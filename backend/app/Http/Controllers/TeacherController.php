<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
    public function index(): JsonResponse
    {
        $teachers = User::query()
            ->with(['role', 'identities'])
            ->whereHas('role', fn ($query) => $query->where('slug', 'teacher'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => UserResource::collection($teachers),
        ]);
    }
}
