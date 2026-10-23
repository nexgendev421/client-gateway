<?php

use App\Enums\HealthCheckStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('status')->default(HealthCheckStatus::Offline->value);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('pos_version')->nullable();
            $table->string('api_version')->nullable();
            $table->dateTime('checked_at')->index();
            $table->string('safe_error_code', 80)->nullable();
            $table->string('safe_error_message', 500)->nullable();
            $table->timestamps();
            $table->index(['store_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_checks');
    }
};
