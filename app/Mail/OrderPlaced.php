<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;

class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
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
            ->subject('Заказ №' . $this->order->id . ' — Global Parts')
            ->view('email.placed');

        // Копия клиенту на почту, указанную при регистрации — раньше
        // письмо уходило только на внутренний ящик Романа, клиент вообще
        // не получал подтверждения (2026-09-01, по просьбе Романа).
        if ($this->order->user?->email) {
            $mail->cc($this->order->user->email, $this->order->user->name);
        }

        return $mail;
    }
}