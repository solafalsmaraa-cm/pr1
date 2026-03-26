<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    // السماح بتعبئة الحقول
    protected $fillable = [
        'quiz_attempt_id',
        'question_id',
        'answer_id',
        'is_correct'
    ];

    // العلاقة مع المحاولة الإجمالية
    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    // العلاقة مع السؤال (لمعرفة أي سؤال هذه إجابته)
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    // العلاقة مع الإجابة (لمعرفة النص الذي اختاره المستخدم)
    public function answer()
    {
        return $this->belongsTo(Answer::class);
    }
}