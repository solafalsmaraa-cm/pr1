<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EducationalPath;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EducationalPathController extends Controller
{
    /**
     * عرض جميع المسارات مع الروابط الكاملة للصور
     */
    public function index()
    {
        $paths = EducationalPath::with(['creator:id,first_name,last_name', 'contents.packages', 'contents.questions.answers'])->withCount('contents')->get();
        return response()->json($paths);
    }



    /**
     * عرض جميع المسارات الموافق عليها الروابط الكاملة للصور
     */
    public function publishedPaths()
    {
        $paths = EducationalPath::where('status','published')->with(['creator:id,first_name,last_name', 'contents.packages', 'contents.questions.answers'])->withCount('contents')->get();
        return response()->json($paths);
    }
    /**
     * إضافة مسار جديد وتخزين الصورة في public/uploads/paths
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $image = $request->file('photo');
            $imageName = time() . '.' . $image->getClientOriginalExtension();

            // تحديد المسار داخل مجلد public/uploads/educational_paths
            $destinationPath = public_path('uploads/educational_paths');

            // التأكد من وجود المجلد، وإذا لم يوجد يتم إنشاؤه
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0777, true, true);
            }

            // نقل الصورة للمجلد العام
            $image->move($destinationPath, $imageName);

            // تخزين المسار النسبي في قاعدة البيانات
            $validatedData['photo'] = 'uploads/educational_paths/' . $imageName;
        }

        $validatedData['creator_id'] = $request->user()->id;
        $validatedData['status'] = 'pending';

        $educationalPath = EducationalPath::create($validatedData);

        return response()->json([
            'message' => 'تم إنشاء المسار وتخزين الصورة في المجلد العام بنجاح',
            'data' => $educationalPath
        ], 201);
    }

    /**
     * تحديث المسار وحذف الصورة القديمة من public
     */
    public function update(Request $request, $id)
    {
        $educationalPath = EducationalPath::findOrFail($id);

        $validatedData = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            // 1. حذف الصورة القديمة من مجلد public إذا وجدت
            if ($educationalPath->photo) {
                $oldImagePath = public_path($educationalPath->photo);
                if (File::exists($oldImagePath)) {
                    File::delete($oldImagePath);
                }
            }

            // 2. رفع الصورة الجديدة
            $image = $request->file('photo');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('uploads/educational_paths');
            $image->move($destinationPath, $imageName);

            $validatedData['photo'] = 'uploads/educational_paths/' . $imageName;
        }

        $educationalPath->update($validatedData);

        return response()->json([
            'message' => 'تم التحديث بنجاح',
            'data' => $educationalPath
        ]);
    }

    /**
     * حذف المسار مع حذف صورته من المجلد العام
     */
    public function destroy(Request $request, $id)
    {
        $path = EducationalPath::findOrFail($id);

        if ($path->photo) {
            $imagePath = public_path($path->photo);
            if (File::exists($imagePath)) {
                File::delete($imagePath);
            }
        }

        $path->delete();
        return response()->json(['message' => 'تم حذف المسار والصورة بنجاح']);
    }


    public function getMyPaths()
    {
        $user = auth()->user();
        $paths = EducationalPath::where(['creator_id' => $user->id])->get();
        return response()->json( $paths);
    }



    public function reviewPath(Request $request, $id)
    {
        // 1. التحقق من المدخلات (يجب أن تكون الحالة إما قبول أو رفض)
        $request->validate([
            'status' => 'required|in:published,rejected',
        ]);

        // 2. البحث عن المسار التعليمي
        $path = EducationalPath::findOrFail($id);

        // 3. تحديث الحالة ومعرف المراجع (الآدمن الحالي)
        $path->update([
            'status' => $request->status,
            'auditor_id' => auth()->id(), // تخزين ID الآدمن الذي قام بالمراجعة
        ]);

        return response()->json([
            'message' => 'تم تحديث حالة المسار بنجاح إلى ' . $request->status,
            'path' => $path
        ], 200);
    }
}