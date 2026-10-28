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
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }
        });

        Schema::table('return_refunds', function (Blueprint $table) {
            if (Schema::hasColumn('return_refunds', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }
        });

        Schema::dropIfExists('customers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('tax_id')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->integer('loyalty_points')->default(0);
            $table->decimal('total_purchases', 10, 2)->default(0);
            $table->integer('total_visits')->default(0);
            $table->date('last_purchase_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
        });

        Schema::table('return_refunds', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
        });
    }
};
