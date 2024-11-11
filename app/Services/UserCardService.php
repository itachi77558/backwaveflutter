<?php

namespace App\Services;

use App\Models\User;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Mail;

class UserCardService
{
    public function generateAndUploadQrCode(User $user)
{
    // Génération du QR code en tant que chaîne de caractères en base64 sans stockage local
    $qrCode = Builder::create()
        ->writer(new PngWriter())
        ->data("User ID: {$user->id}")
        ->size(150)
        ->margin(10)
        ->build();

    $qrCodeData = $qrCode->getString(); // Obtenir le contenu du QR code en tant que chaîne

    // Upload direct sur Cloudinary
    $uploadResult = Cloudinary::upload(
        'data:image/png;base64,' . base64_encode($qrCodeData),  // Conversion du contenu en base64 pour l’upload direct
        [
            'folder' => 'user_qrcodes',
            'public_id' => "user_{$user->id}_qrcode",
            'overwrite' => true,
            'resource_type' => 'image',
        ]
    );

    // Récupération de l'URL du QR code stocké
    $qrCodeUrl = $uploadResult->getSecurePath();

    // Enregistrement de l'URL dans la colonne `qr_code_url` de l’utilisateur
    $user->update(['qr_code_url' => $qrCodeUrl]);

    return $qrCodeUrl;
}


    public function sendUserCardByEmail(User $user)
    {
        // Générer et récupérer l'URL du QR code
        $qrCodeUrl = $this->generateAndUploadQrCode($user);

        // Contenu HTML de l'email
        $htmlContent = "
            <h1>Welcome, {$user->first_name} {$user->last_name}!</h1>
            <p>Thank you for joining our service. Here are your account details:</p>
            <ul>
                <li><strong>Full Name:</strong> {$user->first_name} {$user->last_name}</li>
                <li><strong>Phone:</strong> {$user->phone_number}</li>
                <li><strong>Email:</strong> {$user->email}</li>
            </ul>
            <p>Please find your QR code below. You can use this code to access your account and verify your identity.</p>
            <img src='{$qrCodeUrl}' alt='User QR Code'>
            <p>Thank you,<br>Our Team</p>
        ";

        // Envoi de l'email
        Mail::send([], [], function($message) use ($user, $htmlContent) {
            $message->to($user->email, $user->first_name)
                    ->subject('Welcome to Our Service')
                    ->setBody($htmlContent, 'text/html');
        });
    }
}
