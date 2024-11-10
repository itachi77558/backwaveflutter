<?php

namespace App\Services;

use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Nette\Utils\Image;

class UserCardService
{
    public function generateUserCard(User $user)
    {
        // Generate QR code
        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data("User ID: {$user->id}")
            ->size(150)
            ->margin(10)
            ->build();
        
        // Save the QR code to storage
        $qrCodePath = 'qrcodes/' . $user->id . '_qrcode.png';
        Storage::put($qrCodePath, $qrCode->getString());

        // Create user card with Intervention Image
        $card = Image::canvas(600, 400, '#ffffff');
        $card->text("Welcome, {$user->first_name} {$user->last_name}", 300, 50, function($font) {
            $font->file(public_path('fonts/Roboto-Bold.ttf'));
            $font->size(24);
            $font->color('#333333');
            $font->align('center');
        });

        // Add user info
        $card->text("Phone: {$user->phone_number}", 300, 120, function($font) {
            $font->file(public_path('fonts/Roboto-Regular.ttf'));
            $font->size(18);
            $font->color('#333333');
            $font->align('center');
        });
        $card->text("Email: {$user->email}", 300, 160, function($font) {
            $font->file(public_path('fonts/Roboto-Regular.ttf'));
            $font->size(18);
            $font->color('#333333');
            $font->align('center');
        });

        // Insert QR Code image
        $qrImage = Image::make(Storage::path($qrCodePath));
        $card->insert($qrImage, 'bottom-right', 20, 20);

        // Save card image
        $cardPath = 'cards/' . $user->id . '_card.png';
        Storage::put($cardPath, (string) $card->encode());

        return $cardPath;
    }

    public function sendUserCardByEmail(User $user)
    {
        $cardPath = $this->generateUserCard($user);

        // Send email with the user card attached
        Mail::send('emails.user_card', ['user' => $user], function($message) use ($user, $cardPath) {
            $message->to($user->email, $user->first_name)
                    ->subject('Welcome to Our Service')
                    ->attach(Storage::path($cardPath), [
                        'as' => 'UserCard.png',
                        'mime' => 'image/png',
                    ]);
        });
    }
}
