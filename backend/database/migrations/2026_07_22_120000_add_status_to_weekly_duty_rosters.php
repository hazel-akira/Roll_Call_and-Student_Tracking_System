<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_duty_rosters')) {
            return;
        }

        $addedStatus = false;

        if (! Schema::hasColumn('weekly_duty_rosters', 'status')) {
            Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
                $table->string('status', 20)->default('draft')->after('week_end');
            });
            $addedStatus = true;
        }

        if (! Schema::hasColumn('weekly_duty_rosters', 'published_at')) {
            Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
                $after = Schema::hasColumn('weekly_duty_rosters', 'status') ? 'status' : 'week_end';
                $table->timestamp('published_at')->nullable()->after($after);
            });
        }

        // First-time introduction of status: existing rosters were already live for reports.
        if ($addedStatus) {
            DB::table('weekly_duty_rosters')->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_duty_rosters')) {
            return;
        }

        $drop = array_values(array_filter([
            Schema::hasColumn('weekly_duty_rosters', 'published_at') ? 'published_at' : null,
            Schema::hasColumn('weekly_duty_rosters', 'status') ? 'status' : null,
        ]));

        if ($drop !== []) {
            Schema::table('weekly_duty_rosters', function (Blueprint $table) use ($drop): void {
                $table->dropColumn($drop);
            });
        }
    }
};
