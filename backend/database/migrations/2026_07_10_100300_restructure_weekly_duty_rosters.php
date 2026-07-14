<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('weekly_duty_roster_entry_user', function (Blueprint $table): void {
            $table->foreignId('weekly_duty_roster_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['weekly_duty_roster_entry_id', 'user_id'], 'weekly_duty_entry_user_primary');
        });

        Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
            $table->date('week_end')->nullable()->after('week_start');
        });

        if (Schema::hasColumn('weekly_duty_rosters', 'user_id')) {
            $legacyRows = DB::table('weekly_duty_rosters')
                ->whereNotNull('user_id')
                ->get(['id', 'user_id']);

            foreach ($legacyRows as $row) {
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

            Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
                $table->dropUnique(['school_id', 'week_start', 'slot']);
                $table->dropConstrainedForeignId('user_id');
                $table->dropColumn(['slot', 'notes']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
            $table->unsignedTinyInteger('slot')->default(1);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->unique(['school_id', 'week_start', 'slot']);
            $table->dropColumn('week_end');
        });

        Schema::dropIfExists('weekly_duty_roster_entry_user');
        Schema::dropIfExists('weekly_duty_roster_entries');
    }
};
