<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel {

  /**
   * The Artisan commands provided by your application.
   *
   * @var array
   */
  protected $commands
    = [
      \Laravelista\LumenVendorPublish\VendorPublishCommand::class,
      \App\Console\Commands\UpdatePowerBi::class,
      \App\Console\Commands\SendNotifications::class,
        
    ];

  /**
   * Define the application's command schedule.
   *
   * @param  \Illuminate\Console\Scheduling\Schedule $schedule
   *
   * @return void
   */
  protected function schedule(Schedule $schedule) {
      
        Log::info('Starting Cron Job for send:notifications');
        $schedule->command('send:notifications')->daily()->withoutOverlapping();

        Log::info('Starting Cron Job for export:projects');
        $schedule->command('export:projects')->daily()->withoutOverlapping();

        Log::info('Starting Cron Job for update:results');
        $schedule->command('update:results')->daily()->withoutOverlapping();

        Log::info('Starting Cron Job for update:pbi');
        $schedule->command('update:pbi')->daily()->withoutOverlapping();
          
  }
}
