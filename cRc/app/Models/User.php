<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['full_name', 'email', 'phone', 'password_hash', 'role', 'all_companies', 'is_active', 'created_by'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_AGENT = 'agent';

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    /** The users table only has created_at (no updated_at). */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'all_companies' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** Laravel reads the password from this column. NULL until the invite is accepted. */
    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }

    /** No remember_token column, so "remember me" is not supported. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAgent(): bool
    {
        return $this->role === self::ROLE_AGENT;
    }

    /** Where this user lands after login. */
    public function homeRoute(): string
    {
        return $this->isAdmin() ? 'admin.dashboard' : 'agent.dashboard';
    }
}
