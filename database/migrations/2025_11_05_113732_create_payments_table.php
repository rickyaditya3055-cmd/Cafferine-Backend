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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('order_id');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('qris');
            $table->enum('status', ['pending', 'success', 'failed', 'expired'])->default('pending');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('description')->nullable();
            $table->text('qr_string')->nullable();
            $table->string('gateway_ref')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('order_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};