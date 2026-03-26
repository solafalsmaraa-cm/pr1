<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningContent extends Model
{
    protected $fillable = ['learning_path_id', 'course_name', 'title', 'description', 'content_type', 'rating', 'order'];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function packages()
    {
        return $this->hasMany(ContentPackage::class);
    }
    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    // دالة لتحديث المتوسط في جدول المحتوى تلقائياً
    public function updateAverageRating()
    {
        $this->rating = $this->ratings()->avg('stars') ?: 0;
        $this->save();
    }
}
