<?php

namespace App\Http\Controllers\Api;

use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class CompetitionController extends Controller
{
    public function __construct(
        private readonly FootballData $football,
        private readonly Normalizer $normalizer,
    ) {}

    public function index(): JsonResponse
    {
        $result = $this->football->cached(
            'competitions',
            Config::integer('football.ttl.competitions'),
            '/competitions',
        );

        $data = $result->data === null ? null : $this->normalizer->competitions($result->data);

        return $this->envelope($data, $result);
    }

    public function show(string $id): JsonResponse
    {
        $result = $this->football->cached(
            "competition:{$id}",
            Config::integer('football.ttl.competition'),
            "/competitions/{$id}",
        );

        $data = $result->data === null ? null : $this->normalizer->competition($result->data);

        return $this->envelope($data, $result);
    }

    public function standings(string $id): JsonResponse
    {
        $result = $this->football->cached(
            "standings:{$id}",
            Config::integer('football.ttl.standings'),
            "/competitions/{$id}/standings",
        );

        $data = $result->data === null ? null : $this->normalizer->standings($result->data);

        return $this->envelope($data, $result);
    }

    public function matches(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'matchday' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:20'],
            'stage' => ['nullable', 'string', 'max:40'],
        ]);

        $query = [];

        if ($request->filled('matchday')) {
            $query['matchday'] = $request->integer('matchday');
        }

        if ($request->filled('status')) {
            $query['status'] = strtoupper((string) $request->string('status'));
        }

        if ($request->filled('stage')) {
            $query['stage'] = strtoupper((string) $request->string('stage'));
        }

        $result = $this->football->cached(
            "competition:{$id}:matches",
            Config::integer('football.ttl.matches'),
            "/competitions/{$id}/matches",
            $query,
        );

        $data = $result->data === null ? null : $this->normalizer->matches($result->data);

        return $this->envelope($data, $result);
    }
}
