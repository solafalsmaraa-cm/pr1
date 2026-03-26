<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentPackageController;
use App\Http\Controllers\Api\EducationalPathController;
use App\Http\Controllers\Api\LearningContentController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// مسارات المصادقة العامة (لا تحتاج توكن)
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
// Route::post('login/child', [AuthController::class, 'loginChild']); // دخول الأطفال بالـ PIN

// المسارات التي تتطلب مصادقة (يجب إرسال التوكن مع الطلب)
Route::middleware('auth:sanctum')->group(function () {
    //قبول او رفض المستخدم
    Route::patch('/users/{id}/update-status', [UserController::class, 'updateStatus']);

    Route::post('/rate-content', [RatingController::class, 'store']);

    Route::post('/quiz/submit', [QuizController::class, 'submitQuiz']);


    Route::get('/child/{id}/results', [QuizController::class, 'getChildResults']);
    // تسجيل الخروج
    Route::post('logout', [AuthController::class, 'logout']);

    // إدارة المستخدمين (متحكم UserController)
    Route::prefix('users')->group(function () {

        // جلب قائمة المستخدمين (تختلف حسب الدور: Admin, Parent, Auditor)
        Route::get('/', [UserController::class, 'index']);

        // إنشاء مستخدم (يستخدم لإنشاء الأطفال، أو المراقبين بواسطة المدير)
        Route::post('/', [UserController::class, 'store']);

        // جلب جميع مراقبي المحتوى (لربط صانع محتوى بهم)
        Route::get('auditors', [UserController::class, 'listAuditors']);

        // جلب جميع صناع المحتوى (للمدير)
        Route::get('creators', [UserController::class, 'listCreators']);
        // جلب جميع صناع المحتوى (للمدير)
        Route::get('parents', [UserController::class, 'listParents']);

        // جلب جميع صناع المحتوى (للمدير)
        Route::get('childs', [UserController::class, 'listChilds']);






        // ربط صانع محتوى بمراقب (Admin)
        Route::post('{user}/link-supervisor', [UserController::class, 'linkSupervisor']);

        // تحديث وتفعيل/إلغاء تفعيل المستخدمين (Admin)
        Route::put('{user}', [UserController::class, 'update']);

        // حذف مستخدم (Admin)
        Route::delete('{user}', [UserController::class, 'destroy']);




        // --- مسارات الأب (إدارة الأبناء) ---
        Route::get('/my-children', [UserController::class, 'getMyChildren']);      // عرض أبنائي
        Route::put('/my-children/{id}', [UserController::class, 'updateChild']);    // تعديل بيانات ابن
        Route::delete('/my-children/{id}', [UserController::class, 'destroyChild']); // حذف حساب ابن






    });

    // --- روابط المسارات التعليمية ---
    Route::get('/educational-paths1', [EducationalPathController::class, 'publishedPaths']);

    Route::get('/educational-paths', [EducationalPathController::class, 'index']);
    Route::post('/educational-paths', [EducationalPathController::class, 'store']);

    // ملاحظة: استخدم POST مع _method=PUT في Postman عند تحديث الصور
    Route::post('/educational-paths/{id}', [EducationalPathController::class, 'update']);

    Route::delete('/educational-paths/{id}', [EducationalPathController::class, 'destroy']);

    // يمكنك إضافة رابط خاص لعرض مسار واحد فقط إذا أردت
    // Route::get('/educational-paths/{id}', [EducationalPathController::class, 'show']);




    // إضافة محتوى شامل
    Route::post('/learning-contents', [LearningContentController::class, 'store']);

    // تعديل محتوى (نستخدم POST مع _method=PUT لرفع الفيديوهات)
    Route::post('/learning-contents/{id}', [LearningContentController::class, 'update']);

    // حذف محتوى
    Route::delete('/learning-contents/{id}', [LearningContentController::class, 'destroy']);

    // جلب كافة محتويات مسار معين (دروس، فيديوهات، أسئلة)
    Route::get('/educational-paths/{id}/all-contents', [LearningContentController::class, 'showPathContents']);

    Route::post('/content-packages', [ContentPackageController::class, 'store']);
    Route::delete('/content-packages/{id}', [ContentPackageController::class, 'destroy']);
    Route::put('/content-packages/{id}', [ContentPackageController::class, 'update']);



    Route::get('/courses_videos/{id}', [ContentPackageController::class, 'pathVideos']);
    // جلب جميع المسارات الخاصة بالشخص
    Route::get('/get-my-paths', [EducationalPathController::class, 'getMyPaths']);


    Route::get('/courses_videos/{id}', [ContentPackageController::class, 'pathVideos']);
    // جلب جميع المسارات الخاصة بالشخص
    Route::get('/courses_questions/{id}', [ContentPackageController::class, 'courseQuestions']);

    Route::delete('/courses_questions/{id}', [ContentPackageController::class, 'deleteQuestion']);
    Route::post('/courses_questions', [ContentPackageController::class, 'storeSingleQuestion']);



    // تحديث حالة المسار التعليمي
    Route::post('/educational-paths/{id}/review', [EducationalPathController::class, 'reviewPath']);

});

