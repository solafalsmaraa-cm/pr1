<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPackage;
use App\Models\LearningContent;
use App\Models\Question;
use Illuminate\Http\Request;

class ContentPackageController extends Controller
{
    // 1. إضافة رابط يوتيوب جديد
    public function store(Request $request)
    {
        $validated = $request->validate([
            'learning_content_id' => 'required|exists:learning_contents,id',
            'title' => 'required|string|max:255',
            'url' => 'required|url', // يجب أن يكون رابطاً صالحاً
            'content' => 'nullable|string'
        ]);

        $package = ContentPackage::create($validated);

        return response()->json([
            'message' => 'تم إضافة رابط الفيديو بنجاح',
            'data' => $package
        ], 201);
    }

    // 2. تعديل رابط أو عنوان الفيديو
    public function update(Request $request, $id)
    {
        $package = ContentPackage::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'url' => 'sometimes|url',
            'content' => 'nullable|string'
        ]);

        $package->update($validated);

        return response()->json([
            'message' => 'تم تحديث البيانات بنجاح',
            'data' => $package
        ]);
    }

    // 3. حذف الفيديو
    public function destroy($id)
    {
        $package = ContentPackage::findOrFail($id);
        $package->delete();

        return response()->json(['message' => 'تم حذف الفيديو من المحتوى']);
    }

    public function pathVideos($id)
    {
        $packages = (LearningContent::findOrFail($id))->packages;

        return response()->json(['data' => $packages]);
    }




    public function courseQuestions($id)
    {
        // نقوم بجلب المحتوى التعليمي مع تحميل الأسئلة والإجابات المرتبطة بكل سؤال
        $content = LearningContent::with('questions.answers')->findOrFail($id);

        return response()->json([
            'data' => $content->questions
        ]);
    }



    // 3. حذف الفيديو
    public function deleteQuestion($id)
    {
        $package = Question::findOrFail($id);
        $package->delete();

        return response()->json(['message' => 'تم حذف السؤال من المحتوى']);
    }


    public function storeSingleQuestion(Request $request)
    {
        // 1. التحقق من صحة البيانات القادمة
        $validated = $request->validate([
            'learning_content_id' => 'required|exists:learning_contents,id',
            'question_text' => 'required|string',
            'answers' => 'required|array|min:1',
            'answers.*.answer_text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
        ]);

        // 2. جلب الكورس أو المحتوى التعليمي
        $content = LearningContent::findOrFail($request->learning_content_id);

        // 3. حفظ السؤال
        $question = $content->questions()->create([
            'question_text' => $request->question_text
        ]);

        // 4. حفظ الإجابات المرتبطة به
        foreach ($request->answers as $aData) {
            $question->answers()->create([
                'answer_text' => $aData['answer_text'],
                'is_correct' => $aData['is_correct']
            ]);
        }

        return response()->json([
            'message' => 'تم حفظ السؤال وإجاباته بنجاح',
            'data' => $question->load('answers')
        ], 201);
    }
}