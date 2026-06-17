<?php

namespace App\Integrations\Dynamics;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Services\DynamicsService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DynamicsAttendanceWriter
{
    public function __construct(
        private readonly DynamicsService $dynamicsService,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     attendance_id: string,
     *     attendance_code: string,
     *     roll_ids: array<int, string>,
     *     rolls_created: int
     * }
     */
    public function push(AttendanceSession $session): array
    {
        if (! $this->dynamicsService->isEnabled()) {
            throw ValidationException::withMessages([
                'dynamics' => 'Dynamics integration is not configured.',
            ]);
        }

        $session->loadMissing(['classRoom.school', 'subject', 'teacher', 'records.student']);

        $context = $this->resolveSessionContext($session);
        $attendanceCode = $this->buildAttendanceCode($session);
        $headerPayload = $this->buildAttendanceHeaderPayload($session, $context, $attendanceCode);

        $entitySet = (string) config('dynamics.attendance.header_entity', 'ses_attendances');
        $result = $this->dynamicsService->create($entitySet, $headerPayload);
        $attendanceId = $result['id'] ?? null;

        if (! $attendanceId) {
            throw ValidationException::withMessages([
                'dynamics' => 'Dataverse did not return an attendance record id.',
            ]);
        }

        $rollIds = [];
        foreach ($session->records as $record) {
            $rollId = $this->createAttendanceRoll(
                $session,
                $record,
                $attendanceId,
            );

            if ($rollId !== null) {
                $rollIds[] = $rollId;
            }
        }

        return [
            'id' => $attendanceId,
            'attendance_id' => $attendanceId,
            'attendance_code' => $attendanceCode,
            'roll_ids' => $rollIds,
            'rolls_created' => count($rollIds),
        ];
    }

    /**
     * @return array{
     *     school_name: ?string,
     *     school_dynamics_id: ?string,
     *     class_label: string,
     *     class_dynamics_id: ?string,
     *     room_id: ?string
     * }
     */
    private function resolveSessionContext(AttendanceSession $session): array
    {
        $schoolName = $this->dynamicsService->resolveDataverseSchoolName(
            (string) $session->classRoom?->school_id,
        );
        $schoolDynamicsId = $session->classRoom?->school?->dynamics_id
            ?: $this->dynamicsService->resolveSchoolDynamicsId($schoolName);

        $streamName = trim((string) ($session->classRoom?->section ?: $session->classRoom?->name ?: ''));
        $roomId = $streamName !== ''
            ? $this->dynamicsService->resolveRoomIdByName($streamName, $schoolName)
            : null;

        $classLabel = trim(implode(' ', array_filter([
            $session->classRoom?->grade_level,
            $session->classRoom?->section,
        ]))) ?: ($session->classRoom?->name ?? 'Class');

        return [
            'school_name' => $schoolName,
            'school_dynamics_id' => $schoolDynamicsId,
            'class_label' => $classLabel,
            'class_dynamics_id' => $this->dynamicsService->resolveClassDynamicsId($roomId, $classLabel),
            'room_id' => $roomId,
        ];
    }

    private function buildAttendanceCode(AttendanceSession $session): string
    {
        $prefix = (string) config('dynamics.attendance.code_prefix', 'ATD-');
        $pad = max(1, (int) config('dynamics.attendance.code_pad', 8));
        $suffix = str_pad((string) $session->id, $pad, '0', STR_PAD_LEFT);

        return Str::limit($prefix.$suffix, 100, '');
    }

    /**
     * @param  array{
     *     school_name: ?string,
     *     school_dynamics_id: ?string,
     *     class_label: string,
     *     class_dynamics_id: ?string,
     *     room_id: ?string
     * }  $context
     * @return array<string, mixed>
     */
    private function buildAttendanceHeaderPayload(
        AttendanceSession $session,
        array $context,
        string $attendanceCode,
    ): array {
        $nameColumn = (string) config('dynamics.attendance.header_name_column', 'ses_attendance');
        $dateColumn = (string) config('dynamics.attendance.header_date_column', 'ses_date');
        $classNameColumn = (string) config('dynamics.attendance.class_name_column', 'ses_classname');
        $lmsColumn = (string) config('dynamics.attendance.lms_id_column', 'ses_lmsid');
        $academicYearColumn = (string) config('dynamics.attendance.academic_year_column', 'ses_academicyear');
        $facultyNameColumn = (string) config('dynamics.attendance.faculty_name_column', 'ses_facultyname');

        $payload = [
            $nameColumn => $attendanceCode,
            $dateColumn => $session->session_date?->toDateString(),
            $classNameColumn => Str::limit($context['class_label'], 100, ''),
            $academicYearColumn => $this->resolveAcademicYear($session),
            $lmsColumn => $this->buildLmsReference($session),
            $facultyNameColumn => Str::limit((string) ($session->teacher?->name ?? ''), 100, ''),
        ];

        $this->bindLookup(
            $payload,
            (string) config('dynamics.attendance.school_lookup', 'ses_schoolid'),
            (string) config('dynamics.attendance.school_entity', 'ses_schools'),
            $context['school_dynamics_id'],
        );
        $this->bindLookup(
            $payload,
            (string) config('dynamics.attendance.class_lookup', 'ses_classid'),
            (string) config('dynamics.attendance.class_entity', 'ses_classes'),
            $context['class_dynamics_id'],
        );

        return array_filter(
            $payload,
            static fn ($value) => $value !== null && $value !== '',
        );
    }

