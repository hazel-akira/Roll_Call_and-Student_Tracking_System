<?php

namespace App\Services\Integrations;

use App\Integrations\Dynamics\DynamicsAttendanceWriter;
use App\Integrations\Dynamics\DynamicsClient;
use App\Integrations\Dynamics\DynamicsMapper;
use App\Models\AttendanceSession;
use App\Models\DynamicsSync;
use App\Services\Notifications\NotificationService;
use Illuminate\Validation\ValidationException;
use Throwable;

class DynamicsSyncService
{
    public function __construct(
        private readonly DynamicsClient $client,
        private readonly DynamicsMapper $mapper,
        private readonly DynamicsAttendanceWriter $attendanceWriter,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function queue(AttendanceSession $session): DynamicsSync
    {
        return DynamicsSync::query()->create([
            'attendance_session_id' => $session->id,
            'integration' => 'attendance',
            'direction' => 'outbound',
            'status' => 'queued',
            'payload' => $this->mapper->mapAttendanceSession($session),
        ]);
    }

    public function sync(DynamicsSync $sync): DynamicsSync
    {
        $sync->loadMissing('attendanceSession.records.student', 'attendanceSession.classRoom', 'attendanceSession.subject', 'attendanceSession.teacher');
        $session = $sync->attendanceSession;

        if (! $session) {
            $sync->forceFill([
                'status' => 'skipped',
                'error_message' => 'Attendance session no longer exists.',
            ])->save();

            return $sync;
        }

        try {
            $payload = $this->mapper->mapAttendanceSession($session);

            if (config('dynamics.attendance_push_mode', 'dataverse') === 'dataverse') {
                $response = $this->attendanceWriter->push($session);
                $externalReference = $response['attendance_id'] ?? $response['id'] ?? null;
            } else {
                $response = $this->client->pushAttendance($payload);
                $externalReference = $response['id'] ?? null;
            }

            $sync->forceFill([
                'status' => 'synced',
                'payload' => $payload,
                'response' => $response,
                'external_reference' => $externalReference ?? $sync->external_reference,
                'synced_at' => now(),
                'error_message' => null,
            ])->save();

            $session->forceFill([
                'dynamics_sync_status' => 'synced',
            ])->save();
        } catch (ValidationException $exception) {
            $sync->forceFill([
                'status' => 'skipped',
                'error_message' => $exception->getMessage(),
                'retries' => $sync->retries + 1,
            ])->save();

            $session->forceFill([
                'dynamics_sync_status' => 'skipped',
            ])->save();
        } catch (Throwable $exception) {
            $sync->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'retries' => $sync->retries + 1,
            ])->save();

            $session->forceFill([
                'dynamics_sync_status' => 'failed',
            ])->save();

            $this->notificationService->notifyRole(
                'ict_staff',
                'Dynamics sync failure',
                'An attendance session failed to sync to Microsoft Dynamics.',
                ['sync_id' => $sync->id, 'session_id' => $session->id],
                'integration',
            );
        }

        return $sync->fresh('attendanceSession');
    }
}
