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
        Schema::create('babel_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('babelfee_token', 32);
            $table->string('policy_id', 56);
            $table->string('fingerprint', 64);
            $table->decimal('rate', total: 8, places: 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('babel_fees');
    }
};
