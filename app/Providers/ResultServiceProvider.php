<?php 

namespace App\Providers;

use App\Console\Commands\UpdateResults;
use Illuminate\Support\ServiceProvider;

class ResultServiceProvider extends ServiceProvider {
	
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register() {
		
		$this->app->singleton(
			'command.update:results',
			function () {
				return new UpdateResults();
			}
		);
		
		$this->commands(
			'command.update:results'
		);
		
	}
}