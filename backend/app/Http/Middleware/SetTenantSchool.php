<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenantSchool
{
    public function __construct(
        protected TenantService $tenant
    ) {}

    /**
     * Resolve the active school for the request and merge school_id for downstream scoping.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        $requestedSchoolId = $request->header(TenantService::SCHOOL_HEADER)
            ?? $request->query('school_id')
            ?? $request->input('school_id');

        if ($this->tenant->hasAccessToAllSchools($user)) {
            if ($requestedSchoolId !== null && $requestedSchoolId !== '') {
                $schoolId = (string) $requestedSchoolId;
                if (! $this->tenant->userCanAccessSchool($user, $schoolId)) {
                    return response()->json([
                        'message' => 'The selected school does not exist.',
                    ], 422);
                }
                $request->merge(['school_id' => $schoolId]);
                $this->tenant->setCurrentSchoolId($schoolId);
            }

            return $next($request);
        }

        $allowedIds = $this->tenant->allowedSchoolIds($user);

        if ($this->tenant->isTenantUser($user) && $allowedIds === []) {
            return response()->json([
                'message' => 'Your account is not assigned to any school. Contact an administrator.',
            ], 403);
        }

        if ($allowedIds === []) {
            return $next($request);
        }

        if ($requestedSchoolId !== null && $requestedSchoolId !== '') {
            $schoolId = (string) $requestedSchoolId;
            if (! in_array($schoolId, $allowedIds, true)) {
                return response()->json([
                    'message' => 'You do not have access to the selected school.',
                ], 403);
            }
            $request->merge(['school_id' => $schoolId]);
            $this->tenant->setCurrentSchoolId($schoolId);

            return $next($request);
        }

        $currentId = $this->tenant->getCurrentSchoolId();
        if ($currentId !== null && in_array($currentId, $allowedIds, true)) {
            $request->merge(['school_id' => $currentId]);

            return $next($request);
        }

        $this->tenant->setCurrentSchoolId($allowedIds[0]);
        $request->merge(['school_id' => $allowedIds[0]]);

        return $next($request);
    }
}
