<?php

namespace App\Http\Controllers\Api;

use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class TeamController extends Controller
{
    public function __construct(
        private readonly FootballData $football,
        private readonly Normalizer $normalizer,
    ) {}

    public function show(string $id): JsonResponse
    {
        return $this->respond(
            $this->football->cached("team:{$id}", Config::integer('football.ttl.team'), "/teams/{$id}"),
            $this->normalizer->teamDetail(...),
        );
    }

    public function matches(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = [];

        if ($request->filled('status')) {
            $query['status'] = strtoupper((string) $request->string('status'));
        }

        if ($request->filled('dateFrom')) {
            $query['dateFrom'] = (string) $request->string('dateFrom');
        }

        if ($request->filled('dateTo')) {
            $query['dateTo'] = (string) $request->string('dateTo');
        }

        return $this->respond(
            $this->football->cached("team:{$id}:matches", Config::integer('football.ttl.team_matches'), "/teams/{$id}/matches", $query),
            $this->normalizer->matches(...),
        );
    }
}
