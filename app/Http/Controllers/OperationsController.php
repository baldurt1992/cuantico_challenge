<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Services\DeliveryQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OperationsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Operations', [
            'merchants' => $request->user()->merchants()->get(['merchants.id', 'name', 'timezone']),
            'agent_enabled' => filled(config('services.gemini.key')),
        ]);
    }

    public function data(Request $request, Merchant $merchant, DeliveryQueries $queries): JsonResponse
    {
        Gate::authorize('view', $merchant);

        return response()->json([
            'deliveries' => $queries->search($request->user(), $merchant, $request->query()),
            'totals' => $queries->totals($request->user(), $merchant, $request->query()),
            'issues' => $queries->issues($request->user(), $merchant),
            'couriers' => $merchant->couriers()->get(['couriers.id', 'name']),
            'courier_counts' => $request->filled('courier_id') ? $queries->courierCounts($request->user(), $merchant, $request->query()) : null,
        ]);
    }

    public function detail(Request $request, Merchant $merchant, string $delivery, DeliveryQueries $queries): JsonResponse
    {
        return response()->json($queries->detail($request->user(), $merchant, $delivery));
    }
}
