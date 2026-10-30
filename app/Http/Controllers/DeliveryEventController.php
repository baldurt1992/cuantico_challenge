<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryEventRequest;
use App\Services\EventIngestor;
use Illuminate\Http\JsonResponse;

final class DeliveryEventController extends Controller
{
    public function store(StoreDeliveryEventRequest $request, EventIngestor $ingestor): JsonResponse
    {
        $result = $ingestor->ingest($request->user(), $request->validated());

        return response()->json($result, $result['duplicate'] ? 200 : 201);
    }
}
