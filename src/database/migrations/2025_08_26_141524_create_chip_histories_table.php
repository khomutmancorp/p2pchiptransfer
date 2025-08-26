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
        Schema::create('chip_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('transaction_id');
            $table->enum('type', ['credit', 'debit']);
            $table->integer('amount');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['transaction_id']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chip_histories');
    }
};
