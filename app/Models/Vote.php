<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    protected $table = 'votes';

    protected $fillable = [
        'election_period_id',
        'candidate_id',
        'user_id',
        'member_id',
        'voted_at',
    ];

    protected $casts = [
        'voted_at' => 'datetime',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(
            ElectionPeriod::class,
            'election_period_id'
        );
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(
            ElectionCandidate::class,
            'candidate_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}