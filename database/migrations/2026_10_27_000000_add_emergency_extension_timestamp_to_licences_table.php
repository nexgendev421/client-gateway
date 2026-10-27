<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licences', function (Blueprint $table): void {
            $table->timestamp('emergency_extended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('licences', function (Blueprint $table): void {
            $table->dropColumn('emergency_extended_at');
        });
    }
};
