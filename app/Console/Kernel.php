<?php

namespace App\Console;

use App\Jobs\CheckInstitutionConfigurationsJob;
use App\Jobs\TenantsJob;
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
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new TenantsJob(CheckInstitutionConfigurationsJob::class))->cron('0 8 1 1 *');

        $schedule->command('notifications:delete')->saturdays()->dailyAt('04:00');

        // Processamento automático do histórico escolar (fila alimentada por trigger
        // quando a situação da matrícula muda)
        if (config('school_history.auto_process.enabled', true) && config('school_history.auto_process.schedule', true)) {
            $schedule->command('historico:processar-automatico' . (config('app.multi_tenant') ? ' --todas-conexoes' : ''))
                ->everyMinute()
                ->withoutOverlapping(30)
                ->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
