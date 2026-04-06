<?php

namespace App\Jobs;

use App\Mail\MondayReminderMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendMondayScheduleReminderJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $managerRoles = ['L2', 'L2PM', 'L3', 'L4', 'L5', 'L6'];

        // Collect all users who hold a manager-level Spatie role
        $managers = User::with('department')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $managerRoles))
            ->whereNotNull('department_id')
            ->get();

        foreach ($managers as $manager) {
            Mail::to($manager->email)->send(new MondayReminderMail($manager));
        }
    }
}
