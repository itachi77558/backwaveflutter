<?php

namespace App\Console\Commands;

use App\Models\ScheduledTransaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExecuteScheduledTransactions extends Command
{
    protected $signature = 'transactions:execute';
    protected $description = 'Exécuter les transactions programmées à leur horaire prévu.';

    public function handle()
    {
        $transactions = ScheduledTransaction::where('scheduled_at', '<=', now())
            ->where('status', 'pending')
            ->get();

        foreach ($transactions as $transaction) {
            DB::transaction(function () use ($transaction) {
                $sender = User::find($transaction->sender_id);
                $receiver = User::find($transaction->receiver_id);

                if ($sender->balance >= $transaction->amount) {
                    // Débit et crédit
                    $sender->balance -= $transaction->amount;
                    $sender->save();

                    $receiver->balance += $transaction->amount;
                    $receiver->save();

                    // Mise à jour du statut
                    $transaction->update(['status' => 'completed']);
                } else {
                    // Solde insuffisant
                    $transaction->update(['status' => 'failed']);
                }
            });
        }

        $this->info('Transactions programmées exécutées avec succès.');
    }
}
