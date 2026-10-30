<?php

namespace App\Services;

use App\Http\Resources\DeliveryResource;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class DeliveryQueries
{
    public function __construct(private ReportPeriod $periods) {}

    public function search(User $user, Merchant $merchant, array $input): array
    {
        Gate::forUser($user)->authorize('view', $merchant);
        $values = Validator::make($input, [
            'text' => ['nullable', 'string', 'min:2', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ])->validate();
        $query = $merchant->deliveries()->with('courier');
        $text = trim($values['text'] ?? '');
        if ($text !== '') {
            $fold = static fn (?string $value): string => Str::squish(Str::lower(Str::ascii($value ?? '')));
            $text = $fold($text);
            $pdo = $query->getConnection()->getPdo();
            $createFunction = method_exists($pdo, 'createFunction') ? 'createFunction' : 'sqliteCreateFunction';
            $pdo->{$createFunction}('search_contains', static fn (?string $value, string $needle): int => (int) str_contains($fold($value), $needle), 2);
            $query->where(function (Builder $query) use ($text): void {
                $query->whereRaw('search_contains(delivery_address, ?)', [$text])
                    ->orWhereRaw("search_contains(json_extract(planned_pickup, '$.label'), ?)", [$text])
                    ->orWhereHas('merchant', fn (Builder $merchant) => $merchant->whereRaw('search_contains(name, ?)', [$text]))
                    ->orWhereHas('courier', fn (Builder $courier) => $courier->whereRaw('search_contains(name, ?)', [$text]));
            });
        }
        $page = $query->orderByRaw('created_occurred_at IS NULL')->orderByDesc('created_occurred_at')->orderBy('id')
            ->paginate($values['per_page'] ?? 20, ['*'], 'page', $values['page'] ?? 1);

        return [
            'data' => DeliveryResource::collection($page->getCollection())->resolve(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'merchant_id' => $merchant->id,
            'queried_at' => now('UTC')->toIso8601String(),
        ];
    }

    public function totals(User $user, Merchant $merchant, array $input): array
    {
        Gate::forUser($user)->authorize('view', $merchant);
        $period = $this->periods->resolve($merchant, $input, 'finalización');
        $query = $merchant->deliveries()->where('quality', 'consistent')->whereNotNull('cost_minor')
            ->where('completed_at', '>=', $period['start'])->where('completed_at', '<', $period['end']);
        $counts = (clone $query)->selectRaw('status, COUNT(*) as amount')->groupBy('status')->pluck('amount', 'status');

        return [
            'merchant_id' => $merchant->id,
            'merchant_name' => $merchant->name,
            'currency' => 'COP',
            'total_minor' => (int) $query->sum('cost_minor'),
            'deliveries' => (int) $counts->sum(),
            'delivered' => (int) ($counts['delivered'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'period' => $period,
            'queried_at' => now('UTC')->toIso8601String(),
        ];
    }

    public function courierCounts(User $user, Merchant $merchant, array $input): array
    {
        Gate::forUser($user)->authorize('view', $merchant);
        $values = Validator::make($input, ['courier_id' => ['required', 'integer']])->validate();
        $courier = $merchant->couriers()->whereKey($values['courier_id'])->firstOrFail();
        $period = $this->periods->resolve($merchant, $input, 'creación');
        $groups = $merchant->deliveries()->where('courier_id', $courier->id)
            ->where('created_occurred_at', '>=', $period['start'])->where('created_occurred_at', '<', $period['end'])
            ->selectRaw("CASE WHEN quality = 'conflict' THEN 'conflict' ELSE status END as category, COUNT(*) as amount")
            ->groupBy('category')->pluck('amount', 'category')->map(fn ($count): int => (int) $count)->all();

        return ['merchant_id' => $merchant->id, 'courier_id' => $courier->id, 'courier_name' => $courier->name, 'counts' => $groups, 'period' => $period, 'queried_at' => now('UTC')->toIso8601String()];
    }

    public function issues(User $user, Merchant $merchant): array
    {
        Gate::forUser($user)->authorize('view', $merchant);

        return [
            'pending' => $merchant->deliveries()->where('quality', '!=', 'conflict')->whereNull('cost_minor')->count(),
            'conflict' => $merchant->deliveries()->where('quality', 'conflict')->count(),
            'scope' => 'Estado actual del comercio, independiente del período del reporte',
        ];
    }

    public function detail(User $user, Merchant $merchant, string $id): array
    {
        Gate::forUser($user)->authorize('view', $merchant);
        $delivery = $merchant->deliveries()->with(['courier', 'events', 'revisions'])->findOrFail($id);

        return [
            'delivery' => (new DeliveryResource($delivery))->resolve(),
            'events' => $delivery->events->sortBy('occurred_at')->values()->map(fn ($event): array => $event->only(['event_id', 'type', 'occurred_at', 'received_at', 'data']))->all(),
            'revisions' => $delivery->revisions->sortByDesc('id')->values()->map(fn ($revision): array => $revision->only(['id', 'reason', 'snapshot', 'created_at']))->all(),
        ];
    }
}
