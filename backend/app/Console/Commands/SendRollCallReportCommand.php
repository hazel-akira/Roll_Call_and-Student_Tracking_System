<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Services\Attendance\RollCallReportService;
use Illuminate\Console\Command;

class SendRollCallReportCommand extends Command
{
    protected $signature = 'roll-call:send-report
                            {session : Attendance session ID}
                            {--recipients : List resolved recipients without sending}';

    protected $description = 'Generate and send the roll call report PDF for a closed attendance session';

    public function handle(RollCallReportService $reportService): int
    {
        $session = AttendanceSession::query()
            ->with(['classRoom.school', 'classRoom.homeroomTeacher', 'teacher', 'records.student'])
            ->find($this->argument('session'));

        if (! $session) {
            $this->error('Attendance session not found.');

            return self::FAILURE;
        }

        if ($session->status !== 'closed') {
            $this->warn('Session is not closed yet. Close it first, then re-run this command.');
        }

        $recipients = $reportService->getRecipients($session);

        if ($recipients === []) {
            $this->warn('No recipients resolved for this session.');

            return self::FAILURE;
        }

        $this->info('Recipients:');
        foreach ($recipients as $email) {
            $this->line("  - {$email}");
        }

        if ($this->option('recipients')) {
            return self::SUCCESS;
        }

        if (! config('reports.roll_call_report.auto_send', true)) {
            $this->warn('ROLL_CALL_REPORT_AUTO_SEND is disabled. Enable it or use this command only for dry-run with --recipients.');
        }

        $reportService->generateAndSend($session);

        $transport = config('reports.roll_call_report.use_microsoft_graph')
            && app(\App\Services\MicrosoftGraphMailService::class)->isConfigured()
            ? 'Microsoft Graph'
            : config('mail.default', 'log');

        $this->info("Roll call report dispatched via {$transport}.");

        return self::SUCCESS;
    }
}
