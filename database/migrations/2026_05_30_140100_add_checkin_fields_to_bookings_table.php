<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('checkin_lat', 10, 7)->nullable()->after('visit_time');
            $table->decimal('checkin_lng', 10, 7)->nullable()->after('checkin_lat');
            $table->boolean('checkin_verified')->default(false)->after('checkin_lng');
            $table->integer('checkin_distance_m')->nullable()->after('checkin_verified');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['checkin_lat', 'checkin_lng', 'checkin_verified', 'checkin_distance_m']);
        });
    }
};
