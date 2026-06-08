<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cron-less fallback: lets an external pinger (e.g. cron-job.org) drive the
 * scheduler every minute on hosts without system cron. Token-guarded; disabled
 * when SCHEDULER_TOKEN is unset. See docs/LIVE_POLLING.md.
 */
class SchedulerController extends Controller
{
    public function run(Request $request): Response
    {
        $token = Config::string('football.scheduler_token');

        if ($token === '' || ! hash_equals($token, (string) $request->query('token', ''))) {
            abort(404);
        }

        Artisan::call('schedule:run');

        return response('OK');
    }
}