    private function createAttendanceRoll(
        AttendanceSession $session,
        AttendanceRecord $record,
        string $attendanceId,
    ): ?string {
        $student = $record->student;
        if (! $student) {
            return null;
        }

        $studentDynamicsId = trim((string) ($student->external_reference ?? ''));
        if ($studentDynamicsId === '') {
            return null;
        }

        $entitySet = (string) config('dynamics.attendance.roll_entity', 'ses_attendancerolls');
        $nameColumn = (string) config('dynamics.attendance.roll_name_column', 'ses_attendanceroll');
        $presentColumn = (string) config('dynamics.attendance.roll_present_column', 'ses_present');
        $remarksColumn = (string) config('dynamics.attendance.roll_remarks_column', 'ses_remarks');
        $studentNameColumn = (string) config('dynamics.attendance.student_name_column', 'ses_studentname');
        $admissionColumn = (string) config('dynamics.attendance.institution_student_id_column', 'ses_institutionstudentid');
        $attendanceStatusColumn = (string) config('dynamics.attendance.roll_attendance_status_column', 'ses_attendancestatus');
        $rollStatusColumn = (string) config('dynamics.attendance.roll_line_status_column', 'ses_attendancerollstatus');

        $isPresent = $record->status === 'present';
        $studentName = trim($student->full_name) !== '' ? $student->full_name : $student->admission_number;

        $payload = [
            $nameColumn => $this->buildAttendanceRollCode($record),
            $presentColumn => $isPresent,
            $studentNameColumn => Str::limit(mb_strtoupper($studentName), 100, ''),
            $admissionColumn => Str::limit($this->formatInstitutionStudentId((string) $student->admission_number), 100, ''),
        ];

        $statusOption = $this->resolveAttendanceStatusOption($isPresent);
        if ($statusOption !== null) {
            $payload[$attendanceStatusColumn] = $statusOption;
            $payload[$rollStatusColumn] = $statusOption;
        }

        if (filled($record->remark)) {
            $payload[$remarksColumn] = Str::limit((string) $record->remark, 250, '');
        }

        if (! $isPresent && $record->marked_at) {
            $payload['ses_missedattendance'] = $record->marked_at->toIso8601String();
        }

        $this->bindLookup(
            $payload,
            (string) config('dynamics.attendance.attendance_parent_lookup', 'ses_attendanceid'),
            (string) config('dynamics.attendance.header_entity', 'ses_attendances'),
            $attendanceId,
        );
        $this->bindLookup(
            $payload,
            (string) config('dynamics.attendance.student_lookup', 'ses_studentid'),
            (string) config('dynamics.entities.student', 'ses_students'),
            $studentDynamicsId,
        );

        $result = $this->dynamicsService->create($entitySet, $payload);

        return $result['id'] ?? null;
    }

    private function buildAttendanceRollCode(AttendanceRecord $record): string
    {
        $prefix = (string) config('dynamics.attendance.roll_code_prefix', 'ATR-');
        $pad = max(1, (int) config('dynamics.attendance.roll_code_pad', 8));
        $suffix = str_pad((string) $record->id, $pad, '0', STR_PAD_LEFT);

        return Str::limit($prefix.$suffix, 100, '');
    }

    private function formatInstitutionStudentId(string $admissionNumber): string
    {
        $admissionNumber = trim($admissionNumber);
        if ($admissionNumber === '') {
            return '';
        }

        if (preg_match('/(\d+)$/', $admissionNumber, $matches) === 1) {
            return $matches[1];
        }

        return $admissionNumber;
    }

    private function resolveAttendanceStatusOption(bool $isPresent): ?int
    {
        if ($isPresent) {
            return (int) config('dynamics.attendance.status_option_present', 284210000);
        }

        $absent = config('dynamics.attendance.status_option_absent');

        return $absent !== null && $absent !== '' ? (int) $absent : null;
    }

    private function buildLmsReference(AttendanceSession $session): string
    {
        $title = trim($session->title) !== '' ? trim($session->title) : 'Roll Call';

        return Str::limit('RC-SESSION:'.$session->id.'|'.$title, 100, '');
    }

    private function resolveAcademicYear(AttendanceSession $session): ?string
    {
        $fromClass = trim((string) ($session->classRoom?->academic_year ?? ''));
        if ($fromClass !== '') {
            return $fromClass;
        }

        return $session->session_date?->format('Y');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bindLookup(array &$payload, string $attribute, string $entitySet, ?string $id): void
    {
        $id = trim((string) $id);
        if ($id === '') {
            return;
        }

        $entitySet = trim($entitySet, '/');
        $payload["{$attribute}@odata.bind"] = "/{$entitySet}({$id})";
    }
}
