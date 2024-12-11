<?php

function idea_path($path = '') {
  return app()->basePath() . '/vendor/idea/framework/src/Idea' . ($path ? '/' . $path : $path);
}

if (!function_exists('config_path')) {
  function config_path($path = '') {
    return app()->basePath() . '/config' . ($path ? '/' . $path : $path);
  }
}
if (!function_exists('public_path')) {

  function public_path($path = '') {
    return app()->basePath() . '/public' . ($path ? '/' . $path : $path);
  }
}
if (!function_exists('mail_url')) {

  function mail_url($path = '') {
    $url = env("APP_URL", "http://localhost:8000");

    return $url . '/' . $path;
  }
}
if (!function_exists('request')) {
  function request($key = NULL, $default = NULL) {
    if (is_null($key)) {
      return app('request');
    }

    if (is_array($key)) {
      return app('request')->only($key);
    }

    return app('request')->input($key, $default);
  }
}
if (!function_exists('resource')) {
  function resource($uri, $controller, $withImage = FALSE) {
    //$verbs = array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE');
    global $app;
    $app->get($uri, $controller . '@index');
    $app->get($uri . '/{id}', $controller . '@one');
    $app->post($uri, $controller . '@store');
    $app->post($uri . '/{id}', $controller . '@update');
    $app->delete($uri . '/{id}', $controller . '@destroy');
    if ($withImage) {
      $app->delete($uri . '/{id}/removeImage', $controller . '@removeImage');
    }
  }
}

if (!function_exists('dot_if_empty')) {
  function dot_if_empty(&$item, $key) {
      if($item!= 0 && empty($item))
      {
          $item = ".";
      }
  }
}