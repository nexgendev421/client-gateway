<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('router:check-pos-health')->everyFiveMinutes()->withoutOverlapping(60);
Schedule::command('router:prune-health-checks')->dailyAt('02:30')->withoutOverlapping(10);
