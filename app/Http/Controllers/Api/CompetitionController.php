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
        return $this->respond(
            $this->football->cached('competitions', Config::integer('football.ttl.competitions'), '/competitions'),
            $this->normalizer->competitions(...),
        );
    }

    public function show(string $id): JsonResponse
    {
        return $this->respond(
            $this->football->cached("competition:{$id}", Config::integer('football.ttl.competition'), "/competitions/{$id}"),
            $this->normalizer->competition(...),
        );
    }

    public function standings(string $id): JsonResponse
    {
        return $this->respond(
            $this->football->cached("standings:{$id}", Config::integer('football.ttl.standings'), "/competitions/{$id}/standings"),
            $this->normalizer->standings(...),
        );
    }

    public function matches(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'matchday' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:20'],
            'stage' => ['nullable', 'string', 'max:40'],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = [];

        if ($request->filled('dateFrom')) {
            $query['dateFrom'] = (string) $request->string('dateFrom');
        }

        if ($request->filled('dateTo')) {
            $query['dateTo'] = (string) $request->string('dateTo');
        }

        if ($request->filled('matchday')) {
            $query['matchday'] = $request->integer('matchday');
        }

        if ($request->filled('status')) {
            $query['status'] = strtoupper((string) $request->string('status'));
        }

        if ($request->filled('stage')) {
            $query['stage'] = strtoupper((string) $request->string('stage'));
        }

        return $this->respond(
            $this->football->cached("competition:{$id}:matches", Config::integer('football.ttl.matches'), "/competitions/{$id}/matches", $query),
            $this->normalizer->matches(...),
        );
    }

    public function teams(string $id): JsonResponse
    {
        return $this->respond(
            $this->football->cached("competition:{$id}:teams", Config::integer('football.ttl.teams'), "/competitions/{$id}/teams"),
            $this->normalizer->teams(...),
        );
    }

    public function scorers(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = [];

        if ($request->filled('limit')) {
            $query['limit'] = $request->integer('limit');
        }

        return $this->respond(
            $this->football->cached("competition:{$id}:scorers", Config::integer('football.ttl.scorers'), "/competitions/{$id}/scorers", $query),
            $this->normalizer->scorers(...),
        );
    }
}
