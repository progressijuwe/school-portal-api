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
| Requires a scheduler process in production (`php artisan schedule:work`, or
| a Railway cron hitting `php artisan schedule:run` every minute).
|
*/

// Tokens now expire (config/sanctum.php); this clears the dead rows.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Failed queue jobs older than a week are noise, not signal.
Schedule::command('queue:prune-failed --hours=168')->daily();
