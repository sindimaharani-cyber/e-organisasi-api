<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateApplication extends Model
{
    use HasFactory;

    protected $table =
        'candidate_applications';

    protected $fillable = [
        'election_id',
        'user_id',
        'member_id',

        'vision',
        'mission',
        'photo_path',

        'status',
        'rejection_reason',

        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' =>
            'datetime',
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
    | USER
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBER
    |--------------------------------------------------------------------------
    */

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'member_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REVIEWER
    |--------------------------------------------------------------------------
    */

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
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
            (string) (
                $this->photo_path
                ?? ''
            )
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
            'storage/'
            . ltrim(
                $path,
                '/'
            )
        );
    }
}