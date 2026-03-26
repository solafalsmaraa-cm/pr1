<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningContent;
use App\Models\ContentPackage;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LearningContentController extends Controller
{



    /**
     * عرض المسار التعليمي مع كامل المحتويات والأسئلة التابعة له
     */
    public function showPathContents($pathId)
    {
        $userId = auth()->id(); // جلب معرف المستخدم الحالي

        $path = \App\Models\EducationalPath::with([
            'contents' => function ($query) use ($userId) {
                $query->orderBy('order', 'asc')
                    ->withCount('ratings'); // جلب عدد الأشخاص الذين قيموا كل درس
            },
            'contents.packages',
            'contents.questions.answers',
            // جلب تقييم المستخدم الحالي فقط لهذا الدرس إن وجد
            'contents.ratings' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }
        ])->find($pathId);

        if (!$path) {
            return response()->json(['message' => 'المسار التعليمي غير موجود'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $path
        ]);
    }
    /**
     * 1. إضافة محتوى شامل (درس + فيديو + أسئلة)
     */
    public function store(Request $request)
    {
        $request->validate([
            'learning_path_id' => 'required|exists:educational_paths,id',
            'course_name' => 'required|string',
            'title' => 'required|string',
            'content_type' => 'required|string',
        ]);

        return DB::transaction(function () use ($request) {
            // إنشاء المحتوى الأساسي
            $content = LearningContent::create($request->only([
                'learning_path_id',
                'course_name',
                'title',
                'description',
                'content_type',
                'order'
            ]));



            // إضافة الأسئلة والأجوبة
            if ($request->has('questions')) {
                $this->saveQuestionsAndAnswers($content, $request->questions);
            }

            return response()->json(['message' => 'تم الحفظ بنجاح', 'data' => $content->load('packages', 'questions.answers')], 201);
        });
    }

    /**
     * 2. تعديل محتوى (تحديث البيانات أو استبدال الفيديو)
     */
    public function update(Request $request, $id)
    {
        $content = LearningContent::findOrFail($id);

        return DB::transaction(function () use ($request, $content) {
            $content->update($request->only(['course_name', 'title', 'description', 'content_type', 'order']));

            // تحديث الفيديو
            if ($request->hasFile('video_file')) {
                // حذف الفيديو القديم
                foreach ($content->packages as $package) {
                    if (File::exists(public_path($package->url))) {
                        File::delete(public_path($package->url));
                    }
                    $package->delete();
                }

                // رفع الجديد
                $file = $request->file('video_file');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/videos'), $fileName);

                $content->packages()->create([
                    'title' => $request->video_title ?? $content->title,
                    'url' => 'uploads/videos/' . $fileName,
                ]);
            }

            // تحديث الأسئلة (نقوم بحذف القديمة وإضافة الجديدة للتبسيط)
            if ($request->has('questions')) {
                $content->questions()->delete();
                $this->saveQuestionsAndAnswers($content, $request->questions);
            }

            return response()->json(['message' => 'تم التحديث بنجاح', 'data' => $content->load('packages', 'questions.answers')]);
        });
    }

    /**
     * 3. حذف محتوى (مع ملفات الفيديو التابعة له)
     */
    public function destroy($id)
    {
        $content = LearningContent::findOrFail($id);

        // حذف ملفات الفيديو من القرص
        foreach ($content->packages as $package) {
            if (File::exists(public_path($package->url))) {
                File::delete(public_path($package->url));
            }
        }

        $content->delete(); // سيحذف الأسئلة والأجوبة تلقائياً بسبب onDelete('cascade')

        return response()->json(['message' => 'تم حذف المحتوى وجميع ملفاته بنجاح']);
    }

    // دالة مساعدة لحفظ الأسئلة
    private function saveQuestionsAndAnswers($content, $questionsInput)
    {
        // تحويل النص الجاي من بوست مان إلى مصفوفة PHP
        $questionsData = is_string($questionsInput) ? json_decode($questionsInput, true) : $questionsInput;

        if (is_array($questionsData)) {
            foreach ($questionsData as $qData) {
                $question = $content->questions()->create([
                    'question_text' => $qData['question_text']
                ]);

                foreach ($qData['answers'] as $aData) {
                    $question->answers()->create([
                        'answer_text' => $aData['answer_text'],
                        'is_correct' => $aData['is_correct']
                    ]);
                }
            }
        }
    }
}