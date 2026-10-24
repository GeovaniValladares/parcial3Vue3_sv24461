<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function group()
    {
        return $this->belongsTo(AcademicGroup::class, 'academic_group_id');
    }
}
