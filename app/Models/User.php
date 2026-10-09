<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
        return $this->is_active && array_key_exists(
            $this->role,
            self::CMS_ROLES
        );
    }

    public function canManageAdmins(): bool
    {
        return $this->canAccessCms()
            && $this->role === 'super_admin';
    }

    public function setUsernameAttribute(?string $value): void
    {
        $this->attributes['username'] =
            $value === null ? null : strtolower(trim($value));
    }

    public function cmsHomeRoute(): string
    {
        return 'admin.dashboard';
    }

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'owned_ids' => 'array',
            'owned_documents' => 'array',
            'profile_setup_completed_at' => 'datetime',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedGovernmentIds(): BelongsToMany
    {
        return $this->belongsToMany(
            GovernmentId::class,
            'resident_government_ids'
        )->withTimestamps();
    }

    public function ownedDocuments(): BelongsToMany
    {
        return $this->belongsToMany(
            Document::class,
            'resident_documents'
        )->withTimestamps();
    }
}