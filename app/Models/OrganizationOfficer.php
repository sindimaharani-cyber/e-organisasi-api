<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationOfficer extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'position',
        'division',
        'management_period',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}