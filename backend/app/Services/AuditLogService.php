<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    /**
     * Log an activity.
     */
    public function log(string $action, $subject = null, array $properties = []): void
    {
        if (! config('audit.enabled', true)) {
            return;
        }

        $user = Auth::user();

        // Redact sensitive fields from properties
        $redactedProperties = $this->redactSensitiveFields($properties);

        try {
            AuditLog::query()->create([
                'actor_user_id' => $user?->id,
                'event_type' => $action,
                'entity_type' => $subject ? get_class($subject) : null,
                'entity_id' => $subject?->id,
                'description' => (string) ($redactedProperties['description'] ?? $action),
                'old_values' => $redactedProperties['old_values'] ?? null,
                'new_values' => $redactedProperties['new_values'] ?? $redactedProperties,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fallback to file logging if database fails
            Log::channel('stack')->warning('Audit log write failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Redact sensitive fields from properties.
     */
    protected function redactSensitiveFields(array $properties): array
    {
        $sensitiveFields = config('audit.sensitive_fields', [
            'password',
            'ssn',
            'medical_record_number',
            'diagnosis',
            'treatment_notes',
        ]);

        foreach ($sensitiveFields as $field) {
            if (isset($properties[$field])) {
                $properties[$field] = '[REDACTED]';
            }
        }

        return $properties;
    }

    /**
     * Log patient record access.
     */
    public function logPatientAccess($person): void
    {
        $this->log('patient.viewed', $person, [
            'person_id' => $person->id,
            'name' => $person->full_name,
        ]);
    }

    /**
     * Log visit access.
     */
    public function logVisitAccess($visit): void
    {
        $this->log('visit.viewed', $visit, [
            'visit_id' => $visit->id,
            'person_id' => $visit->person_id,
        ]);
    }

    /**
     * Log data modification.
     */
    public function logModification(string $action, $subject, array $changes = []): void
    {
        $this->log($action, $subject, [
            'changes' => $this->redactSensitiveFields($changes),
        ]);
    }
}
