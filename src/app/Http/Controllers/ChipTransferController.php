<?php

namespace App\Http\Controllers;

use App\Models\ChipHistory;
use App\Models\ChipTransaction;
use App\Models\User;
use App\Models\UserChipBalance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ChipTransferController extends Controller
{
    public function transfer(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fromPlayerId' => 'required|integer|exists:users,id',
                'toPlayerId' => 'required|integer|exists:users,id|different:fromPlayerId',
                'amount' => 'required|integer|min:1|max:5000'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->validator->errors()
            ], 422);
        }
        

        $fromUserId = $request->input('fromPlayerId');
        $toUserId = $request->input('toPlayerId');
        $amount = $request->input('amount');
        $transactionId = uniqid('chip_transfer_');

        try {
            return DB::transaction(function () use ($fromUserId, $toUserId, $amount, $transactionId) {
                // Get or create chip balances
                $fromBalance = UserChipBalance::firstOrCreate(
                    ['user_id' => $fromUserId],
                    ['balance' => 0, 'last_updated_at' => now()]
                );
                
                $toBalance = UserChipBalance::firstOrCreate(
                    ['user_id' => $toUserId],
                    ['balance' => 0, 'last_updated_at' => now()]
                );

                // Verify sender has sufficient chips
                if ($fromBalance->balance < $amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient chip balance',
                        'data' => [
                            'current_balance' => $fromBalance->balance,
                            'requested_amount' => $amount
                        ]
                    ], 400);
                }

                // Create transaction record
                $transaction = ChipTransaction::create([
                    'transaction_id' => $transactionId,
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'amount' => $amount,
                    'status' => 'pending'
                ]);

                // Store balances before update for history
                $fromBalanceBefore = $fromBalance->balance;
                $toBalanceBefore = $toBalance->balance;

                // Update balances
                $fromBalance->balance -= $amount;
                $fromBalance->last_updated_at = now();
                $fromBalance->save();

                $toBalance->balance += $amount;
                $toBalance->last_updated_at = now();
                $toBalance->save();

                // Create history entries
                ChipHistory::create([
                    'user_id' => $fromUserId,
                    'transaction_id' => $transactionId,
                    'type' => 'debit',
                    'amount' => $amount,
                    'balance_before' => $fromBalanceBefore,
                    'balance_after' => $fromBalance->balance,
                ]);

                ChipHistory::create([
                    'user_id' => $toUserId,
                    'transaction_id' => $transactionId,
                    'type' => 'credit',
                    'amount' => $amount,
                    'balance_before' => $toBalanceBefore,
                    'balance_after' => $toBalance->balance,
                ]);

                // Mark transaction as completed
                $transaction->status = 'completed';
                $transaction->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Chip transfer completed successfully',
                    'data' => [
                        'from_user_id' => $fromUserId,
                        'to_user_id' => $toUserId,
                        'amount' => $amount,
                        'transaction_id' => $transactionId,
                        'from_balance' => $fromBalance->balance,
                        'to_balance' => $toBalance->balance
                    ]
                ]);
            });
        } catch (\Exception $e) {
            // Mark transaction as failed if it exists
            if (isset($transaction)) {
                $transaction->status = 'failed';
                $transaction->save();
            }

            return response()->json([
                'success' => false,
                'message' => 'Transfer failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBalance(int $playerId): JsonResponse
    {
        // Validate that the user exists
        $user = User::find($playerId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found'
            ], 404);
        }

        // Get chip balance, default to 0 if no record exists
        $chipBalance = UserChipBalance::where('user_id', $playerId)->first();
        $balance = $chipBalance ? $chipBalance->balance : 0;
        $lastUpdated = $chipBalance ? $chipBalance->last_updated_at : null;

        return response()->json([
            'success' => true,
            'data' => [
                'player_id' => $playerId,
                'balance' => $balance,
                'last_updated_at' => $lastUpdated
            ]
        ]);
    }
}