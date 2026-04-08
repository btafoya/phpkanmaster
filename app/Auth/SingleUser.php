<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class SingleUser implements Authenticatable
{
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
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // No-op for single-user
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }
}
