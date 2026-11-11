<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->decimal('cash_sales', 10, 2)->default(0);
            $table->decimal('card_sales', 10, 2)->default(0);
            $table->decimal('transfer_sales', 10, 2)->default(0);
            $table->decimal('total_sales', 10, 2)->default(0);
            $table->decimal('expected_closing', 10, 2)->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cash_register_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_register_session_id')->nullable()->after('cash_register_id')
                ->constrained('cash_register_sessions')->nullOnDelete();
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('cash_register_session_id')->nullable()->after('cash_register_id')
                ->constrained('cash_register_sessions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropForeign(['cash_register_session_id']);
            $table->dropColumn('cash_register_session_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['cash_register_session_id']);
            $table->dropColumn('cash_register_session_id');
        });

        Schema::dropIfExists('cash_register_sessions');
    }
};
