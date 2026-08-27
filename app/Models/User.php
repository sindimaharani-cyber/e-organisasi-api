<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * Kolom yang boleh diisi menggunakan create() atau update().
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * Kolom yang tidak ditampilkan pada JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Konversi tipe data otomatis.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi satu akun user dengan satu data anggota.
     */
    public function member()
    {
        return $this->hasOne(
            Member::class,
            'user_id',
            'id'
        );
    }

    /**
     * Mengecek apakah akun masih aktif.
     *
     * Method ini memperbaiki error:
     * Call to undefined method App\Models\User::isActive()
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Mengecek apakah akun tidak aktif.
     */
    public function isInactive(): bool
    {
        return !$this->isActive();
    }

    /**
     * Mengecek apakah pengguna adalah Admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Mengecek apakah pengguna adalah Pengurus.
     */
    public function isOfficer(): bool
    {
        return $this->role === 'pengurus';
    }

    /**
     * Mengecek apakah pengguna adalah Mahasiswa.
     */
    public function isStudent(): bool
    {
        return $this->role === 'mahasiswa';
    }

    /**
     * Mengecek apakah user mempunyai salah satu role.
     *
     * Contoh:
     * $user->hasRole('admin')
     * $user->hasRole(['admin', 'pengurus'])
     */
    public function hasRole(
        string|array $roles
    ): bool {
        $allowedRoles = is_array($roles)
            ? $roles
            : [$roles];

        return in_array(
            $this->role,
            $allowedRoles,
            true
        );
    }

    /**
     * Mengecek apakah user boleh mengelola data organisasi.
     */
    public function canManageOrganization(): bool
    {
        return $this->isAdmin()
            || $this->isOfficer();
    }

    /**
     * Scope untuk mengambil user aktif.
     *
     * Contoh:
     * User::active()->get();
     */
    public function scopeActive($query)
    {
        return $query->where(
            'status',
            'active'
        );
    }

    /**
     * Scope untuk mengambil user tidak aktif.
     */
    public function scopeInactive($query)
    {
        return $query->where(
            'status',
            'inactive'
        );
    }

    /**
     * Scope berdasarkan role.
     *
     * Contoh:
     * User::role('admin')->get();
     */
    public function scopeRole(
        $query,
        string $role
    ) {
        return $query->where(
            'role',
            $role
        );
    }
}