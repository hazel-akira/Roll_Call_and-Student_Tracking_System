<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\DynamicsService;
use Illuminate\Console\Command;

class ProbeDynamicsSchoolCommand extends Command
{
    protected $signature = 'dynamics:probe
                            {school? : Local school code (PS) or Dataverse ses_schoolname}
                            {--fresh : Request a new OAuth token}';

    protected $description = 'Test Dataverse connectivity and list class streams for a school';

    public function handle(DynamicsService $dynamics): int
    {
        $input = trim((string) $this->argument('school'));
        $schoolName = $input;

        if ($input !== '') {
            $local = School::query()->where('code', strtoupper($input))->first();
            if ($local) {
                $schoolName = $dynamics->resolveDataverseSchoolName((string) $local->id) ?? $local->name;
                $this->line("Local school: {$local->name} ({$local->code})");
            }
        }

        $probe = $dynamics->probeSchoolRooms(
            $schoolName !== '' ? $schoolName : null,
            freshToken: (bool) $this->option('fresh'),
        );

        $this->line('Enabled: '.($probe['enabled'] ? 'yes' : 'no'));
        $this->line('Token: '.($probe['token_ok'] ? 'ok' : 'failed'));
        if (! $probe['token_ok'] && $probe['token_error']) {
            $this->error($probe['token_error']);

            return self::FAILURE;
        }

        if ($schoolName !== '') {
            $this->line("Dataverse school: {$schoolName}");
            $this->line("Streams (ses_rooms): {$probe['rooms_for_school']}");
        } else {
            $this->line('Active streams (all schools): '.$probe['rooms_for_school']);
        }

        if ($probe['sample_school_names'] !== []) {
            $this->newLine();
            $this->warn('Sample ses_schoolname values in Dataverse:');
            foreach ($probe['sample_school_names'] as $name) {
                $this->line("  - {$name}");
            }
        }

        return self::SUCCESS;
    }
}
