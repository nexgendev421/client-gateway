<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('health_checks')->whereIn('status', ['healthy'])->update(['status' => 'online']);
        DB::table('health_checks')->whereIn('status', ['unhealthy', 'unreachable'])->update(['status' => 'offline']);
    }

    public function down(): void
    {
        DB::table('health_checks')->where('status', 'online')->update(['status' => 'healthy']);
        DB::table('health_checks')->where('status', 'offline')->update(['status' => 'unreachable']);
    }
};
