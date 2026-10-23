<?php

use App\Enums\LicenceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->restrictOnDelete();
            $table->string('plan');
            $table->string('status')->default(LicenceStatus::Trial->value)->index();
            $table->dateTime('starts_at');
            $table->dateTime('valid_until')->index();
            $table->dateTime('grace_until')->nullable();
            $table->unsignedSmallInteger('max_stores')->nullable();
            $table->unsignedSmallInteger('max_devices')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licences');
    }
};
