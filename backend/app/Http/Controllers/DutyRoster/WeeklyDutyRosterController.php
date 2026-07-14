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
            ->withCount('entries')
            ->orderByDesc('week_start')
            ->limit(20)
            ->get()
            ->map(fn (WeeklyDutyRoster $roster): array => [
                'id' => $roster->id,
                'school_id' => $roster->school_id,
                'week_start' => $roster->week_start?->toDateString(),
                'week_end' => $roster->week_end?->toDateString(),
                'week_label' => $roster->weekLabel(),
                'entries_count' => $roster->entries_count,
            ]);

        return response()->json(['data' => $rosters]);
    }

    public function current(Request $request): JsonResponse
    {
        $schoolId = $this->requireSchoolId($request);
        $roster = $this->rosterService->resolveCurrent($schoolId);

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

        $roster = $this->rosterService->create(
            $schoolId,
            $validated['week_start'],
            $validated['week_end'] ?? null,
        );

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
