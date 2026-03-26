<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class QuizAttempt extends Model
{
    protected $fillable = ['user_id', 'learning_content_id', 'score', 'correct_count'];

    public function details()
    {
        return $this->hasMany(UserAnswer::class);
    }
    public function learningContent()
    {
        // نربط المحاولة بالمحتوى التعليمي (الكورس) لنعرف اسم الكورس
        return $this->belongsTo(LearningContent::class, 'learning_content_id');
    }
}
