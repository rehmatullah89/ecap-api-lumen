<?php namespace App\Providers;

use App\Console\Commands\ExportProjects;
use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider {
	
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register() {
		
		$this->app->singleton(
			'command.export:projects',
			function () {
				return new ExportProjects();
			}
		);
		
		$this->commands(
			'command.export:projects'
		);
		
	}
}