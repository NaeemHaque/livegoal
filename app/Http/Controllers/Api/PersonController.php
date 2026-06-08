<?php

namespace App\Http\Controllers\Api;

use App\Services\Football\FootballData;
use App\Services\Football\Normalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;

class PersonController extends Controller
{
    public function __construct(
        private readonly FootballData $football,
        private readonly Normalizer $normalizer,
    ) {}

    public function show(string $id): JsonResponse
    {
        return $this->respond(
            $this->football->cached("person:{$id}", Config::integer('football.ttl.person'), "/persons/{$id}"),
            $this->normalizer->person(...),
        );
    }
}
