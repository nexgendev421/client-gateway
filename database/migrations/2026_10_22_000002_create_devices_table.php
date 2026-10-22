<?php

use App\Enums\DevicePlatform;
use App\Enums\DeviceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('platform')->default(DevicePlatform::Windows->value);
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->string('status')->default(DeviceStatus::Active->value)->index();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
