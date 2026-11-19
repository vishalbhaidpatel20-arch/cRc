<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['full_name', 'email', 'phone', 'password_hash', 'role', 'all_companies', 'is_active', 'created_by'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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

    /** Companies a restricted agent (all_companies = false) may see. */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_companies', 'user_id', 'company_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAgent(): bool
    {
        return $this->role === self::ROLE_AGENT;
    }

    /**
     * A deleted agent is soft-deleted (deleted_at is set, see AccountRemoval): the row and its details stay so history keeps
     * its author and an admin can restore the account, but normal queries, logins and pickers no longer see it.
     */
    public function isRemoved(): bool
    {
        return $this->trashed();
    }

    /** Where this user lands after login. */
    public function homeRoute(): string
    {
        return $this->isAdmin() ? 'admin.dashboard' : 'agent.dashboard';
    }
}
