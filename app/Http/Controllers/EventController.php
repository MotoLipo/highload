<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function store(StoreEventRequest $request, EventService $service): JsonResponse
    {
        $service->handle($request->validated());

        return response()->json(['status' => 'accepted'], 202);
    }

}
