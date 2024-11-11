<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeQrCodeMail extends Mailable
{
    use SerializesModels;

    public $user;
    public $qrCodeUrl;

    public function __construct($user, $qrCodeUrl)
    {
        $this->user = $user;
        $this->qrCodeUrl = $qrCodeUrl;
    }

    public function build()
    {
        return $this->view('emails.welcome-qrcode')
                    ->subject('Bienvenue - Votre QR Code Personnel');
    }
}