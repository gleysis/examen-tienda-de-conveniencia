<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each existing register keeps its totals in exactly one session: the
     * open one for live registers or the closed one for history.
     */
    public function up(): void
    {
        DB::table('cash_registers')->chunkById(100, function ($registers) {
            foreach ($registers as $register) {
                $sessionId = DB::table('cash_register_sessions')->insertGetId([
                    'cash_register_id' => $register->id,
                    'user_id' => $register->user_id,
                    'opened_by' => $register->user_id,
                    'closed_by' => $register->closed_at ? $register->user_id : null,
                    'opening_balance' => $register->opening_balance,
                    'cash_sales' => $register->cash_sales,
                    'card_sales' => $register->card_sales,
                    'transfer_sales' => $register->transfer_sales,
                    'total_sales' => $register->total_sales,
                    'expected_closing' => $register->status === 'open' ? $register->current_balance : $register->expected_closing,
                    'status' => $register->status,
                    'opened_at' => $register->opened_at ?? $register->created_at,
                    'closed_at' => $register->closed_at,
                    'notes' => $register->notes,
                    'created_at' => $register->created_at,
                    'updated_at' => $register->updated_at,
                ]);

                DB::table('sales')->where('cash_register_id', $register->id)
                    ->update(['cash_register_session_id' => $sessionId]);

                DB::table('cash_movements')->where('cash_register_id', $register->id)
                    ->update(['cash_register_session_id' => $sessionId]);

                if ($register->closed_at !== null && $register->actual_closing !== null) {
                    DB::table('cash_counts')->insert([
                        'cash_register_session_id' => $sessionId,
                        'user_id' => $register->user_id,
                        'expected_amount' => $register->expected_closing,
                        'counted_amount' => $register->actual_closing,
                        'difference' => $register->difference ?? ($register->actual_closing - $register->expected_closing),
                        'type' => 'closing',
                        'notes' => $register->notes,
                        'created_at' => $register->closed_at,
                        'updated_at' => $register->closed_at,
                    ]);
                }
            }
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn([
                'opening_balance',
                'current_balance',
                'cash_sales',
                'card_sales',
                'transfer_sales',
                'total_sales',
                'expected_closing',
                'actual_closing',
                'difference',
                'opened_at',
                'closed_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->decimal('current_balance', 10, 2)->default(0);
            $table->decimal('cash_sales', 10, 2)->default(0);
            $table->decimal('card_sales', 10, 2)->default(0);
            $table->decimal('transfer_sales', 10, 2)->default(0);
            $table->decimal('total_sales', 10, 2)->default(0);
            $table->decimal('expected_closing', 10, 2)->default(0);
            $table->decimal('actual_closing', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
        });
    }
};
