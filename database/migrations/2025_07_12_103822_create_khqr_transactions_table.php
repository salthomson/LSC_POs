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
        Schema::create('khqr_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('cascade'); // Link to your existing sales table
            $table->string('khqr_string')->nullable(); // The generated KHQR payload
            $table->decimal('amount', 10, 2);
            $table->string('currency_code', 3); // KHR, USD
            $table->string('reference_number')->unique(); // Your internal transaction ID
            $table->string('bank_transaction_id')->nullable()->unique(); // ID from the bank/PSP
            $table->string('status')->default('pending'); // pending, completed, failed, cancelled
            $table->text('response_data')->nullable(); // Store raw response from bank/PSP
            $table->timestamp('expires_at')->nullable(); // If QR has an expiry
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('khqr_transactions');
    }
};