<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'learning_content_id',
        'question_text'
    ];

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}