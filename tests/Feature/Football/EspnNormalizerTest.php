<?php

namespace Tests\Feature\Football;

use App\Services\Football\EspnNormalizer;
use Tests\TestCase;

/**
 * Covers the ESPN POC normalizer: resolving a football-data match to its ESPN
 * event by TLA, re-orienting scores/events to *our* home/away (ESPN may list
 * the match the other way round), and mapping the real clock + official event
 * minutes. See the `espn-keyless-football-api` note.
 */
class EspnNormalizerTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function scoreboard(): array
    {
        return [
            'events' => [[
                'id' => '760421',
                'competitions' => [[
                    'status' => ['displayClock' => "67'", 'type' => ['state' => 'in', 'detail' => '2nd Half']],
                    'venue' => ['fullName' => 'SoFi Stadium'],
                    'competitors' => [
                        ['homeAway' => 'home', 'score' => '2', 'team' => ['id' => '628', 'abbreviation' => 'AUS', 'displayName' => 'Australia', 'shortDisplayName' => 'Australia']],
                        ['homeAway' => 'away', 'score' => '1', 'team' => ['id' => '465', 'abbreviation' => 'TUR', 'displayName' => 'Türkiye', 'shortDisplayName' => 'Türkiye']],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return [
            'keyEvents' => [
                ['type' => ['text' => 'Goal'], 'clock' => ['displayValue' => "27'"], 'team' => ['id' => '628'], 'participants' => [['athlete' => ['displayName' => 'Irankunda']], ['athlete' => ['displayName' => 'Okon-Engstler']]]],
                ['type' => ['text' => 'Yellow Card'], 'clock' => ['displayValue' => "86'"], 'team' => ['id' => '465'], 'participants' => [['athlete' => ['displayName' => 'Akgün']]]],
                ['type' => ['text' => 'Start Delay'], 'clock' => ['displayValue' => "23'"], 'team' => []],
                ['type' => ['text' => 'Halftime'], 'clock' => ['displayValue' => "45'+2'"], 'team' => []],
                // Listed out of order (a 5' goal after later events) — must sort first.
                ['type' => ['text' => 'Goal'], 'clock' => ['displayValue' => "5'"], 'team' => ['id' => '465'], 'participants' => [['athlete' => ['displayName' => 'Yildiz']]]],
            ],
        ];
    }

    public function test_it_resolves_by_tla_and_reorients_scores_to_our_home_away(): void
    {
        $norm = new EspnNormalizer;

        // Our football-data match lists Turkey as home, Australia as away — the
        // reverse of ESPN's orientation. Resolution must follow team identity.
        $resolved = $norm->resolve($this->scoreboard(), [
            'home' => ['tla' => 'TUR', 'name' => 'Turkey'],
            'away' => ['tla' => 'AUS', 'name' => 'Australia'],
        ]);

        $this->assertNotNull($resolved);
        $this->assertSame('465', $resolved['homeTeamId']); // our home = Turkey
        $this->assertSame('628', $resolved['awayTeamId']); // our away = Australia

        $live = $norm->liveData($resolved, $this->summary());

        $this->assertTrue($live['found']);
        $this->assertSame('LIVE', $live['status']);
        $this->assertSame(67, $live['minute']);
        $this->assertSame("67'", $live['displayClock']);

        // Scores re-oriented: Turkey (our home) 1, Australia (our away) 2.
        $this->assertSame(1, $live['homeScore']);
        $this->assertSame(2, $live['awayScore']);

        // Stadium comes from ESPN (football-data leaves venue null for the WC).
        $this->assertSame('SoFi Stadium', $live['venue']);
    }

    public function test_it_maps_events_with_official_minutes_and_sides(): void
    {
        $norm = new EspnNormalizer;

        $resolved = $norm->resolve($this->scoreboard(), [
            'home' => ['tla' => 'TUR', 'name' => 'Turkey'],
            'away' => ['tla' => 'AUS', 'name' => 'Australia'],
        ]);
        $this->assertNotNull($resolved);

        $events = $norm->liveData($resolved, $this->summary())['events'];

        // "Start Delay" dropped; 2 goals + HT + card remain, sorted chronologically
        // (the 5' goal was listed last in the feed but sorts first).
        $this->assertCount(4, $events);

        // Turkey's 5' goal — our home — running score 1-0.
        $this->assertSame('GOAL', $events[0]['type']);
        $this->assertSame(5, $events[0]['minute']);
        $this->assertSame('home', $events[0]['side']);
        $this->assertSame('Yildiz', $events[0]['player']);
        $this->assertSame(1, $events[0]['homeScore']);
        $this->assertSame(0, $events[0]['awayScore']);

        // Australia's 27' goal — our away — with assist; running score 1-1.
        $this->assertSame('GOAL', $events[1]['type']);
        $this->assertSame(27, $events[1]['minute']);
        $this->assertSame('away', $events[1]['side']);
        $this->assertSame('Irankunda', $events[1]['player']);
        $this->assertSame('Okon-Engstler', $events[1]['assist']);
        $this->assertSame(1, $events[1]['homeScore']);
        $this->assertSame(1, $events[1]['awayScore']);

        // Half-time marker sorts between the goals and the late card.
        $this->assertSame('HT', $events[2]['type']);

        // Turkey's yellow card — our home side.
        $this->assertSame('YELLOW_CARD', $events[3]['type']);
        $this->assertSame(86, $events[3]['minute']);
        $this->assertSame('home', $events[3]['side']);
        $this->assertNull($events[3]['assist']);
    }

    public function test_it_returns_null_when_no_event_matches(): void
    {
        $norm = new EspnNormalizer;

        $resolved = $norm->resolve($this->scoreboard(), [
            'home' => ['tla' => 'BRA', 'name' => 'Brazil'],
            'away' => ['tla' => 'ARG', 'name' => 'Argentina'],
        ]);

        $this->assertNull($resolved);
    }

    public function test_not_found_envelope_is_empty(): void
    {
        $live = (new EspnNormalizer)->notFound();

        $this->assertFalse($live['found']);
        $this->assertNull($live['status']);
        $this->assertSame([], $live['events']);
    }

    public function test_status_mapping(): void
    {
        $norm = new EspnNormalizer;

        // ESPN's status.type as the feed really sends it. Crucially `detail` is
        // an abbreviation ("HT"/"FT") while `name`/`description` spell the phase
        // out — the mapping must read across all three (the abbreviated half-time
        // is the bug that surfaced a finished first half as plain LIVE).
        $cases = [
            [['state' => 'pre', 'name' => 'STATUS_SCHEDULED', 'detail' => 'Sat, June 11'], 'SCHEDULED'],
            [['state' => 'post', 'name' => 'STATUS_FULL_TIME', 'description' => 'Full Time', 'detail' => 'FT'], 'FT'],
            [['state' => 'in', 'name' => 'STATUS_HALFTIME', 'description' => 'Halftime', 'detail' => 'HT'], 'HT'],
            [['state' => 'in', 'name' => 'STATUS_FIRST_HALF', 'description' => 'Halftime', 'detail' => 'Halftime'], 'HT'],
            [['state' => 'in', 'name' => 'STATUS_SECOND_HALF', 'description' => '2nd Half', 'detail' => "67'"], 'LIVE'],
            [['state' => 'in', 'name' => 'STATUS_SHOOTOUT', 'description' => 'Penalty Shootout', 'detail' => 'Pens'], 'PEN'],
            [['state' => 'in', 'name' => 'STATUS_FIRST_EXTRA_TIME', 'description' => 'Extra Time', 'detail' => 'ET'], 'ET'],
        ];

        foreach ($cases as [$type, $expected]) {
            $sb = $this->scoreboard();
            $sb['events'][0]['competitions'][0]['status']['type'] = $type;

            $resolved = $norm->resolve($sb, [
                'home' => ['tla' => 'TUR', 'name' => 'Turkey'],
                'away' => ['tla' => 'AUS', 'name' => 'Australia'],
            ]);
            $this->assertNotNull($resolved);

            $this->assertSame($expected, $norm->liveData($resolved, null)['status'], (string) json_encode($type));
        }
    }
}
