<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPackage extends Model
{
    protected $fillable = [
        'learning_content_id',
        'title',
        'url',
        'content'
    ];
}
