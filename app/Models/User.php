<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Notifiable,HasApiTokens;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone_number', 'password', 'is_phone_verified',
    ];

    protected $hidden = [
        'password',
    ];

    public function verificationCode()
    {
        return $this->hasOne(VerificationCode::class);
    }
}
