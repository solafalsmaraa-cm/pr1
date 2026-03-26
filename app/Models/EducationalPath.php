<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationalPath extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'photo',
        'number_of_courses',
        'status',
        'creator_id',
        'auditor_id',
    ];
    protected $appends = ['dynamic_number_of_courses'];

    // 2. دالة الـ Accessor لحساب العدد
    public function getDynamicNumberOfCoursesAttribute()
    {
        // تقوم بحساب عدد الصفوف في علاقة contents
        return $this->contents()->count();
    }
    // علاقة المسار بصانع المحتوى
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    // علاقة المسار بالمراقب
    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
    public function contents()
    {
        return $this->hasMany(LearningContent::class, 'learning_path_id');
    }

}