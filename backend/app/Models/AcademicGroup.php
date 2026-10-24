<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicGroup extends Model
{
    protected $guarded = ['id'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments');
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }
}
