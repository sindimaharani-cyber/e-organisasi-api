<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EligibleVoter extends Model
{
    protected $table = 'eligible_voters';

    protected $fillable = [
        'election_period_id',
        'user_id',
        'member_id',
        'has_voted',
        'voted_at',
    ];

    protected $casts = [
        'has_voted' => 'boolean',
        'voted_at' => 'datetime',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(
            ElectionPeriod::class,
            'election_period_id'
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