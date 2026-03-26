<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
class AuthController extends Controller
{
    /**
     * تسجيل مستخدم جديد (لـ Parent و Content Creator فقط).
     */
    public function register(Request $request)
    {
        // 1. قواعد التحقق (Validation Rules)
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'national_id' => 'required|string|unique:users,national_id',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'user_type' => ['required', Rule::in(['parent', 'content_creator', 'content_auditor'])],
            'phone_number' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:1',
            'education_level' => 'nullable|string|max:255',
            // التحقق من ملف الصورة الجديد
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // 2. معالجة رفع الصورة (إذا وجدت)
        $imageUrl = null;
        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = public_path('uploads/users');

            // التأكد من وجود المجلد
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $file->move($path, $fileName);
            $imageUrl = asset('uploads/users/' . $fileName);
        }

        // 3. إنشاء المستخدم مع مراعاة الحقول الجديدة
        $user = User::create([
            'first_name' => $validatedData['first_name'],
            'father_name' => $validatedData['father_name'],
            'last_name' => $validatedData['last_name'],
            'national_id' => $validatedData['national_id'],
            'user_type' => $validatedData['user_type'],
            'email' => $validatedData['email'],
            'phone_number' => $validatedData['phone_number'],
            'age' => $validatedData['age'],
            'education_level' => $validatedData['education_level'],
            'password' => Hash::make($validatedData['password']),

            // الإضافات الجديدة:
            'image_url' => $imageUrl,
            'account_status' => 'pending', // أي تسجيل جديد يكون بانتظار المراجعة
            'is_active' => true,       // الحساب مفعل تقنياً لكنه معلق إدارياً
        ]);

        // 4. توليد التوكن
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح وهو بانتظار مراجعة الإدارة.',
            'user' => $user->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'user_type',
                'account_status',
                'image_url'
            ]),
            'token' => $token
        ], 201);
    }
    /**
     * تسجيل الدخول الموحد (Unified Login): 
     * - يستخدم الإيميل وكلمة المرور للبالغين.
     * - يستخدم الرقم الوطني (national_id) و PIN للأطفال.
     */
    public function login(Request $request)
    {
        // 1. التحقق المبدئي من البيانات المرسلة
        $validatedData = $request->validate([
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|string',
            'pin' => 'nullable|string',
        ]);

        $user = null;
        $isAuthenticated = false;

        // ------------------------------------------------------------------
        // أ. محاولة تسجيل الدخول كـ (بالغ): باستخدام الإيميل وكلمة المرور
        // ------------------------------------------------------------------
        if (!empty($validatedData['email']) && !empty($validatedData['password'])) {
            $user = User::where('email', $validatedData['email'])->first();

            if ($user && Hash::check($validatedData['password'], $user->password)) {
                // تأكد أن المستخدم ليس طفلاً إذا كان يستخدم هذا المسار
                if ($user->user_type !== 'child') {
                    $isAuthenticated = true;
                }
            }
        }

        // ------------------------------------------------------------------
        // ب. محاولة تسجيل الدخول كـ (طفل): باستخدام الرقم الوطني و PIN
        // ------------------------------------------------------------------
        elseif (!empty($validatedData['pin']) && !empty($validatedData['password'])) {
            // 1. البحث عن الطفل الذي يمتلك هذا الـ PIN
            // ملاحظة: إذا كان الـ PIN غير فريد، يفضل البحث بالاثنين معاً أو التأكد من فرادته عند الإنشاء
            $user = User::where('pin', $validatedData['pin'])
                ->where('user_type', 'child')
                ->first();

            // 2. التحقق من وجود المستخدم ومطابقة كلمة المرور المشفرة
            if ($user && Hash::check($validatedData['password'], $user->password)) {
                $isAuthenticated = true;
            } else {
                return response()->json(['message' => 'رمز PIN أو كلمة المرور غير صحيحة'], 401);
            }
        }

        // ------------------------------------------------------------------
        // 2. التحقق من النتيجة النهائية
        // ------------------------------------------------------------------
        if (!$isAuthenticated || !$user) {
            return response()->json(['message' => 'Invalid credentials or login method not supported.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated. Please contact support.'], 403);
        }
        if ($user->account_status == 'pending' && $user->user_type == 'content_creator') {
            return response()->json(['message' => 'حسابك قيد المراجعة من قبل الادمن.'], 403);
        }
        // 3. توليد التوكن
        $user->tokens()->delete();
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * تسجيل دخول الأطفال باستخدام PIN.
     */
    // public function loginChild(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'pin' => 'required|string',
    //         'national_id' => 'required|string', // يمكن استخدام الرقم الوطني للتحقق
    //     ]);

    //     $child = User::where('national_id', $validatedData['national_id'])
    //         ->where('user_type', 'child')
    //         ->where('pin', $validatedData['pin'])
    //         ->where('is_active', true)
    //         ->first();

    //     if (!$child) {
    //         return response()->json(['message' => 'Invalid PIN or National ID'], 401);
    //     }

    //     $child->tokens()->delete();
    //     $token = $child->createToken('CHILD_TOKEN')->plainTextToken;

    //     return response()->json([
    //         'user' => $child->only(['first_name', 'last_name', 'user_type', 'parent_id']),
    //         'token' => $token
    //     ]);
    // }

    /**
     * تسجيل الخروج (يتطلب التوكن).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Successfully logged out']);
    }
}