<?php 

namespace App\Providers;

use App\Console\Commands\SendNotifications;
use Illuminate\Support\ServiceProvider;

class SendNotificationsServiceProvider extends ServiceProvider {
	
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register() {
		
		$this->app->singleton(
			'command.send:notifications',
			function () {
				return new SendNotifications();
			}
		);
		
		$this->commands(
			'command.send:notifications'
		);
		
	}
}