<?php

namespace Kirschbaum\Loop\Commands\Concerns;

use Exception;
use Illuminate\Support\Facades\Auth;

trait AuthenticateUsers
{
    protected function authenticateUser(): void
    {
        /** @var string|null */
        $authGuard = $this->option('auth-guard') ?? config('auth.defaults.guard');
        $userModel = $this->option('user-model') ?? 'App\\Models\\User';
        $user = $userModel::find($this->option('user-id'));

        if (! $user) {
            throw new Exception(sprintf('User with ID %s not found. Model used: %s', $this->option('user-id'), $userModel));
        }

        Auth::guard($authGuard)->login($user);

        if ($this->option('debug')) {
            $this->info(sprintf('Authenticated with user ID %s', $this->option('user-id')));
        }
    }
}
