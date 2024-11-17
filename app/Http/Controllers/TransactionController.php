<?php

namespace App\Http\Controllers;

use App\Models\Contact;
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

        $sender = auth()->user(); // Utilisateur connecté
        $receiver = User::where('phone_number', $request->receiver_phone_number)->first();

        // Vérifier si le destinataire est déjà dans les contacts de l'utilisateur
        $isContact = Contact::where('user_id', $sender->id)
                            ->where('contact_user_id', $receiver->id)
                            ->exists();

        if (!$isContact) {
            // Ajouter le destinataire en tant que contact si inexistant
            $this->addContact($sender, $receiver);
        }

        // Vérifier que le solde de l'expéditeur est suffisant
        if ($sender->balance < $request->amount) {
            return response()->json([
                'error' => 'Solde insuffisant pour effectuer le transfert'
            ], 403);
        }

        try {
            DB::transaction(function () use ($sender, $receiver, $request) {
                // Déduire le montant du solde de l'expéditeur
                $sender->balance -= $request->amount;
                $sender->save();

                // Ajouter le montant au solde du destinataire
                $receiver->balance += $request->amount;
                $receiver->save();

                // Enregistrer la transaction
                Transaction::create([
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'amount' => $request->amount,
                    'type' => 'transfer',
                ]);
            });

            return response()->json(['message' => 'Transfert réussi'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du transfert.'], 500);
        }
    }

    /**
     * Ajoute un contact à l'utilisateur.
     */
    private function addContact($sender, $receiver)
    {
        Contact::create([
            'user_id' => $sender->id,
            'contact_user_id' => $receiver->id,
            'name' => $receiver->first_name . ' ' . $receiver->last_name, // Ou autre logique pour nommer le contact
        ]);
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
        'transfers' => 'required|array|min:1', // Liste des transferts
        'transfers.*.receiver_phone_number' => 'required|exists:users,phone_number', // Vérification des numéros de téléphone
        'transfers.*.amount' => 'required|numeric|min:1', // Validation des montants
        'scheduled_at' => 'required|date|after:now', // Date future obligatoire
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 400);
    }

    $sender = auth()->user();

    try {
        DB::transaction(function () use ($sender, $request) {
            foreach ($request->transfers as $transfer) {
                $receiver = User::where('phone_number', $transfer['receiver_phone_number'])->first();

                // Vérification : ne pas se transférer à soi-même
                if ($sender->id === $receiver->id) {
                    throw new \Exception("Vous ne pouvez pas programmer un transfert à vous-même.");
                }

                // Vérification : solde suffisant
                if ($sender->balance < $transfer['amount']) {
                    throw new \Exception("Solde insuffisant pour programmer le transfert de {$transfer['amount']} à {$receiver->phone_number}.");
                }

                // Création de la transaction programmée
                ScheduledTransaction::create([
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'amount' => $transfer['amount'],
                    'scheduled_at' => $request->scheduled_at,
                    'status' => 'pending', // Statut initial
                ]);
            }
        });

        return response()->json(['message' => 'Transactions programmées avec succès.'], 201);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
}





    public function executeScheduledTransactions()
    {
        $now = now();
    
        // Récupérer toutes les transactions programmées en attente
        $scheduledTransactions = ScheduledTransaction::where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->lockForUpdate() // Verrouiller pour éviter les accès simultanés
            ->get();
    
        foreach ($scheduledTransactions as $transaction) {
            $sender = User::find($transaction->sender_id);
            $receiver = User::find($transaction->receiver_id);
    
            // Vérification : solde suffisant
            if ($sender->balance < $transaction->amount) {
                $transaction->update(['status' => 'failed']);
                continue;
            }
    
            try {
                DB::transaction(function () use ($transaction, $sender, $receiver) {
                    // Mise à jour des soldes
                    $sender->balance -= $transaction->amount;
                    $sender->save();
    
                    $receiver->balance += $transaction->amount;
                    $receiver->save();
    
                    // Création d'une seule transaction
                    Transaction::create([
                        'type' => 'transfer',
                        'sender_id' => $transaction->sender_id,
                        'receiver_id' => $transaction->receiver_id,
                        'amount' => $transaction->amount,
                        'direction' => 'sent',
                    ]);
    
                    // Mise à jour rapide du statut
                    $transaction->update(['status' => 'completed']);
                });
            } catch (\Exception $e) {
                // Gestion des erreurs
                $transaction->update(['status' => 'failed']);
            }
        }
    
        return response()->json(['message' => 'Transactions programmées exécutées.']);
    }



    public function cancelScheduledTransaction(Request $request)
{
    $validator = Validator::make($request->all(), [
        'transaction_id' => 'required|exists:scheduled_transactions,id',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 400);
    }

    $user = auth()->user();
    $transaction = ScheduledTransaction::where('id', $request->transaction_id)
        ->where('sender_id', $user->id)
        ->first();

    if (!$transaction) {
        return response()->json(['error' => 'Transaction non trouvée ou non autorisée'], 404);
    }

    if ($transaction->status !== 'pending') {
        return response()->json(['error' => 'Seules les transactions en attente peuvent être annulées'], 400);
    }

    try {
        $transaction->update(['status' => 'canceled']);
        return response()->json(['message' => 'Transaction programmée annulée avec succès'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Erreur lors de l\'annulation de la transaction'], 500);
    }
}

    



public function listScheduledTransactions(Request $request)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json(['error' => 'Utilisateur non authentifié.'], 401);
    }

    // Paramètres de pagination
    $page = $request->query('page', 1); // Par défaut : page 1
    $pageSize = $request->query('page_size', 10); // Par défaut : 10 éléments par page

    // Récupérer les transactions programmées
    $query = ScheduledTransaction::where('sender_id', $user->id)
        ->orderBy('scheduled_at', 'asc');

    $totalTransactions = $query->count();

    $transactions = $query
        ->skip(($page - 1) * $pageSize)
        ->take($pageSize)
        ->get()
        ->map(function ($transaction) {
            // Ensure scheduled_at is parsed as DateTime before formatting
            $scheduledAt = $transaction->scheduled_at instanceof \DateTime
                ? $transaction->scheduled_at
                : new \DateTime($transaction->scheduled_at);

            return [
                'transaction_id' => $transaction->id,
                'receiver_phone_number' => optional($transaction->receiver)->phone_number,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'scheduled_at' => $scheduledAt->format('d M Y H:i'),
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





}
