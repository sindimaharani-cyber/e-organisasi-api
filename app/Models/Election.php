<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    use HasFactory;

    protected $table = 'elections';

    protected $fillable = [
        'created_by',
        'title',
        'description',

        'start_date',
        'end_date',

        'registration_start',
        'registration_end',

        'voting_start',
        'voting_end',

        'minimum_gpa',

        'status',
        'result_published',

        'requirements',

        'starts_at',
        'ends_at',

        'start_at',
        'end_at',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',

        'registration_start' => 'datetime',
        'registration_end' => 'datetime',

        'voting_start' => 'datetime',
        'voting_end' => 'datetime',

        'starts_at' => 'datetime',
        'ends_at' => 'datetime',

        'start_at' => 'datetime',
        'end_at' => 'datetime',

        'minimum_gpa' => 'decimal:2',

        'result_published' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | KANDIDAT
    |--------------------------------------------------------------------------
    */

    public function candidates(): HasMany
    {
        return $this->hasMany(
            ElectionCandidate::class,
            'election_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PEMILIH
    |--------------------------------------------------------------------------
    */

    public function voters(): HasMany
    {
        return $this->hasMany(
            ElectionVoter::class,
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
            'election_id'
        );
    }
}