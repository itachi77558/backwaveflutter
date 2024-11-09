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

        // Envoyer le code via Twilio (assurez-vous d'avoir configuré Twilio)
        $this->sendSms($request->phone_number, "Your verification code is $code");

        return response()->json(['message' => 'Verification code sent successfully.']);
    }

    private function sendSms($phoneNumber, $message)
{
    $sid = env('TWILIO_SID');
    $token = env('TWILIO_AUTH_TOKEN');
    $from = env('TWILIO_PHONE_NUMBER');

    // Vérifier les valeurs des variables d'environnement
    if (!$sid || !$token || !$from) {
        throw new \Exception('Twilio credentials are missing');
    }

    $twilio = new Client($sid, $token);
    $twilio->messages->create($phoneNumber, [
        'from' => $from,
        'body' => $message
    ]);
}

}
