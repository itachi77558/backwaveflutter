<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use App\Mail\WelcomeQrCode;

class UserController extends Controller
{
    public function __construct()
    {
        // Configuration de Cloudinary
        Configuration::instance([
            'cloud' => [
                'cloud_name' => 'dsxab4qnu',
                'api_key' => '267848335846173', 
                'api_secret' => 'WLhzU3riCxujR1DXRXyMmLPUCoU'
            ],
            'url' => [
                'secure' => true
            ]
        ]);
    }

    private function generateAndUploadQrCode($userId)
    {
        // Génération du QR Code
        $qrCode = QrCode::create(route('user.profile', $userId))
            ->setSize(300)
            ->setMargin(10);

        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        
        // Sauvegarde temporaire du QR Code
        $tempPath = sys_get_temp_dir() . '/qr-' . $userId . '.png';
        $result->saveToFile($tempPath);

        // Upload vers Cloudinary
        $uploadApi = new UploadApi();
        $result = $uploadApi->upload($tempPath, [
            'folder' => 'qrcodes',
            'public_id' => 'user-' . $userId,
        ]);

        // Suppression du fichier temporaire
        unlink($tempPath);

        return $result['secure_url'];
    }

    public function createAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone_number' => 'required|string|exists:users,phone_number',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user->is_phone_verified) {
            return response()->json(['error' => 'Phone number not verified'], 400);
        }

        // Générer et uploader le QR code
        $qrCodeUrl = $this->generateAndUploadQrCode($user->id);

        // Mettre à jour les informations de l'utilisateur
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'qr_code_url' => $qrCodeUrl,
        ]);

        // Envoyer l'email de bienvenue avec le QR code
        Mail::to($user->email)->send(new WelcomeQrCode($user));

        return response()->json([
            'message' => 'Account created successfully',
            'user' => $user
        ], 201);
    }
}