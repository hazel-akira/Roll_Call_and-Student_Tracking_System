<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_duty_rosters')) {
            return;
        }

        if (Schema::hasColumn('weekly_duty_rosters', 'published_by')) {
            return;
        }

        Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
            $table->foreignId('published_by')
                ->nullable()
                ->after('published_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_duty_rosters') || ! Schema::hasColumn('weekly_duty_rosters', 'published_by')) {
            return;
        }

        Schema::table('weekly_duty_rosters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('published_by');
        });
    }
};
