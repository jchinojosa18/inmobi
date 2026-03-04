<?php

use App\Console\Commands\InmoDailyCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily baseline command placeholder for future fines/reminders/closures workflows.
Schedule::command(InmoDailyCommand::class)
    ->dailyAt('00:05')
    ->withoutOverlapping();
