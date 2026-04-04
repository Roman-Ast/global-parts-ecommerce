<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SparePartRequest extends Mailable
{
   
    use Queueable, SerializesModels;

    public $requestData;
    public $photos;

    /**
     * Create a new message instance.
     *
     * @param array $requestData
     * @param array $photos
     * @return void
     */
    public function __construct(array $requestData, array $photos = [])
    {
        $this->requestData = $requestData;
        $this->photos = $photos;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
     public function build()
    {
        $mail = $this
            ->to('globalparts.ast@inbox.ru')
            ->subject('Запрос подбора по винкоду')
            ->view('email.sparePartRequest');

        if (!empty($this->photos)) {
            foreach ($this->photos as $photo) {
                if ($photo && $photo->isValid()) {
                    $mail->attach(
                        $photo->getRealPath(),
                        [
                            'as' => $photo->getClientOriginalName(),
                            'mime' => $photo->getMimeType(),
                        ]
                    );
                }
            }
        }

        return $mail;
    }
}