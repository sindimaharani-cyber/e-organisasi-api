<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    protected $table = 'activities';

    protected $fillable = [
        'created_by',
        'title',
        'description',

        'activity_date',
        'start_time',
        'end_time',

        /*
         * Kolom legacy / kompatibilitas
         */
        'start_datetime',
        'end_datetime',

        'location',
        'target_role',
        'quota',
        'status',
        'registration_open',

        'completed_at',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'completed_at' => 'datetime',
        'registration_open' => 'boolean',
        'quota' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
            'id'
        );
    }
}