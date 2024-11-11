<?php

namespace App\Services;

use App\Models\User;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserCardService
{
    public function generateAndUploadQrCode(User $user)
    {
        // Génération du QR code
        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data("User ID: {$user->id}")
            ->size(150)
            ->margin(10)
            ->build();

        // Créer un nom de fichier temporaire unique
        $tempFile = storage_path('app/temp/' . Str::random(40) . '.png');
        
        // Assurer que le répertoire existe
        if (!file_exists(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0777, true);
        }

        // Sauvegarder le QR code dans un fichier temporaire
        $qrCode->saveToFile($tempFile);

        try {
            // Upload vers Cloudinary
            $uploadResult = Cloudinary::upload($tempFile, [
                'folder' => 'user_qrcodes',
                'public_id' => "user_{$user->id}_qrcode",
                'resource_type' => 'image'
            ]);

            // Récupération de l'URL sécurisée
            $qrCodeUrl = $uploadResult->getSecurePath();

            // Mise à jour de l'utilisateur
            $user->update(['qr_code_url' => $qrCodeUrl]);

            return $qrCodeUrl;
        } finally {
            // Nettoyage : supprimer le fichier temporaire
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
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
        Mail::send([], [], function ($message) use ($user, $htmlContent) {
            $message->to($user->email, $user->first_name)
                ->subject('Welcome to Our Service')
                ->setBody($htmlContent, 'text/html');
        });
    }
}
