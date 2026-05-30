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
            // Drop old foreign key constraint if it exists (cascade on delete)
            // We wrap it in a try-catch or safe check since for fresh installations using the modified original migration,
            // the constraint is already nullOnDelete.
            try {
                $table->dropForeign(['room_id']);
            } catch (\Exception $e) {
                // If it fails (e.g. SQLite in tests, or already dropped), we ignore and proceed
            }

            // Re-add the foreign key constraint with nullOnDelete
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            try {
                $table->dropForeign(['room_id']);
            } catch (\Exception $e) {
                // Ignore
            }

            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->cascadeOnDelete();
        });
    }
};
