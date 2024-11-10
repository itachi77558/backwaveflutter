<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    public $timestamps = false; // Désactive les timestamps automatiques

    protected $fillable = [
        'user_id', 'code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
