<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->after('week_end');
            $table->timestamp('published_at')->nullable()->after('status');
        });

        // Existing rosters were already live for reports — keep them published.
        DB::table('weekly_duty_rosters')->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
