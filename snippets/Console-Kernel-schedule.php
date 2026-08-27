<?php

// Add inside app/Console/Kernel.php -> schedule(Schedule $schedule)

$schedule->command('loans:process-default-interest --limit=20')
    ->everyMinute()
    ->timezone('Africa/Nairobi')
    ->when(function () {
        $hour = (int) now('Africa/Nairobi')->format('H');

        // Night window: 22:00 through 05:59.
        return $hour >= 22 || $hour < 6;
    })
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();
