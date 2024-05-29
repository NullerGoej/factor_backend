<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class Phone extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'device',
        'ip_address',
        'user_id',
        'two_factor_secret',
        'two_factor_setup',
        'two_factor_6_digit',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'two_factor_secret',
        'two_factor_6_digit',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'device' => 'string',
            'ip_address' => 'string',
            'user_id' => 'integer',
            'two_factor_setup' => 'integer',
            'two_factor_secret' => 'string',
            'two_factor_6_digit' => 'string',
        ];
    }
}