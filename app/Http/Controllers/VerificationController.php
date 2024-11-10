<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Twilio\Rest\Client;

class VerificationController extends Controller
{
    public function sendVerificationCode(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone_number' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 400);
    }

    $user = User::firstOrCreate(
        ['phone_number' => $request->phone_number],
        [
            'first_name' => 'Pending', 
            'last_name' => 'User', 
            'email' => 'pending_' . time() . '@example.com' // Email temporaire
        ]
    );

    // Générer un code de vérification aléatoire
    $code = rand(100000, 999999);

    // Enregistrer ou mettre à jour le code dans la base de données
    $user->verificationCode()->updateOrCreate(
        ['user_id' => $user->id],
        ['code' => $code]
    );

    $this->sendSms($user->phone_number, "Your verification code is $code");

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
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'verification_code' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if ($user && $user->verificationCode && $user->verificationCode->code == $request->verification_code) {
            $user->update(['is_phone_verified' => true]);
            return response()->json(['message' => 'Verification successful'], 200);
        } else {
            return response()->json(['error' => 'Invalid verification code'], 400);
        }
    }
}
