<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AgentBudget
{
    public function reserve(User $user, Merchant $merchant): string
    {
        return DB::transaction(function () use ($user, $merchant): string {
            $now = CarbonImmutable::now('UTC');
            $day = $now->setTimezone('America/Los_Angeles')->startOfDay()->utc();
            $calls = DB::table('agent_calls');
            $busy = (clone $calls)->where('user_id', $user->id)->where('status', 'running')->where('started_at', '>', $now->subSeconds(30))->exists();
            $userMinute = (clone $calls)->where('user_id', $user->id)->where('started_at', '>', $now->subMinute())->count();
            $userDay = (clone $calls)->where('user_id', $user->id)->where('started_at', '>=', $day)->count();
            $projectMinute = (clone $calls)->where('started_at', '>', $now->subMinute())->count();
            $projectDay = (clone $calls)->where('started_at', '>=', $day)->count();
            if ($busy || $userMinute >= config('agent.user_per_minute') || $userDay >= config('agent.user_per_day') || $projectMinute >= config('agent.project_per_minute') || $projectDay >= config('agent.project_per_day')) {
                throw new HttpException(429, 'El asistente alcanzó su límite de uso. Intenta más tarde; las consultas de operaciones siguen disponibles.', null, ['Retry-After' => '60']);
            }
            $id = (string) Str::uuid();
            DB::table('agent_calls')->insert(['id' => $id, 'user_id' => $user->id, 'merchant_id' => $merchant->id, 'status' => 'running', 'started_at' => $now]);

            return $id;
        }, 5);
    }

    public function finish(string $id, string $status, ?string $tool = null, ?int $tokens = null): void
    {
        DB::table('agent_calls')->where('id', $id)->update(['status' => $status, 'tool' => $tool, 'tokens' => $tokens, 'finished_at' => now('UTC')]);
    }
}
