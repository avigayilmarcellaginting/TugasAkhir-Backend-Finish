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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Cashier
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('transaction_code')->unique(); // e.g., TRX-timestamp-rand
            $table->decimal('total_amount', 12, 2);
            $table->decimal('cash_amount', 12, 2)->nullable(); // For cash payments
            $table->decimal('change_amount', 12, 2)->nullable(); // For cash payments
            $table->string('payment_method')->default('cash'); // cash, qris, debit, credit
            $table->string('payment_status')->default('pending'); // pending, paid, failed, void
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('price', 12, 2); // Price at the time of transaction
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
        Schema::dropIfExists('transactions');
    }
};
