<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('midtrans_order_id')->nullable()->after('snap_token');
            $table->string('refund_status')->nullable()->after('payment_status'); // pending, processed, failed
            $table->decimal('refund_amount', 12, 2)->nullable()->after('refund_status');
            $table->timestamp('cancelled_at')->nullable()->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['midtrans_order_id', 'refund_status', 'refund_amount', 'cancelled_at']);
        });
    }
};
