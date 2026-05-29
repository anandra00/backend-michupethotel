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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('cat_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('badge')->nullable(); // e.g. "Lahap", "Normal"
            $table->string('badge_bg')->nullable(); // e.g. "bg-[#4ADE80]"
            $table->string('icon_type')->default('CheckCircle2'); // To store which lucide icon to use
            $table->string('photo_path')->nullable();
            $table->string('time'); // e.g. "12:30 PM"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
