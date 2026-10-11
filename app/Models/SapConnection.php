<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SapConnection extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
        'is_active',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
            'password' => 'encrypted',
        ];
    }
}
