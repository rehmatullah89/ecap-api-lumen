<?php

namespace App\Jobs;

use Idea\Base\BaseJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;

class SendPush extends BaseJob {

  /**
   * Send the email.
   *
   * @return void
   */
  public function handle() {
    try {
      if (empty($this->params['parameters'])) {
        $this->params['parameters'] = [];
      }

      if (empty($this->params['extraData'])) {
        $this->params['extraData'] = [];
      }

      //currently not used
      $tokens = !empty($this->params['tokens']) ? $this->params['tokens'] : [];
      if (!$tokens) {
        return FALSE;
      }

      Log::info('Sending Push To  Notification[[' . $this->params['body'] . ']]');

      if (empty($this->params['title'])) {
        $this->params['title'] = config('main.title');
      }

      foreach ($this->params['tokens'] as $locale => $tokens) {
        //Translate message text and title
        app('translator')->setLocale($locale);
        $messageText = trans($this->params['body'], $this->params['parameters']);
        $titleText   = trans($this->params['title'], $this->params['parameters']);

        //send the notification
        $this->sendPush($titleText, $messageText, $this->params['extraData'], $tokens);
      }
    } catch (\Exception $e) {
      \Log::info($e->getMessage());
      throw $e;
    }
  }

  public static function sendPush($title, $body, $dataArray = [], $tokens = []) {
    //create OptionsBuilder and set time to live
    $optionBuilder = new OptionsBuilder();
    $optionBuilder->setTimeToLive(60 * 20);

    //create the notification
    $notificationBuilder = new PayloadNotificationBuilder($title);
    $notificationBuilder->setBody($body)->setSound('default');

    //build notication and option
    $option       = $optionBuilder->build();
    $notification = $notificationBuilder->build();

    //build the data
    $dataBuilder = new PayloadDataBuilder();
    $dataBuilder->addData($dataArray);
    $data = $dataBuilder->build();

    //send the notification
    $downstreamResponse = \FCM::sendTo($tokens, $option, $notification, $data);

    return new JsonResponse(
      [
        'status' => '1',
        'sucess' => $downstreamResponse->numberSuccess(),
        'fail'   => $downstreamResponse->numberFailure(),
        'msg'    => $downstreamResponse->tokensWithError(),
      ],
      200
    );
  }
}
