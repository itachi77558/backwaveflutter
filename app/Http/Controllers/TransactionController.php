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
            'sender_id' => 'required|exists:users,id',
            'receiver_phone' => 'required|exists:users,phone_number',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $sender = User::find($request->sender_id);
        $receiver = User::where('phone_number', $request->receiver_phone)->first();

        if ($sender->balance < $request->amount) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        DB::transaction(function () use ($sender, $receiver, $request) {
            $sender->balance -= $request->amount;
            $sender->save();

            $receiver->balance += $request->amount;
            $receiver->save();

            Transaction::create([
                'type' => 'transfer',
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $request->amount,
            ]);
        });

        return response()->json(['message' => 'Transfer successful'], 200);
    }


    // In TransactionController.php

    public function multipleTransfer(Request $request)
    {
        // Validation de la requête
        $validator = Validator::make($request->all(), [
            'transfers' => 'required|array|min:1',
            'transfers.*.receiver_phone' => 'required|exists:users,phone_number',
            'transfers.*.amount' => 'required|numeric|min:1',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
    
        $sender = auth()->user(); // Utiliser l'utilisateur authentifié
        $totalAmount = array_sum(array_column($request->transfers, 'amount'));
    
        // Vérification du solde
        if ($sender->balance < $totalAmount) {
            return response()->json(['error' => 'Insufficient balance for multiple transfers'], 400);
        }
    
        // Traiter chaque transfert dans une transaction
        DB::transaction(function () use ($sender, $request) {
            foreach ($request->transfers as $transfer) {
                $receiver = User::where('phone_number', $transfer['receiver_phone'])->first();
    
                // Déduire du solde de l'expéditeur et ajouter au destinataire
                $sender->balance -= $transfer['amount'];
                $sender->save();
    
                $receiver->balance += $transfer['amount'];
                $receiver->save();
    
                // Enregistrer la transaction
                Transaction::create([
                    'type' => 'transfer',
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'amount' => $transfer['amount'],
                ]);
            }
        });
    
        return response()->json(['message' => 'Multiple transfer successful'], 200);
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

    // Lister toutes les transactions
    public function listTransactions(Request $request)
    {
        $userId = $request->user_id;
        $transactions = Transaction::where('sender_id', $userId)
                                   ->orWhere('receiver_id', $userId)
                                   ->get();

        return response()->json($transactions);
    }*/
}
