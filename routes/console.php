<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily end-to-end publish: selects best keyword, generates, validates, publishes → WP + Nuxt
// alquilatucarro — site_id=1
Schedule::command('seo:daily:publish --site=1 --sync-nuxt')
    ->dailyAt('08:00')
    ->timezone('America/Bogota')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/daily-publish.log'));
