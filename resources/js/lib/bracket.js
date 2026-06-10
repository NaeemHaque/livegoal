/**
 * Knockout-stage rounds, in bracket order. Each competition only uses a subset
 * of these stage keys; missing rounds are skipped.
 */
const KO_ORDER = [
    { key: 'LAST_32', title: 'Round of 32' },
    { key: 'PLAYOFFS', title: 'Play-offs' },
    { key: 'LAST_16', title: 'Round of 16' },
    { key: 'QUARTER_FINALS', title: 'Quarter-finals' },
    { key: 'SEMI_FINALS', title: 'Semi-finals' },
    { key: 'FINAL', title: 'Final' },
    // Third place is a standalone match (not fed by the final), so it trails the
    // bracket — this keeps every main round a clean 2:1 for the connector lines.
    { key: 'THIRD_PLACE', title: 'Third place' },
];

const LIVE_STATUSES = ['LIVE', 'HT', 'ET', 'PEN'];

/**
 * Collapse a stage's matches into ties, summing both legs of two-legged ties so
 * the column shows one node per matchup (single-leg competitions get one leg).
 *
 * @param {Array<object>} stageMatches
 * @returns {Array<{ id: string, home: object|null, away: object|null, hs: number|null, as: number|null, live: boolean }>}
 */
function aggregateTies(stageMatches) {
    const ties = new Map();
    const chronological = [...stageMatches].sort((a, b) =>
        String(a.kickoff ?? '').localeCompare(String(b.kickoff ?? '')),
    );

    for (const m of chronological) {
        // Undrawn matches carry placeholder teams with an empty id, not null.
        const home = m.home?.id ? m.home : null;
        const away = m.away?.id ? m.away : null;

        // Two-legged ties (both teams known) merge by the unordered team pair.
        // Undrawn matches (TBD teams) must each stay their own node, or every
        // placeholder in a round would collapse into one — so key them by match id.
        const pairKey =
            home && away
                ? [String(home.id), String(away.id)].sort().join('|')
                : `match:${m.id}`;

        if (!ties.has(pairKey)) {
            ties.set(pairKey, {
                id: m.id,
                home,
                away,
                hs: null,
                as: null,
                live: false,
                kickoff: m.kickoff ?? null,
            });
        }

        const tie = ties.get(pairKey);

        if (m.homeScore != null && m.awayScore != null) {
            const homeIsTieHome = String(m.home?.id) === String(tie.home?.id);
            tie.hs =
                (tie.hs ?? 0) + (homeIsTieHome ? m.homeScore : m.awayScore);
            tie.as =
                (tie.as ?? 0) + (homeIsTieHome ? m.awayScore : m.homeScore);
        }

        if (LIVE_STATUSES.includes(m.status)) {
            tie.live = true;
        }

        // Tapping a tie opens its most recent leg.
        tie.id = m.id;
    }

    return [...ties.values()];
}

/**
 * Build ordered bracket rounds from a competition's full match list. Returns an
 * empty array when there are no knockout-stage matches.
 *
 * @param {Array<object>} matches
 * @returns {Array<{ title: string, ties: Array<object> }>}
 */
export function buildKnockoutRounds(matches) {
    const byStage = new Map();

    for (const m of matches ?? []) {
        if (!m?.stage) {
            continue;
        }

        if (!byStage.has(m.stage)) {
            byStage.set(m.stage, []);
        }

        byStage.get(m.stage).push(m);
    }

    return KO_ORDER.filter((stage) => byStage.has(stage.key)).map((stage) => ({
        title: stage.title,
        ties: aggregateTies(byStage.get(stage.key)),
    }));
}
