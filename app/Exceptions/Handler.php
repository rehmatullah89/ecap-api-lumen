<?php

namespace App\Exceptions;

use Exception;
use Idea\Http\Response\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler {

  /**
   * A list of the exception types that should not be reported.
   *
   * @var array
   */
  protected $dontReport
    = [
      AuthorizationException::class,
      HttpException::class,
      ModelNotFoundException::class,
      HttpResponseException::class,
      ValidationException::class,
    ];

  /**
   * Report or log an exception.
   *
   * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
   *
   * @param  \Exception $e
   *
   * @return void
   */
  public function report(Exception $e) {
    parent::report($e);
  }

  /**
   * Render an exception into an HTTP response.
   *
   * @param  \Illuminate\Http\Request $request
   * @param  \Exception $e
   *
   * @return \Illuminate\Http\Response
   */

  public function render($request, Exception $e) {
    if (app()->environment('local')) {
      return parent::render($request, $e);
    }

    $response = $this->checkOurExceptions($e);
    if (!$response->getMessage()) {
      $response->setMessage(Response::STATUS_FAILED);
    }

    return $response->generate(200);
  }

  /**
   * @param \Exception $e
   *
   */
  protected function checkOurExceptions(Exception $e) {
    if (!in_array(get_class($e), $this->dontReport)) {
      return new Response();
    }

    //get response from the exception
    $response = $e->getResponse();

    //retrieve message properties since the value are protected
    $data = $response->getData();

    //set the actual status and message
    $response->setStatus($data->status);
    $response->setMessage($data->message);

    //override data with the actual data
    if (!empty($data->data)) {
      $response->updateData($data->data);
    }

    return $response;
  }
}
