<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('command:shizhong-warning-sync admission_yesterday --no-quality')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/shizhong-warning-sync-admission-yesterday.log'));

        $schedule->command('command:shizhong-warning-sync in_hospital_cleanup')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/shizhong-warning-sync-in-hospital-cleanup.log'));

        $warningScenes = [
            'admission_8',
            'admission_24',
            'admission_48',
            'discharge_24',
            'discharge_superior_round_day',
            'discharge_168',
            'critical_value_24',
            'transfusion_24',
            'superior_round_cycle',
            'stage_summary_30d',
            'transfer_in_24',
            'ct_progress_72',
            'mr_progress_72',
            'antibiotic_progress_72',
            'chemo_progress_72',
            'death_discussion_168',
        ];

        foreach ($warningScenes as $scene) {
            $schedule->command('command:shizhong-warning-sync ' . $scene)
                ->everyTenMinutes()
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/shizhong-warning-sync-' . str_replace('_', '-', $scene) . '.log'));
        }

        $schedule->command('command:shizhong-warning-sync pending_warnings')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/shizhong-warning-sync-pending-warnings.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
