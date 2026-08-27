<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $table = 'members';

    protected $fillable = [
        'user_id',
        'nim',
        'name',
        'email',
        'phone',
        'study_program',
        'generation',
        'gpa',
        'position',
        'division',
        'service_period',
        'address',
        'member_status',
        'profile_photo',
    ];

    /*
    |--------------------------------------------------------------------------
    | CAST
    |--------------------------------------------------------------------------
    |
    | IPK dikirim ke Flutter sebagai angka.
    |
    */

    protected $casts = [
        'gpa' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id'
        );
    }
}