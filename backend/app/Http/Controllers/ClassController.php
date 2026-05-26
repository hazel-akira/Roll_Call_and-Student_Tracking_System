<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;

class ClassController extends Controller
{
    public function index(): JsonResponse
    {
        $classes = SchoolClass::query()
            ->with('homeroomTeacher:id,name,email')
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $classes,
        ]);
    }
}
