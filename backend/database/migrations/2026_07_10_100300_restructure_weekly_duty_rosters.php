<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_duty_roster_entries')) {
            Schema::create('weekly_duty_roster_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('weekly_duty_roster_id')->constrained()->cascadeOnDelete();
                $table->string('category');
                $table->string('location')->nullable();
                $table->string('time_slot')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['weekly_duty_roster_id', 'category']);
            });
        }

        if (! Schema::hasTable('weekly_duty_roster_entry_user')) {
            Schema::create('weekly_duty_roster_entry_user', function (Blueprint $table): void {
                $table->foreignId('weekly_duty_roster_entry_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();

                $table->primary(['weekly_duty_roster_entry_id', 'user_id'], 'weekly_duty_entry_user_primary');
            });
        }

        if (Schema::hasTable('weekly_duty_rosters') && ! Schema::hasColumn('weekly_duty_rosters', 'week_end')) {
            Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
                $table->date('week_end')->nullable()->after('week_start');
            });
        }

        if (! Schema::hasColumn('weekly_duty_rosters', 'user_id')) {
            return;
        }

        $legacyRows = DB::table('weekly_duty_rosters')
            ->whereNotNull('user_id')
            ->get(['id', 'user_id']);

        foreach ($legacyRows as $row) {
            $hasEntries = DB::table('weekly_duty_roster_entries')
                ->where('weekly_duty_roster_id', $row->id)
                ->exists();

            if ($hasEntries) {
                continue;
            }

            $entryId = DB::table('weekly_duty_roster_entries')->insertGetId([
                'weekly_duty_roster_id' => $row->id,
                'category' => 'general',
                'location' => null,
                'time_slot' => null,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('weekly_duty_roster_entry_user')->insert([
                'weekly_duty_roster_entry_id' => $entryId,
                'user_id' => $row->user_id,
            ]);
        }

        try {
            Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
                $table->dropUnique(['school_id', 'week_start', 'slot']);
            });
        } catch (\Throwable) {
            // Unique index may already be gone on partially migrated DBs.
        }

        try {
            Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('user_id');
            });
        } catch (\Throwable) {
            // Column/FK may already be gone.
        }

        $drop = array_values(array_filter([
            Schema::hasColumn('weekly_duty_rosters', 'slot') ? 'slot' : null,
            Schema::hasColumn('weekly_duty_rosters', 'notes') ? 'notes' : null,
        ]));

        if ($drop !== []) {
            Schema::table('weekly_duty_rosters', function (Blueprint $table) use ($drop): void {
                $table->dropColumn($drop);
            });
        }
    }

    public function down(): void
    {
        Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
            if (! Schema::hasColumn('weekly_duty_rosters', 'slot')) {
                $table->unsignedTinyInteger('slot')->default(1);
            }
            if (! Schema::hasColumn('weekly_duty_rosters', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('weekly_duty_rosters', 'notes')) {
                $table->text('notes')->nullable();
            }
        });

        try {
            Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
                $table->unique(['school_id', 'week_start', 'slot']);
            });
        } catch (\Throwable) {
            // Ignore if unique already exists.
        }

        if (Schema::hasColumn('weekly_duty_rosters', 'week_end')) {
            Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
                $table->dropColumn('week_end');
            });
        }

        Schema::dropIfExists('weekly_duty_roster_entry_user');
        Schema::dropIfExists('weekly_duty_roster_entries');
    }
};
