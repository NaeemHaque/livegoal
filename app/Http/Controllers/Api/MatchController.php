<?php

namespace App\Http\Controllers\Api;

use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

class MatchController extends Controller
{
    public function __construct(
        private readonly FootballData $football,
        private readonly Normalizer $normalizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'competition' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $date = $request->filled('date') ? (string) $request->string('date') : Date::now()->toDateString();
        $query = ['dateFrom' => $date, 'dateTo' => $date];

        if ($request->filled('competition')) {
            $query['competitions'] = strtoupper((string) $request->string('competition'));
        }

        if ($request->filled('status')) {
            $query['status'] = strtoupper((string) $request->string('status'));
        }

        $result = $this->football->cached('matches', Config::integer('football.ttl.matches'), '/matches', $query);

        $data = $result->data === null ? null : $this->normalizer->matches($result->data);

        return $this->envelope($data, $result);
    }

    public function show(string $id): JsonResponse
    {
        $result = $this->football->cached(
            "match:{$id}",
            Config::integer('football.ttl.match_live'),
            "/matches/{$id}",
        );

        $data = $result->data === null ? null : $this->normalizer->match($result->data);

        return $this->envelope($data, $result);
    }
}
