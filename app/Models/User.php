<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const CMS_ROLES = [
        'super_admin' => 'Super Admin',
        'researcher' => 'Researcher',
    ];

    protected $attributes = [
        'role' => 'resident',
        'is_active' => true,
    ];

    public function canAccessCms(): bool
    {
        return $this->is_active && array_key_exists($this->role, self::CMS_ROLES);
    }

    public function canManageAdmins(): bool
    {
        return $this->canAccessCms() && $this->role === 'super_admin';
    }

    public function setUsernameAttribute(?string $value): void
    {
        $this->attributes['username'] = $value === null ? null : strtolower(trim($value));
    }

    public function cmsHomeRoute(): string
    {
        return $this->canManageAdmins() ? 'admin.dashboard' : 'admin.government-ids.index';
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
