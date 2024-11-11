<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    // Transférer des fonds entre utilisateurs
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


    // Effectuer un retrait pour l'utilisateur

    /*
    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = User::find($request->user_id);

        if ($user->balance < $request->amount) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        DB::transaction(function () use ($user, $request) {
            $user->balance -= $request->amount;
            $user->save();

            Transaction::create([
                'type' => 'withdraw',
                'sender_id' => $user->id,
                'receiver_id' => null,
                'amount' => $request->amount,
            ]);
        });

        return response()->json(['message' => 'Withdrawal successful'], 200);
    }

    */

    // Lister toutes les transactions
    public function listTransactions(Request $request)
    {
        $userId = $request->user_id;
        $transactions = Transaction::where('sender_id', $userId)
                                   ->orWhere('receiver_id', $userId)
                                   ->get();

        return response()->json($transactions);
    }
}
