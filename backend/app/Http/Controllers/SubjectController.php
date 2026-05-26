<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Subject::query()->orderBy('name')->get(),
        ]);
    }
}
