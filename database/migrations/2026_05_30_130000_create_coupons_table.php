<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. MICHULEBARAN
            $table->enum('type', ['percentage', 'flat']); // percentage or flat amount
            $table->decimal('value', 12, 2); // 15 for 15%, or 50000 for Rp50k
            $table->decimal('min_order', 12, 2)->nullable(); // minimum order to use
            $table->decimal('max_discount', 12, 2)->nullable(); // cap for percentage discounts
            $table->integer('usage_limit')->default(0); // 0 = unlimited
            $table->integer('used_count')->default(0);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
