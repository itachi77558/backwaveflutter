<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeQrCodeMail;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{

    protected $qrCodeService;

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
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

        // Mettre à jour les informations de base de l'utilisateur
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Générer les données pour le QR Code (vous pouvez personnaliser selon vos besoins)
        $qrData = json_encode([
            'user_id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'phone' => $user->phone_number
        ]);

        // Générer et uploader le QR Code
        $qrCodeUrl = $this->qrCodeService->generateAndUploadQrCode($user->id, $qrData);
        
        // Mettre à jour l'URL du QR Code
        $user->update(['qr_code_url' => $qrCodeUrl]);

        // Envoyer l'email avec le QR Code
        Mail::to($user->email)->send(new WelcomeQrCodeMail($user, $qrCodeUrl));

        return response()->json([
            'message' => 'Account created successfully', 
            'user' => $user
        ], 201);
    }


    public function login(Request $request)
    {
        // Validation des informations d'identification
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Récupération de l'utilisateur
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Création d'un token pour l'utilisateur (avec Laravel Sanctum par exemple)
        $token = $user->createToken('auth_token')->plainTextToken;

        // Retourner la réponse avec le token
        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    
}
