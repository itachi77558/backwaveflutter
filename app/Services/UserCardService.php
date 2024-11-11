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
        // Génération du QR code en mémoire
        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data("User ID: {$user->id}")
            ->size(150)
            ->margin(10)
            ->build();

        // Enregistrement en mémoire pour l'upload vers Cloudinary
        $qrCodeData = $qrCode->getString();

        // Upload directement vers Cloudinary
        $uploadResult = Cloudinary::upload(
            $qrCodeData, 
            [
                'folder' => 'user_qrcodes',
                'public_id' => "user_{$user->id}_qrcode",
                'resource_type' => 'image'
            ]
        );

        // Récupération de l'URL sécurisée du QR code
        $qrCodeUrl = $uploadResult->getSecurePath();

        // Mise à jour de l'utilisateur avec l'URL du QR code
        $user->update(['qr_code_url' => $qrCodeUrl]);

        return $qrCodeUrl;
    }

    public function sendUserCardByEmail(User $user)
    {
        // Générer et récupérer l'URL du QR code
        $qrCodeUrl = $this->generateAndUploadQrCode($user);

        // Contenu HTML de l'email
        $htmlContent = view('emails.user_card', [
            'user' => $user,
            'qrCodeUrl' => $qrCodeUrl
        ])->render();

        // Envoi de l'email avec la carte et le QR code
        Mail::send([], [], function($message) use ($user, $htmlContent) {
            $message->to($user->email, $user->first_name)
                    ->subject('Welcome to Our Service')
                    ->setBody($htmlContent, 'text/html');
        });
    }
}
