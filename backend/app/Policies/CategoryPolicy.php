<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    /** Tout utilisateur connecté peut lister les catégories. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): Response
    {
        return $this->adminOnly($user);
    }

    public function update(User $user, Category $category): Response
    {
        return $this->adminOnly($user);
    }

    public function delete(User $user, Category $category): Response
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
