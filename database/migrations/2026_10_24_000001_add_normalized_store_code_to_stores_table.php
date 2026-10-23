<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('store_code_normalized')->storedAs('UPPER(store_code)');
            $table->unique('store_code_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropUnique(['store_code_normalized']);
            $table->dropColumn('store_code_normalized');
        });
    }
};
