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
    $user = auth()->user(); // Authenticated user is the one we retrieve transactions for

    // Get transactions where the user is the sender or receiver
    $transactions = Transaction::where(function ($query) use ($user) {
                                $query->where('sender_id', $user->id)
                                      ->orWhere('receiver_id', $user->id);
                            })
                            ->orderBy('created_at', 'desc')
                            ->get()
                            ->map(function ($transaction) use ($user) {
                                return [
                                    'type' => $transaction->type,
                                    'amount' => $transaction->amount,
                                    'direction' => $transaction->sender_id === $user->id ? 'sent' : 'received',
                                    'other_party' => $transaction->sender_id === $user->id 
                                                        ? User::find($transaction->receiver_id)->phone_number ?? 'External'
                                                        : User::find($transaction->sender_id)->phone_number ?? 'External',
                                    'date' => $transaction->created_at->format('d M Y'),
                                ];
                            });

    return response()->json(['transactions' => $transactions], 200);
}




}
