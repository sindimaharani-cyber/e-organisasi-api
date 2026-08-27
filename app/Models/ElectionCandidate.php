<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionCandidate extends Model
{
    use HasFactory;

    protected $table = 'election_candidates';

    protected $fillable = [
        'election_id',
        'candidate_number',
        'name',
        'nim',
        'study_program',
        'vision',
        'mission',
        'photo_path',
    ];

    protected $casts = [
        'candidate_number' => 'integer',
    ];

    protected $appends = [
        'photo_url',
    ];

    /*
    |--------------------------------------------------------------------------
    | PEMILIHAN
    |--------------------------------------------------------------------------
    */

    public function election(): BelongsTo
    {
        return $this->belongsTo(
            Election::class,
            'election_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUARA
    |--------------------------------------------------------------------------
    */

    public function votes(): HasMany
    {
        return $this->hasMany(
            ElectionVote::class,
            'candidate_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PHOTO URL
    |--------------------------------------------------------------------------
    */

    public function getPhotoUrlAttribute(): ?string
    {
        $path = trim(
            (string) ($this->photo_path ?? '')
        );

        if ($path === '') {
            return null;
        }

        if (
            str_starts_with(
                $path,
                'http://'
            ) ||
            str_starts_with(
                $path,
                'https://'
            )
        ) {
            return $path;
        }

        if (
            str_starts_with(
                $path,
                'storage/'
            )
        ) {
            return url($path);
        }

        return url(
            'storage/' . ltrim(
                $path,
                '/'
            )
        );
    }
}