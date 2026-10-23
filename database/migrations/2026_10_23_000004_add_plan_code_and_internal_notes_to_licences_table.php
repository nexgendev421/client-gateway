<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licences', function (Blueprint $table): void {
            $table->string('plan_code', 32)->nullable()->after('plan');
            $table->text('internal_notes')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('licences', function (Blueprint $table): void {
            $table->dropColumn(['plan_code', 'internal_notes']);
        });
    }
};
