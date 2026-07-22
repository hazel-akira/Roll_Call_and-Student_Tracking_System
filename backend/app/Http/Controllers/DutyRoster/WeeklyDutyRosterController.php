<?php

namespace App\Http\Controllers\DutyRoster;

use App\Http\Controllers\Controller;
use App\Http\Requests\DutyRoster\StoreWeeklyDutyRosterRequest;
use App\Http\Requests\DutyRoster\UpdateWeeklyDutyRosterRequest;
use App\Models\WeeklyDutyRoster;
use App\Services\DutyRoster\WeeklyDutyRosterService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyDutyRosterController extends Controller
{
    public function __construct(
        private readonly WeeklyDutyRosterService $rosterService,
        private readonly TenantService $tenantService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->requireSchoolId($request);

        $rosters = WeeklyDutyRoster::query()
            ->where('school_id', $schoolId)
            ->with('publisher:id,name')
            ->withCount('entries')
            ->orderByDesc('week_start')
            ->limit(40)
            ->get()
            ->map(fn (WeeklyDutyRoster $roster): array => [
                'id' => $roster->id,
                'school_id' => $roster->school_id,
                'week_start' => $roster->week_start?->toDateString(),
                'week_end' => $roster->week_end?->toDateString(),
                'week_label' => $roster->weekLabel(),
                'status' => $roster->status ?? WeeklyDutyRoster::STATUS_DRAFT,
                'published_at' => $roster->published_at?->toIso8601String(),
                'published_by' => $roster->published_by,
                'published_by_name' => $roster->publisher?->name,
                'entries_count' => $roster->entries_count,
            ]);

        return response()->json(['data' => $rosters]);
    }

    public function current(Request $request): JsonResponse
    {
        $schoolId = $this->requireSchoolId($request);
        $roster = $this->rosterService->resolveCurrentForEditing($schoolId);

        return response()->json([
            'data' => $roster ? $this->rosterService->serialize($roster) : null,
        ]);
    }

    public function show(Request $request, WeeklyDutyRoster $dutyRoster): JsonResponse
    {
        $this->ensureRosterAccessible($request, $dutyRoster);

        return response()->json([
            'data' => $this->rosterService->serialize($dutyRoster->load(['entries.staff'])),
        ]);
    }

    public function store(StoreWeeklyDutyRosterRequest $request): JsonResponse
    {
        $schoolId = $this->requireSchoolId($request);
        $validated = $request->validated();

        try {
            $roster = $this->rosterService->create(
                $schoolId,
                $validated['week_start'],
                $validated['week_end'] ?? null,
            );
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);

            $hint = str_contains($exception->getMessage(), 'school_duty_roster_template_entries')
                || str_contains($exception->getMessage(), 'Unknown column')
                || str_contains($exception->getMessage(), "doesn't exist")
                ? ' Backend database may be missing recent migrations. Run: php artisan migrate --force'
                : '';

            return response()->json([
                'message' => 'Unable to create the weekly duty roster.'.$hint,
            ], 503);
        }

        return response()->json([
            'message' => 'Weekly duty roster created.',
            'data' => $this->rosterService->serialize($roster),
        ], 201);
    }

    public function update(UpdateWeeklyDutyRosterRequest $request, WeeklyDutyRoster $dutyRoster): JsonResponse
    {
        $this->ensureRosterAccessible($request, $dutyRoster);

        $roster = $this->rosterService->update($dutyRoster, $request->validated());

        return response()->json([
            'message' => 'Weekly duty roster saved.',
            'data' => $this->rosterService->serialize($roster),
        ]);
    }

    public function destroy(Request $request, WeeklyDutyRoster $dutyRoster): JsonResponse
    {
        $this->ensureRosterAccessible($request, $dutyRoster);
        $dutyRoster->delete();

        return response()->json([
            'message' => 'Weekly duty roster deleted.',
        ]);
    }

    public function resetTemplate(Request $request, WeeklyDutyRoster $dutyRoster): JsonResponse
    {
        $this->ensureRosterAccessible($request, $dutyRoster);

        $roster = $this->rosterService->resetTemplate($dutyRoster);

        return response()->json([
            'message' => 'Duty roster reset to the standard layout.',
            'data' => $this->rosterService->serialize($roster),
        ]);
    }

    public function copyFromPrevious(Request $request, WeeklyDutyRoster $dutyRoster): JsonResponse
    {
        $this->ensureRosterAccessible($request, $dutyRoster);

        $roster = $this->rosterService->copyFromPrevious($dutyRoster);

        return response()->json([
            'message' => 'Staff assignments copied from the previous week.',
            'data' => $this->rosterService->serialize($roster),
        ]);
    }

    public function publish(Request $request, WeeklyDutyRoster $dutyRoster): JsonResponse
    {
        $this->ensureRosterAccessible($request, $dutyRoster);

        $roster = $this->rosterService->publish($dutyRoster, $request->user()?->id);

        return response()->json([
            'message' => 'Duty roster published. It will appear on roll call reports and notifications.',
            'data' => $this->rosterService->serialize($roster),
        ]);
    }

    public function unpublish(Request $request, WeeklyDutyRoster $dutyRoster): JsonResponse
    {
        $this->ensureRosterAccessible($request, $dutyRoster);

        $roster = $this->rosterService->unpublish($dutyRoster);

        return response()->json([
            'message' => 'Duty roster moved back to draft.',
            'data' => $this->rosterService->serialize($roster),
        ]);
    }

    private function requireSchoolId(Request $request): int
    {
        $schoolId = $this->tenantService->effectiveSchoolId($request);

        abort_unless($schoolId !== null, 422, 'Select a school before managing duty rosters.');

        abort_unless(
            $this->tenantService->userCanAccessSchool($request->user(), $schoolId),
            403,
            'You do not have access to the selected school.',
        );

        return (int) $schoolId;
    }

    private function ensureRosterAccessible(Request $request, WeeklyDutyRoster $dutyRoster): void
    {
        $schoolId = $this->requireSchoolId($request);

        abort_unless((int) $dutyRoster->school_id === $schoolId, 404);
    }
}
