<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\EcapAppVersionMiddleware;

class RouteServiceProvider extends ServiceProvider {

  /**
   * This namespace is applied to your controller routes.
   *
   * In addition, it is set as the URL generator's root namespace.
   *
   * @var string
   */
  protected $namespace = 'App\Http\Controllers';

  protected $ideaNamespace = 'Idea\Http\Controllers';


  /**
   * Define the routes for the application.
   *
   * @return void
   */
  public function boot() {
    $this->app->get(
      '/',
      function () {
        echo 'It works';
      }
    );

    //Map Framework routes first
    $this->mapFrameworkPublicRoutes();
    $this->mapFrameworkPrivateRoutes();

    //Then Framework Admin Routes
    $this->mapAdminFrameworkPublicRoutes();
    $this->mapAdminFrameworkPrivateRoutes();

    //App routes
    $this->mapPublicRoutes();
    $this->mapPrivateRoutes();

    //Admin Routes
    $this->mapAdminPublicRoutes();
    $this->mapAdminPrivateRoutes();
  }

  protected function mapFrameworkPublicRoutes() {
    $this->app->group(
      [
        'middleware' => ['DeviceMiddleware', 'DBTransaction'],
        'namespace'  => $this->ideaNamespace,
      ],
      function ($router) {
        require idea_path('Routes/app/public.php');
      }
    );
  }

  protected function mapFrameworkPrivateRoutes() {
    $this->app->group(
      [
        'middleware' => [
          'AppAuthenticate',
          'DeviceMiddleware',
          'DBTransaction',
        ],
        'namespace'  => $this->ideaNamespace,
      ],
      function ($router) {
        require idea_path('Routes/app/private.php');
      }
    );
  }

  protected function mapAdminFrameworkPublicRoutes() {
    $this->app->group(
      [
        'middleware' => ['DeviceMiddleware', 'DBTransaction'],
        'namespace'  => $this->ideaNamespace,
      ],
      function ($router) {
        require idea_path('Routes/admin/public.php');
      }
    );
  }

  protected function mapAdminFrameworkPrivateRoutes() {
    $this->app->group(
      [
        'middleware' => [
          'AdminAuthenticate',
          'DeviceMiddleware',
          'DBTransaction',
        ],
        'namespace'  => $this->ideaNamespace,
      ],
      function ($router) {
        require idea_path('Routes/admin/private.php');
      }
    );
  }

  protected function mapPublicRoutes() {
    $this->app->group(
      [
        'middleware' => ['ecapAppVersionMiddleware', 'DeviceMiddleware', 'DBTransaction'],
        'namespace'  => $this->namespace,
      ],
      function ($router) {
        require base_path('app/Routes/app/public.php');
      }
    );
  }

  protected function mapPrivateRoutes() {
    $this->app->group(
      [
        'middleware' => [
          'ecapAppVersionMiddleware',
          'AppAuthenticate',
          'DeviceMiddleware',
          'DBTransaction',
        ],
        'namespace'  => $this->namespace,
      ],
      function ($router) {
        require base_path('app/Routes/app/private.php');
      }
    );
  }

  protected function mapAdminPublicRoutes() {
    $this->app->group(
      [
        'middleware' => ['DeviceMiddleware', 'DBTransaction'],
        'namespace'  => $this->namespace,
      ],
      function ($router) {
        require base_path('app/Routes/admin/public.php');
      }
    );
  }

  protected function mapAdminPrivateRoutes() {
    $this->app->group(
      [
        'middleware' => [
          'AdminAuthenticate',
          'DeviceMiddleware',
          'DBTransaction',
        ],
        'namespace'  => $this->namespace,
      ],
      function ($router) {
        require base_path('app/Routes/admin/private.php');
      }
    );
  }
}
