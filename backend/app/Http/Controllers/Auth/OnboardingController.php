<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\School;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\JwtIssuer;
use App\Services\Auth\UserAccessService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly UserAccessService $userAccessService,
        private readonly JwtIssuer $jwtIssuer,
        private readonly TenantService $tenantService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function schools(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->userAccessService->canSelfAssignSchools($user)) {
            return response()->json([
                'message' => 'School self-selection is only available for teacher accounts.',
            ], 403);
        }

        if (! $this->userAccessService->requiresSchoolSelection($user)) {
            return response()->json([
                'message' => 'Your account already has school access assigned.',
            ], 422);
        }

        return response()->json([
            'code' => 'school_selection_required',
            'message' => 'Select your school to finish setting up your account.',
            'user' => UserResource::make($user->loadMissing(['role', 'identities', 'schools'])),
            'schools' => $this->availableSchools(),
        ]);
    }

    public function assignSchools(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->userAccessService->canSelfAssignSchools($user)) {
            return response()->json([
                'message' => 'School self-selection is only available for teacher accounts.',
            ], 403);
        }

        if (! $this->userAccessService->requiresSchoolSelection($user)) {
            return response()->json([
                'message' => 'Your account already has school access assigned.',
            ], 422);
        }

        $validated = $request->validate([
            'school_ids' => ['required', 'array', 'min:1'],
            'school_ids.*' => ['integer', 'exists:schools,id'],
        ]);

        $activeSchoolIds = School::query()
            ->where('active', true)
            ->whereIn('id', $validated['school_ids'])
            ->pluck('id')
            ->all();

        if ($activeSchoolIds === []) {
            throw ValidationException::withMessages([
                'school_ids' => 'Select at least one active school.',
            ]);
        }

        $user->schools()->sync($activeSchoolIds);
        $user = $user->fresh(['role', 'identities', 'schools']);

        $this->tenantService->setCurrentSchoolId((string) $activeSchoolIds[0]);

        $tokens = $this->jwtIssuer->createTokenPair($user, $request);

        $this->auditLogger->log(
            $user,
            'auth.onboarding.schools',
            'Teacher completed first-login school selection.',
            $user,
            ['school_ids' => $activeSchoolIds],
            request: $request,
        );

        return response()->json([
            'message' => 'School access assigned successfully.',
            'code' => 'onboarding_complete',
            'user' => UserResource::make($user),
            'current_school_id' => $this->tenantService->getCurrentSchoolId(),
            'tokens' => $tokens,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableSchools(): array
    {
        return School::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'level', 'active'])
            ->map(fn (School $school) => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
                'level' => $school->level,
                'active' => $school->active,
            ])
            ->all();
    }
}
