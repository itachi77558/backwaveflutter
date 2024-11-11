<?php

namespace App\Services;

use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class UserCardService
{
    public function generateUserQrCode(User $user)
    {
        // Générer le code QR avec Endroid\QrCode
        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data("User ID: {$user->id}")
            ->size(150)
            ->margin(10)
            ->build();
        
        // Sauvegarder le code QR
        $qrCodePath = 'qrcodes/' . $user->id . '_qrcode.png';
        Storage::put($qrCodePath, $qrCode->getString());

        return $qrCodePath;
    }

    public function sendUserCardByEmail(User $user)
    {
        // Générer le code QR et obtenir le chemin du fichier
        $qrCodePath = $this->generateUserQrCode($user);

        // Contenu HTML de l'email
        $htmlContent = "
            <h1>Welcome, {$user->first_name} {$user->last_name}!</h1>
            <p>Thank you for joining our service. Here are your account details:</p>
            <ul>
                <li><strong>Full Name:</strong> {$user->first_name} {$user->last_name}</li>
                <li><strong>Phone:</strong> {$user->phone_number}</li>
                <li><strong>Email:</strong> {$user->email}</li>
            </ul>
            <p>Please find your QR code attached below. You can use this code to access your account and verify your identity.</p>
            <p>Thank you,<br>Our Team</p>
        ";

        // Envoi de l'email avec le QR code en pièce jointe
        Mail::send([], [], function($message) use ($user, $qrCodePath, $htmlContent) {
            $message->to($user->email, $user->first_name)
                    ->subject('Welcome to Our Service')
                    ->setBody($htmlContent, 'text/html')
                    ->attach(Storage::path($qrCodePath), [
                        'as' => 'UserQRCode.png',
                        'mime' => 'image/png',
                    ]);
        });
    }
}
