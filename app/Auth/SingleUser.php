<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class SingleUser implements Authenticatable
{
    public string $rememberToken = '';

    public function __construct(public string $username) {}

    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    public function getAuthIdentifier(): string
    {
        return $this->username;
    }

    public function getAuthPassword(): string
    {
        return (string) config('auth.credentials.password_hash', '');
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken ?: null;
    }

    public function setRememberToken($value): void
    {
        $this->rememberToken = (string) $value;
    }

    public function getRememberTokenName(): ?string
    {
        return 'remember_token';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }
}