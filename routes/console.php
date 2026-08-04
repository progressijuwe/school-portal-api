<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
|
| The scheduler runs in production as one of the programs in supervisord.conf,
| so it starts with the deploy rather than needing a service created by hand.
|
| onOneServer() takes an atomic cache lock before a command runs, so if the web
| service is ever scaled past one replica — each with its own scheduler — the
| command still executes exactly once. It needs a lock-capable cache store;
| CACHE_STORE=database qualifies via the cache_locks table.
|
*/

// Tokens now expire (config/sanctum.php); this clears the dead rows.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();

// Failed queue jobs older than a week are noise, not signal.
Schedule::command('queue:prune-failed --hours=168')->daily()->onOneServer();
