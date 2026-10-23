<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_token_uses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->char('jti_hash', 64)->unique();
            $table->timestamp('used_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['device_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_token_uses');
    }
};
