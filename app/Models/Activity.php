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

        /*
         * Sertifikat
         */
        'certificate_template_path',
        'certificate_name_x',
        'certificate_name_y',
        'certificate_font_size',
        'certificate_font_color',
    ];

    protected $casts = [
        'activity_date' => 'date',

        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',

        'completed_at' => 'datetime',

        'registration_open' => 'boolean',

        'quota' => 'integer',

        /*
         * Konfigurasi sertifikat
         */
        'certificate_name_x' => 'integer',
        'certificate_name_y' => 'integer',
        'certificate_font_size' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
            'id'
        );
    }
    
}