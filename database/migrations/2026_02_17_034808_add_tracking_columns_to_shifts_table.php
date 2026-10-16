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
        Schema::table('shifts', function (Blueprint $table) {
            $table->time('scheduled_start')->nullable()->after('end_time');
            $table->time('scheduled_end')->nullable()->after('scheduled_start');
            $table->integer('lateness_minutes')->default(0)->after('scheduled_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['scheduled_start', 'scheduled_end', 'lateness_minutes']);
        });
    }
};
