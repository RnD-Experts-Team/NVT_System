<x-mail::message>
# Hello, {{ $manager->name }}

No schedule has been submitted yet for your department **{{ $manager->department?->name }}** for the week of **{{ $weekStart }}**.

The system has automatically copied last week's schedule as a draft. Please review and publish it, or make any necessary changes.

If last week's schedule was not available, please fill in the schedule manually as soon as possible.

Thanks,<br>
{{ config('app.name') }} — NVT Schedule System
</x-mail::message>
