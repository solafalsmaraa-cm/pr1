<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'learning_content_id' => 'required|exists:learning_contents,id',
            'stars' => 'required|integer|min:1|max:5',
        ]);

        $rating = Rating::updateOrCreate(
            ['user_id' => auth()->id(), 'learning_content_id' => $request->learning_content_id],
            ['stars' => $request->stars]
        );

        $rating->learningContent->updateAverageRating();

        return response()->json(['status' => 'success', 'average' => $rating->learningContent->rating]);
    }
}
