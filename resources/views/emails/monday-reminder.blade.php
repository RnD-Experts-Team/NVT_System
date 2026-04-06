<x-mail::message>
# Hello, {{ $manager->name }}

This is your weekly reminder to submit the schedule for your department: **{{ $manager->department?->name }}**.

Please log in and fill in all shift assignments for this week before Tuesday at noon.

Thanks,<br>
{{ config('app.name') }} — NVT Schedule System
</x-mail::message>
