<?php

use App\Jobs\SendMondayScheduleReminderJob;
use App\Jobs\SendTuesdayReminderAndAutoCopyJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// M2 — Schedule reminder jobs
// Every Monday at 12:00 PM: remind all managers to submit the schedule
Schedule::job(new SendMondayScheduleReminderJob())->weeklyOn(1, '12:00');

// Every Tuesday at 12:00 PM: remind again + auto-copy if schedule not yet submitted
Schedule::job(new SendTuesdayReminderAndAutoCopyJob())->weeklyOn(2, '12:00');

// ─── Manual test commands (run these in terminal to test jobs immediately) ───

Artisan::command('job:test-monday', function () {
    $this->info('Running SendMondayScheduleReminderJob...');
    (new SendMondayScheduleReminderJob())->handle();
    $this->info('Done.');
})->purpose('Test: run the Monday reminder job right now');

Artisan::command('job:test-tuesday', function () {
    $this->info('Running SendTuesdayReminderAndAutoCopyJob...');
    (new SendTuesdayReminderAndAutoCopyJob())->handle();
    $this->info('Done.');
})->purpose('Test: run the Tuesday reminder + auto-copy job right now');
