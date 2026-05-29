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
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('check_in');
            $table->index('check_out');
            $table->index('status');
            $table->index('booking_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['check_in']);
            $table->dropIndex(['check_out']);
            $table->dropIndex(['status']);
            $table->dropIndex(['booking_type']);
        });
    }
};
