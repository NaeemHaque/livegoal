<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use App\Services\Football\Result;
use Illuminate\Http\JsonResponse;

abstract class Controller extends BaseController
{
    /**
     * Wrap a payload in the standard API envelope (see docs/API.md). Returns 503
     * on a hard miss (no data and no last-good) so the SPA can show an error state.
     */
    protected function envelope(mixed $data, Result $result): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'lastUpdated' => $result->lastUpdated,
                'stale' => $result->stale,
                'cached' => $result->cached,
            ],
        ], $result->data === null ? 503 : 200);
    }
}
