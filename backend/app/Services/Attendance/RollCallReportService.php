<?php

namespace App\Services\Attendance;

use App\Mail\RollCallReportMail;
use App\Models\AttendanceSession;
use App\Models\GradeMasterAssignment;
use App\Models\RollCallReportRecipient;
use App\Models\User;
use App\Models\WeeklyDutyRoster;
use App\Services\MicrosoftGraphMailService;
use App\Services\Reports\AttendancePdfGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class RollCallReportService
{
    public function __construct(
        private readonly AttendancePdfGenerator $pdfGenerator,
        private readonly MicrosoftGraphMailService $graphMail,
    ) {
    }

    public function generateAndSend(AttendanceSession $session): void
    {
        if (! config('reports.roll_call_report.auto_send', true)) {
            return;
        }

        $session->load([
            'classRoom.school',
            'classRoom.homeroomTeacher',
            'teacher',
            'records.student',
        ]);

        $recipients = $this->getRecipients($session);

        if ($recipients === []) {
            return;
        }

        $pdfPath = $this->pdfGenerator->generateForSession($session);
        $mailable = new RollCallReportMail($session, $pdfPath);

        if ($this->shouldUseMicrosoftGraph()) {
            $this->graphMail->sendMail(
                $recipients,
                $mailable->envelope()->subject,
                View::make('emails.roll-call-report', ['session' => $session])->render(),
                [[
                    'path' => $pdfPath,
                    'name' => basename($pdfPath),
                    'mime' => 'application/pdf',
                ]],
            );

            Log::info('Roll call report sent via Microsoft Graph', [
                'session_id' => $session->id,
                'recipients' => $recipients,
            ]);

            return;
        }

        $pendingMail = Mail::to($recipients);

        if (config('queue.default') === 'sync') {
            $pendingMail->send($mailable);
        } else {
            $pendingMail->queue($mailable);
        }

        Log::info('Roll call report email dispatched', [
            'session_id' => $session->id,
            'recipients' => $recipients,
            'transport' => config('mail.default'),
        ]);
    }

    /**
     * @return list<string>
     */
    public function getRecipients(AttendanceSession $session): array
    {
        $school = $session->classRoom?->school;
        $schoolId = $school?->id;
        $gradeLevel = $session->classRoom?->grade_level;
        $settings = $school?->rollCallSettingsOrDefault();
        $emails = [];

        $emails = array_merge($emails, $this->assignedRecipientEmails($schoolId, $gradeLevel));

        if ($settings->assigned_recipients_only) {
            if ($settings->notify_duty_roster && $schoolId && $session->session_date) {
                $emails = array_merge(
                    $emails,
                    $this->dutyRosterEmails($schoolId, $session->session_date),
                );
            }

            return $this->finalizeRecipients($emails);
        }

        if ($settings->notify_school_admins) {
            $emails = array_merge($emails, $this->schoolAdminEmails($schoolId));
        }

        $extraRecipients = config('reports.roll_call_report.extra_recipients', []);
        if (is_array($extraRecipients)) {
            $emails = array_merge($emails, $extraRecipients);
        }

        if ($settings->notify_homeroom_teacher && $session->classRoom?->homeroomTeacher?->email) {
            $emails[] = $session->classRoom->homeroomTeacher->email;
        }

        if ($settings->notify_grade_master && $gradeLevel && $schoolId) {
            $gradeMasterEmail = GradeMasterAssignment::query()
                ->where('school_id', $schoolId)
                ->where('grade_level', $gradeLevel)
                ->with('user')
                ->first()
                ?->user
                ?->email;

            if ($gradeMasterEmail) {
                $emails[] = $gradeMasterEmail;
            }
        }

        if ($settings->notify_session_teacher && $session->teacher?->email) {
            $emails[] = $session->teacher->email;
        }

        if ($settings->notify_duty_roster && $schoolId && $session->session_date) {
            $emails = array_merge(
                $emails,
                $this->dutyRosterEmails($schoolId, $session->session_date),
            );
        }

        return $this->finalizeRecipients($emails);
    }

    /**
     * @return list<string>
     */
    private function assignedRecipientEmails(?int $schoolId, ?string $gradeLevel): array
    {
        if (! $schoolId) {
            return [];
        }

        return RollCallReportRecipient::query()
            ->where('school_id', $schoolId)
            ->where('active', true)
            ->with('user')
            ->get()
            ->filter(function (RollCallReportRecipient $recipient) use ($gradeLevel): bool {
                if (! filled($recipient->grade_level)) {
                    return true;
                }

                return filled($gradeLevel) && $recipient->grade_level === $gradeLevel;
            })
            ->map(fn (RollCallReportRecipient $recipient): ?string => $recipient->resolvedEmail())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function schoolAdminEmails(?int $schoolId): array
    {
        $emails = [];

        if ($schoolId) {
            $emails = User::query()
                ->where('status', 'active')
                ->whereNotNull('email')
                ->whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'ict_staff']))
                ->whereHas('schools', fn ($query) => $query->where('schools.id', $schoolId))
                ->pluck('email')
                ->all();
        }

        if ($emails === []) {
            $emails = User::query()
                ->where('status', 'active')
                ->whereNotNull('email')
                ->whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'ict_staff']))
                ->pluck('email')
                ->all();
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    private function dutyRosterEmails(int $schoolId, \DateTimeInterface $sessionDate): array
    {
        $roster = WeeklyDutyRoster::resolveForSchoolDate(
            $schoolId,
            \Illuminate\Support\Carbon::parse($sessionDate),
        );

        return $roster?->staffEmails() ?? [];
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    private function finalizeRecipients(array $emails): array
    {
        return array_values(
            array_unique(
                array_filter($emails)
            )
        );
    }

    private function shouldUseMicrosoftGraph(): bool
    {
        if (! config('reports.roll_call_report.use_microsoft_graph', false)) {
            return false;
        }

        return $this->graphMail->isConfigured();
    }
}
