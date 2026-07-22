<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;

class RepairMigrationStateCommand extends Command
{
    protected $signature = 'migrate:repair
                            {--force : Run without confirmation in production}';

    protected $description = 'Mark migrations as applied when their schema already exists (production recovery)';

    public function handle(Migrator $migrator): int
    {
        if (! $this->option('force') && $this->laravel->environment('production')) {
            if (! $this->confirm('Repair migration history in production?')) {
                return self::FAILURE;
            }
        }

        $repository = $migrator->getRepository();
        $files = $migrator->getMigrationFiles([$this->laravel->databasePath('migrations')]);
        $ran = $repository->getRan();
        $batch = $repository->getNextBatchNumber();
        $repaired = [];

        foreach ($files as $migration => $path) {
            if (in_array($migration, $ran, true)) {
                continue;
            }

            $checker = $this->migrationChecks()[$migration] ?? null;

            if ($checker === null || ! $checker()) {
                continue;
            }

            $repository->log($migration, $batch);
            $repaired[] = $migration;
        }

        if ($repaired === []) {
            $this->components->info('No migration history repairs were needed.');

            return self::SUCCESS;
        }

        $this->components->info('Marked existing schema migrations as applied:');
        foreach ($repaired as $migration) {
            $this->line("  - {$migration}");
        }

        $this->newLine();
        $this->components->warn('Next run: php artisan migrate --force && php artisan db:seed --force');

        return self::SUCCESS;
    }

    /**
     * @return array<string, callable(): bool>
     */
    private function migrationChecks(): array
    {
        return [
            '0001_01_01_000000_create_users_table' => fn (): bool => Schema::hasTable('users')
                && Schema::hasTable('password_reset_tokens')
                && Schema::hasTable('sessions'),
            '0001_01_01_000001_create_cache_table' => fn (): bool => Schema::hasTable('cache')
                && Schema::hasTable('cache_locks'),
            '0001_01_01_000002_create_jobs_table' => fn (): bool => Schema::hasTable('jobs')
                && Schema::hasTable('job_batches')
                && Schema::hasTable('failed_jobs'),
            '2026_05_25_081608_create_roles_table' => fn (): bool => Schema::hasTable('roles'),
            '2026_05_25_081608_create_school_classes_table' => fn (): bool => Schema::hasTable('classes'),
            '2026_05_25_081608_create_user_identities_table' => fn (): bool => Schema::hasTable('user_identities'),
            '2026_05_25_081611_create_subjects_table' => fn (): bool => Schema::hasTable('subjects'),
            '2026_05_25_081612_create_students_table' => fn (): bool => Schema::hasTable('students'),
            '2026_05_25_081613_create_teacher_subjects_table' => fn (): bool => Schema::hasTable('teacher_subjects'),
            '2026_05_25_081614_create_attendance_sessions_table' => fn (): bool => Schema::hasTable('attendance_sessions'),
            '2026_05_25_081615_create_attendance_records_table' => fn (): bool => Schema::hasTable('attendance_records'),
            '2026_05_25_081616_create_audit_logs_table' => fn (): bool => Schema::hasTable('audit_logs'),
            '2026_05_25_081617_create_dynamics_syncs_table' => fn (): bool => Schema::hasTable('dynamics_syncs'),
            '2026_05_25_081618_create_notifications_table' => fn (): bool => Schema::hasTable('notifications'),
            '2026_05_25_081619_create_refresh_tokens_table' => fn (): bool => Schema::hasTable('refresh_tokens'),
            '2026_05_25_081610_add_role_foreign_key_to_users_table' => fn (): bool => Schema::hasTable('users')
                && Schema::hasTable('roles')
                && Schema::hasColumn('users', 'role_id'),
            '2026_05_28_100000_create_schools_table' => fn (): bool => Schema::hasTable('schools'),
            '2026_05_28_100100_add_school_and_grade_to_classes_table' => fn (): bool => Schema::hasTable('classes')
                && Schema::hasColumn('classes', 'school_id')
                && Schema::hasColumn('classes', 'grade_level'),
            '2026_05_28_100200_create_school_user_table' => fn (): bool => Schema::hasTable('school_user'),
            '2026_07_10_100100_create_weekly_duty_rosters_table' => fn (): bool => Schema::hasTable('weekly_duty_rosters'),
            '2026_07_10_100300_restructure_weekly_duty_rosters' => fn (): bool => Schema::hasTable('weekly_duty_roster_entries')
                && Schema::hasTable('weekly_duty_roster_entry_user'),
            '2026_07_14_160000_ensure_weekly_duty_roster_week_end' => fn (): bool => Schema::hasTable('weekly_duty_rosters')
                && Schema::hasColumn('weekly_duty_rosters', 'week_end'),
            '2026_07_22_120000_add_status_to_weekly_duty_rosters' => fn (): bool => Schema::hasTable('weekly_duty_rosters')
                && Schema::hasColumn('weekly_duty_rosters', 'status')
                && Schema::hasColumn('weekly_duty_rosters', 'published_at'),
            '2026_07_22_130000_add_published_by_to_weekly_duty_rosters' => fn (): bool => Schema::hasTable('weekly_duty_rosters')
                && Schema::hasColumn('weekly_duty_rosters', 'published_by'),
            '2026_07_22_140000_create_school_duty_roster_template_entries_table' => fn (): bool => Schema::hasTable('school_duty_roster_template_entries'),
        ];
    }
}
