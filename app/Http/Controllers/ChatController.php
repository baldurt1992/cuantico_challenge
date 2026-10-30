<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Services\GeminiAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ChatController extends Controller
{
    public function store(Request $request, Merchant $merchant, GeminiAgent $agent): JsonResponse
    {
        Gate::authorize('view', $merchant);
        $values = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $context = $request->session()->get('chat', ['merchant_id' => null, 'history' => []]);
        $history = $context['merchant_id'] === $merchant->id ? $context['history'] : [];
        $result = $agent->answer($request->user(), $merchant, $values['message'], $history);
        if (($result['kind'] ?? null) !== 'help') {
            $history[] = ['role' => 'user', 'text' => $values['message']];
            $history[] = ['role' => 'assistant', 'text' => $result['message']];
            $request->session()->put('chat', ['merchant_id' => $merchant->id, 'history' => array_slice($history, -6)]);
        }

        return response()->json($result);
    }
}
