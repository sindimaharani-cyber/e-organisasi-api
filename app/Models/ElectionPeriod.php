<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionPeriod extends Model
{
    protected $table = 'election_periods';

    protected $fillable = [
        'title',
        'description',

        'start_date',
        'end_date',

        'registration_start',
        'registration_end',

        'voting_start',
        'voting_end',

        'status',
        'result_published',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',

        'registration_start' => 'datetime',
        'registration_end' => 'datetime',

        'voting_start' => 'datetime',
        'voting_end' => 'datetime',

        'result_published' => 'boolean',
    ];
}