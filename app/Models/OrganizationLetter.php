<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'letter_number',
        'subject',
        'type',
        'letter_date',
        'sender',
        'recipient',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'letter_date' => 'date',
        'file_size' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }
}