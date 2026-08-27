<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Certificate extends Model
{
    use HasFactory;

    protected $table = 'certificates';

    protected $fillable = [
        'activity_id',
        'user_id',
        'issued_by',
        'certificate_number',
        'title',
        'description',
        'file_path',
        'issued_at',
        'status',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    protected $appends = [
        'file_url',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(
            Activity::class,
            'activity_id',
            'id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id'
        );
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'issued_by',
            'id'
        );
    }

    public function getFileUrlAttribute(): ?string
    {
        if (
            $this->file_path === null ||
            trim($this->file_path) === ''
        ) {
            return null;
        }

        return url(
            Storage::url(
                $this->file_path
            )
        );
    }
}