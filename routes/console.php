<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily end-to-end publish: selects best keyword/topic, generates, validates, publishes.
// --site accepts WordPressSite ID or NuxtSite ID — the command auto-detects the type.
// alquilatucarro (NuxtSite) — confirm ID via: php artisan db:table nuxt_sites
Schedule::command('seo:daily:publish --site=1 --site-type=nuxt --llm=openai')
    ->dailyAt('08:00')
    ->timezone('America/Bogota')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/daily-publish-site1.log'));
