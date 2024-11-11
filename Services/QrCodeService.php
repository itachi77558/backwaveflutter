<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Cloudinary\Cloudinary;

class QrCodeService
{
    protected $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => env('CLOUDINARY_API_KEY'),
            'api_secret' => env('CLOUDINARY_API_SECRET')
        ]);
    }

    public function generateAndUploadQrCode(string $userId, string $data)
    {
        // Créer le QR Code
        $qrCode = QrCode::create($data)
            ->setSize(300)
            ->setMargin(10)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255))
            ->setEncoding(new Encoding('UTF-8'));

        // Créer le writer
        $writer = new PngWriter();
        
        // Générer l'image
        $result = $writer->write($qrCode);

        // Créer un fichier temporaire
        $tempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
        file_put_contents($tempFile, $result->getString());

        // Upload vers Cloudinary
        $uploadResult = $this->cloudinary->uploadApi()->upload($tempFile, [
            'folder' => 'qrcodes',
            'public_id' => 'user_' . $userId . '_qr'
        ]);

        // Supprimer le fichier temporaire
        unlink($tempFile);

        return $uploadResult['secure_url'];
    }
}