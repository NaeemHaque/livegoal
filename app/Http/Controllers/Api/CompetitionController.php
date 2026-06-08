<?php

namespace App\Http\Controllers\Api;

use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Http\JsonResponse;
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
}
