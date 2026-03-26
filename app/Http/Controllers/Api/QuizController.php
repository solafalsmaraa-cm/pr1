<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\Answer;
use App\Models\QuizAttempt;
use App\Models\UserAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    public function submitQuiz(Request $request)
    {
        // 1. التحقق من البيانات المرسلة من تطبيق فلاتر
        $request->validate([
            'learning_content_id' => 'required|exists:learning_contents,id',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer_id' => 'required|exists:answers,id',
        ]);

        $userId = Auth::id(); // جلب ID المستخدم المسجل حالياً
        $contentId = $request->learning_content_id;
        $userSubmissions = $request->answers;

        // 2. جلب الأسئلة الفعلية المرتبطة بهذا المحتوى لحساب العدد الكلي
        $questions = Question::where('learning_content_id', $contentId)->get();
        $totalQuestionsCount = $questions->count();

        if ($totalQuestionsCount == 0) {
            return response()->json(['message' => 'لا توجد أسئلة لهذا المحتوى'], 404);
        }

        // استخدام Transaction لضمان حفظ كل البيانات أو عدم حفظ شيء في حال حدوث خطأ
        return DB::transaction(function () use ($userId, $contentId, $userSubmissions, $totalQuestionsCount) {

            $correctAnswersCount = 0;
            $resultsDetail = [];

            // 3. مقارنة كل إجابة أرسلها المستخدم بالإجابة الصحيحة في قاعدة البيانات
            foreach ($userSubmissions as $submission) {
                $isCorrect = Answer::where('id', $submission['answer_id'])
                    ->where('question_id', $submission['question_id'])
                    ->where('is_correct', true)
                    ->exists();

                if ($isCorrect) {
                    $correctAnswersCount++;
                }

                // تخزين النتيجة المؤقتة لكل سؤال
                $resultsDetail[] = [
                    'question_id' => $submission['question_id'],
                    'answer_id' => $submission['answer_id'],
                    'is_correct' => $isCorrect,
                ];
            }

            // 4. الحسابية: النتيجة من 100
            // المعادلة: (عدد الإجابات الصحيحة / عدد الأسئلة الكلي) * 100
            $finalScore = ($correctAnswersCount / $totalQuestionsCount) * 100;

            // 5. تخزين المحاولة الكلية (QuizAttempt)
            $attempt = QuizAttempt::create([
                'user_id' => $userId,
                'learning_content_id' => $contentId,
                'score' => round($finalScore, 2),
                'correct_count' => $correctAnswersCount,
            ]);

            // 6. تخزين تفاصيل إجابات المستخدم (UserAnswer)
            foreach ($resultsDetail as $detail) {
                UserAnswer::create([
                    'quiz_attempt_id' => $attempt->id,
                    'question_id' => $detail['question_id'],
                    'answer_id' => $detail['answer_id'],
                    'is_correct' => $detail['is_correct'],
                ]);
            }

            // 7. إرجاع النتيجة النهائية للتطبيق
            return response()->json([
                'status' => 'success',
                'message' => 'تم تصحيح الاختبار وحفظ النتيجة',
                'data' => [
                    'score' => round($finalScore, 2), // العلامة من 100
                    'correct_count' => $correctAnswersCount,
                    'total_questions' => $totalQuestionsCount,
                    'attempt_id' => $attempt->id
                ]
            ], 200);
        });
    }

    public function getChildResults($childId)
    {
        // جلب نتائج الطفل مع اسم الكورس المرتبط بكل نتيجة
        $results = QuizAttempt::where('user_id', $childId)
            ->with('learningContent:id,title') // جلب id وعنوان الكورس فقط لتخفيف البيانات
            ->orderBy('created_at', 'desc')
            ->get();
        $averageScore = QuizAttempt::where('user_id', $childId)->avg('score');
        $completedCourses = QuizAttempt::where('user_id', $childId)->count();
        return response()->json([
            'status' => 'success',
            'data' => $results,
            'averageScore' => $averageScore,
            'completedCourses' => $completedCourses
        ]);
    }
}