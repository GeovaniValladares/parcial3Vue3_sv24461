<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    public const CAREER = 'Licenciatura en ciencias de la computacion';

    protected $guarded = ['id'];

    protected $appends = ['totp_confirmed_at'];

    protected $hidden = ['user'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTotpConfirmedAtAttribute()
    {
        return $this->user?->totp_confirmed_at;
    }

    public function groups()
    {
        return $this->belongsToMany(AcademicGroup::class, 'enrollments');
    }
}
