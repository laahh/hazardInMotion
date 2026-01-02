<?php

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\SafetyScoreLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SafetyScoreController extends Controller
{
    /**
     * Store safety score data from DMS frontend
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|string|max:50',
            'trip_id' => 'nullable|string|max:50',
            'timestamp' => 'required|date',
            'ear' => 'nullable|numeric',
            'perclos_60s' => 'nullable|numeric|min:0|max:1',
            'blink_60s' => 'nullable|integer|min:0',
            'microsleep_60s' => 'nullable|integer|min:0',
            'fatigue' => 'nullable|numeric|min:0|max:100',
            'drift' => 'nullable|numeric|min:0|max:100',
            'safety_score' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string|in:Safe,Caution,Attention',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $log = SafetyScoreLog::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Safety score logged successfully',
                'data' => $log,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to store safety score log', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store safety score log',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

