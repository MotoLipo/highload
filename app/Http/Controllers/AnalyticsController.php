<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $service) {}

    public function pageViews(Request $request): JsonResponse
    {
        $data = $this->service->pageViews((int) $request->query('days', 7));

        return response()->json(['data' => $data]);
    }

    public function conversion(Request $request): JsonResponse
    {
        $data = $this->service->conversion((int) $request->query('days', 7));

        return response()->json(['data' => $data]);
    }
}
