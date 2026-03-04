<?php

use App\Console\Commands\InmoBackupCommand;
use App\Console\Commands\InmoDailyCommand;
use App\Support\SystemHeartbeatService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    app(SystemHeartbeatService::class)->touch('scheduler', 'ok', [
        'source' => 'schedule',
    ]);
})
    ->name('system:heartbeat:scheduler')
    ->everyMinute()
    ->withoutOverlapping();

// Daily baseline command placeholder for future fines/reminders/closures workflows.
Schedule::command(InmoDailyCommand::class)
    ->dailyAt('00:15')
    ->timezone('America/Tijuana')
    ->withoutOverlapping();

Schedule::command('inmo:penalties:run')
    ->dailyAt('00:05')
    ->timezone('America/Tijuana')
    ->withoutOverlapping();

Schedule::command('inmo:generate-rent --month='.now('America/Tijuana')->format('Y-m'))
    ->monthlyOn(1, '00:10')
    ->timezone('America/Tijuana')
    ->withoutOverlapping();

Schedule::command(InmoBackupCommand::class)
    ->dailyAt('03:10')
    ->timezone('America/Tijuana')
    ->withoutOverlapping();
