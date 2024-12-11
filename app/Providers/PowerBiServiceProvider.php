<?php 

namespace App\Providers;

use App\Console\Commands\UpdatePowerBi;
use Illuminate\Support\ServiceProvider;

class PowerBiServiceProvider extends ServiceProvider {
	
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register() {
		
		$this->app->singleton(
			'command.update:pbi',
			function () {
				return new UpdatePowerBi();
			}
		);
		
		$this->commands(
			'command.update:pbi'
		);
		
	}
}