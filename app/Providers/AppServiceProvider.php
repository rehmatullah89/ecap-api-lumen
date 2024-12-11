<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
	
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register() {
		//Idea
		$this->app->register(\Idea\Providers\IdeaServiceProvider::class);
		//Idea
		$this->app->register(\Baum\Providers\BaumServiceProvider::class);
		//Routes
		$this->app->register(RouteServiceProvider::class);
		//Excel
		$this->app->register(\Maatwebsite\Excel\ExcelServiceProvider::class);
		class_alias('Maatwebsite\Excel\Facades\Excel', 'Excel');
	}
}
