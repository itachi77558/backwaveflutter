<?php

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
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $from = env('TWILIO_PHONE_NUMBER');

        if (!$sid || !$token || !$from) {
            throw new \Exception('Twilio credentials are missing');
        }

        $twilio = new Client($sid, $token);
        $twilio->messages->create($phoneNumber, [
            'from' => $from,
            'body' => $message
        ]);
    }

    public function verifyCode(Request $request)
    {
        // Valider le numéro de téléphone et le code de vérification
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'verification_code' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Rechercher le code de vérification dans la base de données
        $verificationCode = VerificationCode::where('phone_number', $request->phone_number)
            ->where('code', $request->verification_code)
            ->first();

        if ($verificationCode) {
            // Marquer le numéro de téléphone comme vérifié
            $verificationCode->update(['verified' => true]);

            return response()->json(['message' => 'Verification successful'], 200);
        } else {
            return response()->json(['error' => 'Invalid verification code'], 400);
        }
    }
}
