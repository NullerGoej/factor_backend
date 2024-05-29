<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwoFactorRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'unique_id',
        'accepted',
        'ip_address',
        'action',
        'device_id',
    ];

    public function phone()
    {
        return $this->belongsTo(Phone::class, 'device_id');
    }

    protected function casts(): array
    {
        return [
            'unique_id' => 'string',
            'accepted' => 'boolean',
            'ip_address' => 'string',
            'action' => 'string',
            'device_id' => 'integer',
        ];
    }
}