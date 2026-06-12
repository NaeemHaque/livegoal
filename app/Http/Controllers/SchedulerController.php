<?php

namespace App\Http\Controllers;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cron-less fallback: lets an external pinger (e.g. cron-job.org) drive the
 * scheduler every minute on hosts without system cron. Token-guarded; disabled
 * when SCHEDULER_TOKEN is unset. See docs/LIVE_POLLING.md.
 */
class SchedulerController extends Controller
{
    public function run(Request $request, Schedule $schedule, Application $app): Response
    {
        $token = Config::string('football.scheduler_token');

        if ($token === '' || ! hash_equals($token, (string) $request->query('token', ''))) {
            abort(404);
        }

        // Not `schedule:run`: sub-minute events make that command repeat until
        // the minute ends — right for system cron, but an HTTP trigger must
        // return promptly. Run each due event exactly once instead.
        foreach ($schedule->dueEvents($app) as $event) {
            if ($event instanceof Event && $event->filtersPass($app)) {
                $event->run($app);
            }
        }

        return response('OK');
    }
}
