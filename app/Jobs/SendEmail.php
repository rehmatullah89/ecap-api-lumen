<?php

namespace App\Jobs;

use Idea\Base\BaseJob;
use Illuminate\Support\Facades\Mail;
use Log;

//unlike the base job, this one is meant to accept several emails as destinations
class SendEmail extends BaseJob
{

    /**
     * Send the email.
     *
     * @return void
     */
    public function handle()
    {
        // dump($this->params['to']);exit;
        //specify the sent from
        $this->params['from'] = [
            'name' => env('MAIL_FROM_NAME', 'IdeaToLife Testing'),
            'email' => env('MAIL_FROM_ADDRESS', 'youssef.jradeh@ideatolife.me')
        ];

        //if we are notifying the site owner
        if (!empty($this->params['sendToOwner'])) {
            $this->params['to'] = $this->params['from'];
        }

        //send the actual email
        Mail::send(
            $this->params['template'], $this->params, function ($message) {
                $message->setFrom($this->params['from']['email'], $this->params['from']['name']);
                $message->setTo($this->params['to']['email'], $this->params['to']['name']);

                // if the subject is provided
                if (!empty($this->params['subject'])) {
                    $message->subject($this->params['subject']);
                }
                // if attachment is provided
                if (!empty($this->params['attachment'])) {
                    $message->attachData($this->params['attachment'], 'statement.pdf', [
                        'mime' => 'application/pdf',
                    ]);
                }
            }
        );
    }

}
