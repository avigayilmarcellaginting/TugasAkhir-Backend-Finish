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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('table_number')->nullable()->after('customer_name');
            $table->string('customer_phone')->nullable()->after('table_number');
            $table->enum('dining_option', ['dine_in', 'take_away'])->default('dine_in')->after('customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['table_number', 'customer_phone', 'dining_option']);
        });
    }
};
