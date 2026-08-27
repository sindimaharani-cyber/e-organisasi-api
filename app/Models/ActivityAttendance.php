<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityAttendance extends Model
{
    use HasFactory;

    protected $table = 'activity_attendances';

    protected $fillable = [
        'activity_id',
        'user_id',
        'attendance_status',
        'attended_at',
        'verified_by',
        'notes',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}