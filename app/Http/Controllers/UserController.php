<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeQRCode;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


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


    public function profile(Request $request)
{
    $user = $request->user();

    return response()->json([
        'id' => $user->id,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'phone_number' => $user->phone_number,
        'qr_code_url' => $user->qr_code_url,
        'balance' => $user->balance,
    ], 200, [], JSON_UNESCAPED_UNICODE); // Réponse JSON propre
}



    public function login(Request $request)
    {
        // Validation des informations d'identification
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
    
        // Récupération de l'utilisateur
        $user = User::where('email', $request->email)->first();
    
        // Vérification des identifiants
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Les identifiants sont incorrects'
            ], 401);
        }
    
        // Vérification si le numéro de téléphone est vérifié
        if (!$user->is_phone_verified) {
            return response()->json([
                'error' => 'Le numéro de téléphone n\'est pas vérifié',
                'needs_verification' => true
            ], 403);
        }
    
        // Supprimer les anciens tokens (optionnel - pour la sécurité)
        $user->tokens()->delete();
    
        // Création d'un nouveau token
        $token = $user->createToken('auth_token')->plainTextToken;
    
        // Préparer les données utilisateur à retourner
        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'qr_code_url' => $user->qr_code_url,
        ];
    
        // Retourner la réponse avec le token et les données utilisateur
        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $userData,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }


    public function transfer(Request $request)
{
    $validator = Validator::make($request->all(), [
        'receiver_phone_number' => 'required|exists:users,phone_number',
        'amount' => 'required|numeric|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 400);
    }

    $sender = auth()->user(); // Utilisateur connecté
    $receiver = User::where('phone_number', $request->receiver_phone_number)->first();

    // Vérification du solde suffisant pour le transfert
    if ($sender->balance < $request->amount) {
        return response()->json([
            'error' => 'Solde insuffisant pour le transfert'
        ], 403);
    }

    // Déduction du montant du solde de l'expéditeur et ajout au destinataire
    $sender->balance -= $request->amount;
    $sender->save();

    $receiver->balance += $request->amount;
    $receiver->save();

    // Enregistrement de la transaction
    $transaction = Transaction::create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'amount' => $request->amount,
        'type' => 'transfer',
    ]);

    return response()->json([
        'message' => 'Transfert réussi',
        'transaction' => $transaction,
    ], 201);
}

    


    







/*
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
            'access_token' => $token,:
            'token_type' => 'Bearer',
        ], 200);
    }

    */

    private function generateAndUploadQrCode($user)
    {
        // Générer un identifiant unique pour l'utilisateur s'il n'en a pas
        $uniqueId = $user->unique_id ?? Str::uuid();
        
        // Créer le contenu du QR Code (vous pouvez personnaliser selon vos besoins)
        $qrContent = json_encode([
            'user_id' => $user->id,
            'unique_id' => $uniqueId,
            'name' => $user->first_name . ' ' . $user->last_name
        ]);

        // Génération du QR Code
        $qrCode = QrCode::create($qrContent)
            ->setSize(300)
            ->setMargin(10);

        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        
        // Sauvegarde temporaire du QR Code
        $tempPath = sys_get_temp_dir() . '/qr-' . $user->id . '.png';
        $result->saveToFile($tempPath);

        // Upload vers Cloudinary
        $uploadApi = new UploadApi();
        $result = $uploadApi->upload($tempPath, [
            'folder' => 'qrcodes',
            'public_id' => 'user-' . $user->id,
        ]);

        // Suppression du fichier temporaire
        unlink($tempPath);

        // Mettre à jour l'identifiant unique de l'utilisateur si nécessaire
        if (!$user->unique_id) {
            $user->unique_id = $uniqueId;
            $user->save();
        }

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

        // Mettre à jour les informations de l'utilisateur
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Générer et uploader le QR code
        $qrCodeUrl = $this->generateAndUploadQrCode($user);
        $user->qr_code_url = $qrCodeUrl;
        $user->save();

        // Envoyer l'email de bienvenue avec le QR code
        Mail::to($user->email)->send(new WelcomeQRCode($user));

        return response()->json([
            'message' => 'Account created successfully',
            'user' => $user
        ], 201);
    }


    

    // Lister toutes les transactions
    public function listTransactions(Request $request)
    {
        $userId = $request->user_id;
        $transactions = Transaction::where('sender_id', $userId)
                                   ->orWhere('receiver_id', $userId)
                                   ->get();

        return response()->json($transactions);
    }


    public function checkContacts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_numbers' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $contacts = User::whereIn('phone_number', $request->phone_numbers)
                        ->get(['id', 'phone_number', 'first_name', 'last_name']);
        return response()->json($contacts);
    }


protected function getAndroidContacts()
{
    // Cette fonction doit être implémentée côté Flutter pour renvoyer la liste des contacts Android vers Laravel
    return [];
}


}