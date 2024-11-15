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

        $sender = auth()->user(); // Authenticated user is the sender
        $receiver = User::where('phone_number', $request->receiver_phone_number)->first();

        // Prevent transferring to oneself
        if ($sender->id === $receiver->id) {
            return response()->json(['error' => 'You cannot transfer money to yourself'], 400);
        }

        // Check if the sender has sufficient balance
        if ($sender->balance < $request->amount) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        DB::transaction(function () use ($sender, $receiver, $request) {
            // Deduct amount from sender and add to receiver
            $sender->balance -= $request->amount;
            $sender->save();

            $receiver->balance += $request->amount;
            $receiver->save();

            // Record "sent" transaction for the sender
            Transaction::create([
                'type' => 'transfer',
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $request->amount,
                'direction' => 'sent', // Indicates the sender side of the transaction
            ]);

            // Record "received" transaction for the receiver
            Transaction::create([
                'type' => 'transfer',
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $request->amount,
                'direction' => 'received', // Indicates the receiver side of the transaction
            ]);
        });

        return response()->json(['message' => 'Transfer successful'], 200);
    }


    // In TransactionController.php

    public function multipleTransfer(Request $request)
    {
        // Validate each transfer in the list
        $validator = Validator::make($request->all(), [
            'transfers' => 'required|array|min:1',
            'transfers.*.receiver_phone' => 'required|exists:users,phone_number',
            'transfers.*.amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $sender = auth()->user();
        $totalAmount = array_sum(array_column($request->transfers, 'amount'));

        // Check if the sender has enough balance for all transfers
        if ($sender->balance < $totalAmount) {
            return response()->json(['error' => 'Insufficient balance for multiple transfers'], 400);
        }

        // Ensure no self-transfers and no duplicate recipients
        $recipientPhones = array_column($request->transfers, 'receiver_phone');
        if (in_array($sender->phone_number, $recipientPhones)) {
            return response()->json(['error' => 'You cannot transfer money to yourself'], 400);
        }
        if (count($recipientPhones) !== count(array_unique($recipientPhones))) {
            return response()->json(['error' => 'Duplicate transfers to the same recipient are not allowed'], 400);
        }

        DB::transaction(function () use ($sender, $request) {
            foreach ($request->transfers as $transfer) {
                $receiver = User::where('phone_number', $transfer['receiver_phone'])->first();

                // Deduct amount from sender and add to receiver
                $sender->balance -= $transfer['amount'];
                $sender->save();

                $receiver->balance += $transfer['amount'];
                $receiver->save();

                // Record "sent" transaction for the sender
                Transaction::create([
                    'type' => 'transfer',
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'amount' => $transfer['amount'],
                    'direction' => 'sent',
                ]);

                // Record "received" transaction for the receiver
                Transaction::create([
                    'type' => 'transfer',
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'amount' => $transfer['amount'],
                    'direction' => 'received',
                ]);
            }
        });

        return response()->json(['message' => 'Multiple transfers successful'], 200);
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



    public function listTransactions(Request $request)
{
    // Récupérer l'utilisateur authentifié
    $user = auth()->user();

    // Vérifier si l'utilisateur est authentifié
    if (!$user) {
        return response()->json(['error' => 'Utilisateur non authentifié'], 401);
    }

    // Récupérer les transactions où l'utilisateur est soit l'expéditeur, soit le destinataire
    $transactions = Transaction::where('sender_id', $user->id)
                        ->orWhere('receiver_id', $user->id)
                        ->orderBy('created_at', 'desc')
                        ->get()
                        ->map(function ($transaction) use ($user) {
                            // Déterminer la direction de la transaction
                            $isSent = $transaction->sender_id === $user->id;
                            $otherUserId = $isSent ? $transaction->receiver_id : $transaction->sender_id;
                            $otherUser = User::find($otherUserId);

                            return [
                                'type' => $transaction->type,
                                'amount' => $transaction->amount,
                                'direction' => $isSent ? 'sent' : 'received',
                                'other_party' => [
                                    'phone_number' => optional($otherUser)->phone_number ?? 'Externe',
                                    'name' => optional($otherUser)->first_name . ' ' . optional($otherUser)->last_name ?? 'Externe'
                                ],
                                'date' => $transaction->created_at->format('d M Y'),
                            ];
                        });

    // Vérifier si des transactions ont été trouvées
    if ($transactions->isEmpty()) {
        return response()->json(['message' => 'Aucune transaction trouvée'], 200);
    }

    return response()->json(['transactions' => $transactions], 200);
}


public function cancelTransaction(Request $request)
{
    $validator = Validator::make($request->all(), [
        'transaction_id' => 'required|exists:transactions,id',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 400);
    }

    $transaction = Transaction::find($request->transaction_id);

    $currentUser = auth()->user();

    // Vérification : Seul l'expéditeur peut annuler la transaction
    if ($transaction->sender_id !== $currentUser->id) {
        return response()->json(['error' => 'Vous n\'avez pas l\'autorisation d\'annuler cette transaction'], 403);
    }

    // Vérifier si la transaction a déjà été annulée
    if ($transaction->canceled_at) {
        return response()->json(['error' => 'Transaction déjà annulée'], 400);
    }

    // Vérification : Annulation possible uniquement dans les 30 minutes
    if (now()->diffInMinutes($transaction->created_at) > 30) {
        return response()->json(['error' => 'Annulation impossible après 30 minutes'], 400);
    }

    $sender = User::find($transaction->sender_id);
    $receiver = User::find($transaction->receiver_id);

    DB::transaction(function () use ($transaction, $sender, $receiver) {
        // Rembourser l'expéditeur
        $sender->balance += $transaction->amount;
        $sender->save();

        // Réduire le montant du destinataire
        $receiver->balance -= $transaction->amount;
        $receiver->save();

        // Marquer la transaction comme annulée
        $transaction->canceled_at = now();
        $transaction->save();
    });

    return response()->json(['message' => 'Transaction annulée avec succès'], 200);
}








}
