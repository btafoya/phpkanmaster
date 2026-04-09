<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class SingleUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        $username = config('auth.credentials.username');
        if ($identifier === $username) {
            return new SingleUser($username);
        }
        return null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return $this->retrieveById($identifier);
    }

    /**
     * @param array<string, string> $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials['username'])) {
            return null;
        }
        $username = config('auth.credentials.username');
        if ($credentials['username'] === $username) {
            return new SingleUser($username);
        }
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $expectedHash = config('auth.credentials.password_hash');
        if (empty($expectedHash)) {
            return false;
        }
        return password_verify($credentials['password'], $expectedHash);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // No-op for single-user
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // No-op for single-user
    }
}
