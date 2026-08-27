<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationProfile extends Model
{
    protected $table = 'organization_profiles';

    protected $fillable = [
        'name',
        'description',
        'history',
        'vision',
        'mission',
        'goals',
        'photo_path',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        if (
            $this->photo_path === null ||
            trim($this->photo_path) === ''
        ) {
            return null;
        }

        return asset(
            'storage/' . ltrim($this->photo_path, '/')
        );
    }
}