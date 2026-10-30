<?php

namespace App\Services;

use App\Models\Merchant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

final class ReportPeriod
{
    public function resolve(Merchant $merchant, array $input, string $criterion): array
    {
        $values = Validator::make($input, [
            'period' => ['sometimes', 'in:last_week'],
            'start_date' => ['required_without:period', 'date_format:Y-m-d'],
            'end_date' => ['required_without:period', 'date_format:Y-m-d', 'after:start_date'],
        ])->validate();
        if (isset($values['period'])) {
            $end = CarbonImmutable::now($merchant->timezone)->startOfWeek();
            $start = $end->subWeek();
        } else {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $values['start_date'], $merchant->timezone);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $values['end_date'], $merchant->timezone);
        }
        abort_if($start->diffInDays($end) > 366, 422, 'El período no puede superar 366 días.');

        return [
            'start' => $start->utc()->format('Y-m-d H:i:s.u'),
            'end' => $end->utc()->format('Y-m-d H:i:s.u'),
            'start_date' => $start->format('Y-m-d'),
            'end_date_exclusive' => $end->format('Y-m-d'),
            'timezone' => $merchant->timezone,
            'criterion' => $criterion,
        ];
    }
}
