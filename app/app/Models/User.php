<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLES = ['super_admin' => 'Super Admin', 'driver' => 'Operation Support Officer (OSO)', 'admin' => 'Admin', 'officer' => 'Officer'];

    public const DEPARTMENTS = ['HR/Admin', 'Programs', 'Finance'];

    public const OFFICES = ['Accra', 'Tamale'];

    protected $attributes = ['active' => true, 'role' => 'officer', 'department' => 'HR/Admin', 'location' => 'Accra'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function getRoleAttribute(?string $value): string
    {
        return self::normalizeRole($value ?? 'officer');
    }

    public function setRoleAttribute(string $value): void
    {
        $this->attributes['role'] = self::normalizeRole($value);
    }

    public static function normalizeRole(string $value): string
    {
        return match (strtolower(trim($value))) {
            'super_admin', 'superadmin', 'superuser', 'super_user' => 'super_admin',
            'oso', 'operation support officer', 'operation support officer (oso)' => 'driver',
            'it_admin', 'admin_manager', 'management' => 'admin',
            'admin_officer', 'staff', 'finance', 'auditor' => 'officer',
            default => strtolower(trim($value)),
        };
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
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
        'mfa_secret',
        'recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
