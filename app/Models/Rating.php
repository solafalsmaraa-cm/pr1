<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = ['user_id', 'learning_content_id', 'stars', 'comment'];

    public function learningContent()
    {
        return $this->belongsTo(LearningContent::class);
    }
}
