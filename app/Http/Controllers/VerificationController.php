<?php

// app/Http/Controllers/VerificationController.php

namespace App\Http\Controllers;

use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Twilio\Rest\Client;

class VerificationController extends Controller
{
    public function sendVerificationCode(Request $request)
    {
        // Validation du numéro de téléphone
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|unique:verification_codes',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Générer un code de vérification aléatoire
        $code = rand(100000, 999999);

        // Enregistrer ou mettre à jour le code dans la base de données
        VerificationCode::updateOrCreate(
            ['phone_number' => $request->phone_number],
            ['code' => $code]
        );

        // Envoyer le code via Twilio
        $this->sendSms($request->phone_number, "Your verification code is $code");

        return response()->json(['message' => 'Verification code sent successfully.']);
    }

    private function sendSms($phoneNumber, $message)
    {
        // Récupération des identifiants Twilio à partir du fichier .env
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $from = env('TWILIO_PHONE_NUMBER');

        // Vérification des identifiants Twilio
        if (!$sid || !$token || !$from) {
            return response()->json(['error' => 'Twilio credentials are not set properly in the .env file.'], 500);
        }

        try {
            $twilio = new Client($sid, $token);
            $twilio->messages->create($phoneNumber, [
                'from' => $from,
                'body' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send SMS: ' . $e->getMessage()], 500);
        }
    }
}
