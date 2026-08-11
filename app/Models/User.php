<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the roles that belong to the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Assignment Mitra Kerja Evaluasi Well (satu per user).
     */
    public function evaluasiWellMitraAssignment()
    {
        return $this->hasOne(EvaluasiWellMitraAssignment::class);
    }

    /**
     * Assignment Mitra Kerja aktif.
     */
    public function activeEvaluasiWellMitraAssignment()
    {
        return $this->hasOne(EvaluasiWellMitraAssignment::class)->where('is_active', true);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole($role): bool
    {
        if (is_string($role)) {
            // Check both slug and name for flexibility
            return $this->roles()->where(function($query) use ($role) {
                $query->where('slug', $role)
                      ->orWhere('name', $role);
            })->exists();
        }
        
        return $this->roles()->where('id', $role->id)->exists();
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission($permission): bool
    {
        // Check through all user's roles
        foreach ($this->roles as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Assign role to user.
     */
    public function assignRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->first();
        }
        
        if ($role && !$this->hasRole($role)) {
            $this->roles()->attach($role);
        }
        
        return $this;
    }

    /**
     * Remove role from user.
     */
    public function removeRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->first();
        }
        
        if ($role) {
            $this->roles()->detach($role);
        }
        
        return $this;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->role === 'administrator' || $this->hasRole('admin');
    }
}
