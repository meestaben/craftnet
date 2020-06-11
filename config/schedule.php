<?php

// Only run scheduled jobs on production
if (getenv('CRAFT_ENV') !== 'prod') {
    return;
}

/** @var $schedule omnilight\scheduling\Schedule */

$schedule->command('craftnet/licenses/send-reminders')
    ->daily()
    ->withoutOverlapping();

$schedule->command('craftnet/licenses/process-expired-licenses')
    ->daily()
    ->withoutOverlapping();

$schedule->command('craftnet/packages/update-deps --queue')
    ->daily()
    ->withoutOverlapping();

$schedule->command('craftnet/plugins/update-install-counts')
    ->daily()
    ->withoutOverlapping();
