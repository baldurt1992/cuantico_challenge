<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class MerchantPolicy
{
    public function view(User $user, Merchant $merchant): Response
    {
        return $user->merchants()->whereKey($merchant->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function ingest(User $user, Merchant $merchant): Response
    {
        $access = $this->view($user, $merchant);
        if ($access->denied()) {
            return $access;
        }

        return $user->role === 'operator' ? Response::allow() : Response::deny('Solo operadores pueden ingresar eventos.');
    }
}
