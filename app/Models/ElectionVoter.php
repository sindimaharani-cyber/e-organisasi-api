<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionVoter extends Model
{
    use HasFactory;

    protected $table = 'election_voters';

    protected $fillable = [
        'election_id',
        'user_id',
        'is_eligible',
        'has_voted',
        'voted_at',
    ];

    protected $casts = [
        'is_eligible' => 'boolean',
        'has_voted' => 'boolean',
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