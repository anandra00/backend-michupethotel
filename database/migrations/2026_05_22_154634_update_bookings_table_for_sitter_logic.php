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
            $table->foreignId('room_id')->nullable()->change();
            $table->string('booking_type')->default('board'); // 'board' or 'sitter'
            $table->string('sitter_package')->nullable(); // '1x' or '2x'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable(false)->change();
            $table->dropColumn('booking_type');
            $table->dropColumn('sitter_package');
        });
    }
};
