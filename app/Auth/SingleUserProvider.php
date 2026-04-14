<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

class SingleUserProvider implements UserProvider
{
    private const CACHE_KEY_PREFIX = 'auth:remember_token:';

    private const CACHE_TTL = 576000; // minutes — matches SessionGuard default remember duration

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
        $cached = Cache::get(self::CACHE_KEY_PREFIX.$identifier);

        if ($cached !== $token) {
            return null;
        }

        $username = config('auth.credentials.username');
        if ($identifier !== $username) {
            return null;
        }

        $user = new SingleUser($username);
        $user->setRememberToken($token);

        return $user;
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
        Cache::put(self::CACHE_KEY_PREFIX.$user->getAuthIdentifier(), $token, self::CACHE_TTL);
    }
}