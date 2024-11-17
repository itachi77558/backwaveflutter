<?php

namespace App\Http\Controllers;

use App\Models\ScheduledTransaction;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Transférer des fonds entre utilisateurs.
     */
    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_phone_number' => 'required|exists:users,phone_number',
            'amount' => 'required|numeric|min:1',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
    
        $sender = auth()->user();
        $receiver = User::where('phone_number', $request->receiver_phone_number)->first();
    
        if ($sender->phone_number === $receiver->phone_number) {
            return response()->json(['error' => 'Vous ne pouvez pas transférer de l\'argent à vous-même.'], 400);
        }
    
        if ($sender->balance < $request->amount) {
            return response()->json(['error' => 'Solde insuffisant pour effectuer ce transfert.'], 400);
        }
    
        try {
            DB::transaction(function () use ($sender, $receiver, $request) {
                $sender->balance -= $request->amount;
                $sender->save();
    
                $receiver->balance += $request->amount;
                $receiver->save();
    
                // Une seule transaction créée
                Transaction::create([
                    'type' => 'transfer',
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'amount' => $request->amount,
                    'direction' => 'sent', // ou utilisez "transfer" si 'direction' n'est pas nécessaire
                ]);
            });
    
            return response()->json(['message' => 'Transfert effectué avec succès.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du transfert.'], 500);
        }
    }
    


    /**
     * Effectuer plusieurs transferts à la fois.
     */
    public function multipleTransfer(Request $request)
{
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

    if ($sender->balance < $totalAmount) {
        return response()->json(['error' => 'Solde insuffisant pour effectuer tous les transferts.'], 400);
    }

    try {
        DB::transaction(function () use ($sender, $request) {
            foreach ($request->transfers as $transfer) {
                $receiver = User::where('phone_number', $transfer['receiver_phone'])->first();

                $sender->balance -= $transfer['amount'];
                $sender->save();

                $receiver->balance += $transfer['amount'];
                $receiver->save();

                // Une seule transaction créée
                Transaction::create([
                    'type' => 'transfer',
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'amount' => $transfer['amount'],
                    'direction' => 'sent',
                ]);
            }
        });

        return response()->json(['message' => 'Transferts multiples effectués avec succès.'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Une erreur est survenue lors des transferts.'], 500);
    }
}


    /**
     * Annuler une transaction si elle respecte les critères d'annulation.
     */
    public function cancelTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $transaction = Transaction::findOrFail($request->transaction_id);
        $currentUser = auth()->user();

        // Vérification : seul l'expéditeur peut annuler la transaction
        if ($transaction->sender_id !== $currentUser->id) {
            return response()->json(['error' => 'Vous n\'avez pas l\'autorisation d\'annuler cette transaction.'], 403);
        }

        // Vérification : transaction déjà annulée
        if ($transaction->canceled_at) {
            return response()->json(['error' => 'Cette transaction a déjà été annulée.'], 400);
        }

        // Vérification : annulation dans les 30 minutes
        if (now()->diffInMinutes($transaction->created_at) > 30) {
            return response()->json(['error' => 'Annulation impossible après 30 minutes.'], 400);
        }

        $sender = User::find($transaction->sender_id);
        $receiver = User::find($transaction->receiver_id);

        DB::transaction(function () use ($transaction, $sender, $receiver) {
            // Remboursement de l'expéditeur
            $sender->balance += $transaction->amount;
            $sender->save();

            // Déduction chez le destinataire
            $receiver->balance -= $transaction->amount;
            $receiver->save();

            // Marquer la transaction comme annulée
            $transaction->canceled_at = now();
            $transaction->save();
        });

        return response()->json(['message' => 'Transaction annulée avec succès.'], 200);
    }

    /**
     * Lister toutes les transactions pour l'utilisateur authentifié.
     */
    public function listTransactions(Request $request)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json(['error' => 'Utilisateur non authentifié.'], 401);
    }

    // Get pagination parameters
    $page = $request->query('page', 1); // Default to page 1
    $pageSize = $request->query('page_size', 5); // Default to 5 items per page

    // Fetch paginated transactions
    $query = Transaction::where('sender_id', $user->id)
        ->orWhere('receiver_id', $user->id)
        ->orderBy('created_at', 'desc');

    $totalTransactions = $query->count();
    $transactions = $query
        ->skip(($page - 1) * $pageSize)
        ->take($pageSize)
        ->get()
        ->map(function ($transaction) use ($user) {
            $isSent = $transaction->sender_id === $user->id;
            $otherUser = User::find($isSent ? $transaction->receiver_id : $transaction->sender_id);

            return [
                'transaction_id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'direction' => $isSent ? 'sent' : 'received',
                'other_party' => [
                    'name' => optional($otherUser)->first_name . ' ' . optional($otherUser)->last_name,
                    'phone_number' => optional($otherUser)->phone_number,
                ],
                'date' => $transaction->created_at->format('d M Y H:i'),
                'status' => $transaction->canceled_at ? 'canceled' : 'completed',
                'cancelable' => $isSent && !$transaction->canceled_at && now()->diffInMinutes($transaction->created_at) <= 30,
            ];
        });

    return response()->json([
        'transactions' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'page_size' => $pageSize,
            'total_transactions' => $totalTransactions,
            'total_pages' => ceil($totalTransactions / $pageSize),
        ],
    ], 200);
}

public function scheduleTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_phone_number' => 'required|exists:users,phone_number',
            'amount' => 'required|numeric|min:1',
            'scheduled_at' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $sender = auth()->user();
        $receiver = User::where('phone_number', $request->receiver_phone_number)->first();

        if ($sender->balance < $request->amount) {
            return response()->json(['error' => 'Solde insuffisant pour programmer ce transfert.'], 400);
        }

        ScheduledTransaction::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => $request->amount,
            'scheduled_at' => $request->scheduled_at,
        ]);

        return response()->json(['message' => 'Transaction programmée avec succès.'], 201);
    }



    public function executeScheduledTransactions()
{
    $now = now();

    // Récupérer toutes les transactions programmées en attente
    $scheduledTransactions = ScheduledTransaction::where('status', 'pending')
        ->where('scheduled_at', '<=', $now)
        ->get();

    foreach ($scheduledTransactions as $transaction) {
        $sender = User::find($transaction->sender_id);
        $receiver = User::find($transaction->receiver_id);

        // Vérification : solde suffisant
        if ($sender->balance < $transaction->amount) {
            $transaction->update(['status' => 'failed']);
            continue;
        }

        DB::transaction(function () use ($transaction, $sender, $receiver) {
            // Mise à jour des soldes
            $sender->balance -= $transaction->amount;
            $sender->save();

            $receiver->balance += $transaction->amount;
            $receiver->save();

            // Création des transactions
            Transaction::create([
                'type' => 'transfer',
                'sender_id' => $transaction->sender_id,
                'receiver_id' => $transaction->receiver_id,
                'amount' => $transaction->amount,
                'direction' => 'sent',
            ]);

            Transaction::create([
                'type' => 'transfer',
                'sender_id' => $transaction->receiver_id,
                'receiver_id' => $transaction->sender_id,
                'amount' => $transaction->amount,
                'direction' => 'received',
            ]);

            // Marquer comme terminée
            $transaction->update(['status' => 'completed']);
        });
    }

    return response()->json(['message' => 'Transactions programmées exécutées.']);
}



public function listScheduledTransactions()
{
    $user = auth()->user();

    $transactions = ScheduledTransaction::where('sender_id', $user->id)
        ->orderBy('scheduled_at', 'asc')
        ->get();

    return response()->json($transactions, 200);
}


}
