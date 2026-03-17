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
        Schema::create('inbounds', function (Blueprint $table) {
            $table->id();                        
            $table->foreignId('user_id');
            $table->string('location');
            $table->string('inbound_token', 32);
            $table->string('inbound_hex', 64);
            $table->string('policy_id', 56);
            $table->string('fingerprint', 64);
            $table->unsignedInteger('cost');
            $table->unsignedInteger('decimals')->default(0);
            $table->string('hash', 64)->unique();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbounds');
    }
};
