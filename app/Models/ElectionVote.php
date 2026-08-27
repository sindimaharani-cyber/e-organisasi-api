<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionVote extends Model
{
    use HasFactory;

    protected $table = 'election_votes';

    protected $fillable = [
        'election_id',
        'candidate_id',
        'user_id',
        'voted_at',
    ];

    protected $casts = [
        'voted_at' => 'datetime',
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
    | KANDIDAT
    |--------------------------------------------------------------------------
    */

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(
            ElectionCandidate::class,
            'candidate_id'
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
}