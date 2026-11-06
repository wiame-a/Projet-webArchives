<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->adminOnly($user);
    }

    public function update(User $user, User $target): Response
    {
        return $this->adminOnly($user);
    }

    public function delete(User $user, User $target): Response
    {
        return $this->adminOnly($user);
    }

    private function adminOnly(User $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('Action réservée aux administrateurs.');
    }
}
