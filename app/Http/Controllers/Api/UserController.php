<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class UserController extends Controller
{
    /**
     * جلب قائمة المستخدمين بناءً على دور المستخدم الحالي. (System Admin / Parent / Auditor)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSystemAdministrator()) {
            // المدير: جلب جميع المستخدمين باستثناء الأطفال (لمنع الازدحام)
            $users = User::where('user_type', '!=', 'child')->paginate(20);
        } elseif ($user->isParent()) {
            // الأب: جلب جميع الأبناء (الأطفال) فقط
            $users = $user->children()->paginate(20);
        } elseif ($user->isContentAuditor()) {
            // المراقب: جلب جميع صناع المحتوى المسؤول عنهم فقط
            $users = $user->supervisedCreators()
                ->where('user_type', 'content_creator')
                ->paginate(20);
        } else {
            // منع وصول أي دور آخر إلى هذه القائمة
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json($users);
    }



    /**
     * إنشاء حساب جديد (Admin / Parent).
     */
    public function store(Request $request)
    {
        $currentUser = $request->user();
        $isCurrentUserParent = $currentUser->isParent();

        // 1. قواعد التحقق (Validation Rules)
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'national_id' => 'required|string|unique:users,national_id',
            'age' => 'nullable|integer|min:1',
            'education_level' => 'nullable|string|max:255',
            'user_type' => ['required', Rule::in(['parent', 'content_creator', 'content_auditor', 'child'])],

            // الحقول الجديدة المضافة للتحقق
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'account_status' => ['nullable', Rule::in(['pending', 'accepted', 'rejected'])],

            'email' => 'required_if:user_type,parent,content_creator,content_auditor|nullable|email|unique:users,email',
            'password' => 'required|string|min:8',

            'parent_id' => [
                'nullable',
                Rule::requiredIf(function () use ($request, $isCurrentUserParent) {
                    return $request->user_type === 'child' && !$isCurrentUserParent;
                }),
                'exists:users,id',
            ],
            'pin' => 'sometimes|nullable|string|min:4',
            'supervisor_id' => 'nullable|exists:users,id',
        ]);

        // 2. التحقق من الصلاحيات قبل الإنشاء
        $typeToCreate = $validatedData['user_type'];

        if ($typeToCreate === 'child' && !$isCurrentUserParent) {
            return response()->json(['message' => 'Only parents can create child accounts.'], 403);
        }

        if ($typeToCreate === 'content_auditor' && !$currentUser->isSystemAdministrator()) {
            return response()->json(['message' => 'Only system administrators can create auditors.'], 403);
        }

        if (in_array($typeToCreate, ['parent', 'content_creator']) && !$currentUser->isSystemAdministrator()) {
            return response()->json(['message' => 'Unauthorized to create this user type.'], 403);
        }

        // 3. معالجة رفع الصورة إلى مجلد public/uploads/users
        $imageUrl = null;
        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            // التأكد من وجود المجلد، وإنشائه إذا لم يوجد
            $path = public_path('uploads/users');
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
            $file->move($path, $fileName);
            $imageUrl = asset('uploads/users/' . $fileName);
        }

        // 4. تجهيز البيانات الأساسية
        $userData = [
            'first_name' => $validatedData['first_name'],
            'father_name' => $validatedData['father_name'],
            'last_name' => $validatedData['last_name'],
            'mother_name' => $validatedData['mother_name'] ?? null,
            'national_id' => $validatedData['national_id'],
            'user_type' => $typeToCreate,
            'age' => $validatedData['age'] ?? null,
            'education_level' => $validatedData['education_level'] ?? null,
            'is_active' => true,
            'image_url' => $imageUrl, // الرابط الجديد
            'account_status' => $validatedData['account_status'] ?? 'pending', // الحالة الجديدة
        ];

        // 5. تخصيص الحقول حسب نوع المستخدم
        if ($typeToCreate === 'child') {
            $userData['pin'] = $validatedData['pin'] ?? strval(mt_rand(1000, 9999));
            $userData['parent_id'] = $isCurrentUserParent ? $currentUser->id : $validatedData['parent_id'];
            $userData['password'] = Hash::make($validatedData['password']);

            // توليد إيميل تلقائي للأطفال
            $safeName = Str::slug($validatedData['first_name']);
            $userData['email'] = $safeName . '-' . substr($validatedData['national_id'], -4) . '@child.app';
        } else {
            $userData['email'] = $validatedData['email'];
            $userData['password'] = Hash::make($validatedData['password']);
            $userData['supervisor_id'] = $validatedData['supervisor_id'] ?? null;
        }

        // 6. الحفظ في قاعدة البيانات
        $user = User::create($userData);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user
        ], 201);
    }

    /**
     * تحديث بيانات المستخدم (تحديث الحالة، الصورة، وكلمة المرور).
     */
    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();

        // السماح فقط للمدير بالتعديل على الآخرين
        if (!$currentUser->isSystemAdministrator()) {
            return response()->json(['message' => 'Only administrators can update users.'], 403);
        }

        $validatedData = $request->validate([
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
            'account_status' => ['sometimes', Rule::in(['pending', 'accepted', 'rejected'])],
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // تحديث كلمة المرور إذا أُرسلت
        if ($request->filled('password')) {
            $user->password = Hash::make($validatedData['password']);
        }

        // تحديث الحالة والنشاط
        if (isset($validatedData['is_active'])) {
            $user->is_active = $validatedData['is_active'];
        }
        if (isset($validatedData['account_status'])) {
            $user->account_status = $validatedData['account_status'];
        }

        // تحديث الصورة ومعالجة القديمة
        if ($request->hasFile('image_file')) {
            // حذف الصورة القديمة من السيرفر إذا كانت موجودة
            if ($user->image_url) {
                $oldPath = public_path(str_replace(asset(''), '', $user->image_url));
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $file = $request->file('image_file');
            $fileName = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/users'), $fileName);
            $user->image_url = asset('uploads/users/' . $fileName);
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user
        ]);
    }

    /**
     * إنشاء حساب جديد (Admin / Parent).
     */
    public function store1(Request $request)
    {
        $currentUser = $request->user();

        // تحديد حالة المستخدم الحالي للمساعدة في قواعد التحقق
        $isCurrentUserParent = $currentUser->isParent();

        // 1. قواعد التحقق (Validation Rules)
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255', // **تم التأكد من وجوده في التحقق**
            'last_name' => 'required|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'national_id' => 'required|string|unique:users,national_id',
            'age' => 'nullable|integer|min:1',
            'education_level' => 'nullable|string|max:255',

            // أنواع المستخدمين المسموح بها
            'user_type' => ['required', Rule::in(['parent', 'content_creator', 'content_auditor', 'child'])],

            // حقول خاصة بالبالغين فقط
            'email' => 'required_if:user_type,parent,content_creator,content_auditor|nullable|email|unique:users,email',
            'password' => 'required|string|min:8',

            // حقول خاصة بالأطفال/الـ References
            'parent_id' => [
                'nullable',
                // مطلوب فقط إذا كان طفلاً والمستخدم الحالي ليس الأب (أي المدير هو من ينشئ الحساب)
                Rule::requiredIf(function () use ($request, $isCurrentUserParent) {
                    return isset($validatedData['user_type']) && $request['user_type'] === 'child' && !$isCurrentUserParent;
                }),
                'exists:users,id',
            ],
            'pin' => 'sometimes|nullable|string|min:4',

            // حقول خاصة بمدير النظام
            'supervisor_id' => 'nullable|exists:users,id',
        ]);

        // 2. التحقق من الصلاحيات قبل الإنشاء
        $typeToCreate = $validatedData['user_type'];

        // الصلاحيات: الأب ينشئ طفل، المدير ينشئ مراقب
        if ($typeToCreate === 'child' && !$isCurrentUserParent) {
            return response()->json(['message' => 'Only parents can create child accounts.'], 403);
        }

        if ($typeToCreate === 'content_auditor' && !$currentUser->isSystemAdministrator()) {
            return response()->json(['message' => 'Only system administrators can create auditors.'], 403);
        }

        // يمكن للمدير إنشاء البالغين الآخرين
        if (in_array($typeToCreate, ['parent', 'content_creator']) && !$currentUser->isSystemAdministrator()) {
            return response()->json(['message' => 'Unauthorized to create this user type.'], 403);
        }

        // 3. معالجة البيانات وتجهيزها للإنشاء
        $userData = [
            'first_name' => $validatedData['first_name'],
            'father_name' => $validatedData['father_name'], // **تمت إضافته إلى المصفوفة**
            'last_name' => $validatedData['last_name'],
            'mother_name' => $validatedData['mother_name'] ?? null,
            'national_id' => $validatedData['national_id'],
            'user_type' => $typeToCreate,
            'age' => $validatedData['age'] ?? null,
            'education_level' => $validatedData['education_level'] ?? null,
            'is_active' => true,
        ];

        // 4. تحديد الحقول الخاصة بناءً على النوع
        if ($typeToCreate === 'child') {
            // الأطفال: تعيين PIN وربط بـ Parent ID
            $userData['pin'] = $validatedData['pin'] ?? strval(mt_rand(1000, 9999));

            // إذا كان المستخدم الحالي هو الأب، استخدم ID الأب الحالي
            $userData['parent_id'] = $isCurrentUserParent ? $currentUser->id : $validatedData['parent_id'] ?? null;

            $userData['supervisor_id'] = null;
            $userData['password'] = Hash::make($validatedData['password']);

            $uniqueIdentifier = Str::uuid()->toString();
            $safeName = Str::slug($validatedData['first_name']); // لتحويل الاسم إلى تنسيق URL آمن

            $generatedEmail = $safeName . '-' . substr($validatedData['national_id'], -4) . '-' . $uniqueIdentifier . '@child.app';

            $userData['email'] = $generatedEmail;
        } else {
            // البالغون: تعيين الإيميل وكلمة المرور وتشفيرها
            $userData['email'] = $validatedData['email'];
            $userData['password'] = Hash::make($validatedData['password']);
            $userData['supervisor_id'] = $validatedData['supervisor_id'] ?? null;
            $userData['parent_id'] = null;
            $userData['pin'] = null;
        }

        // 5. إنشاء المستخدم في قاعدة البيانات
        $user = User::create($userData);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user->only(['id', 'first_name', 'user_type', 'email', 'pin'])
        ], 201);
    }

    /**
     * تحديث كلمة المرور أو تفعيل/إلغاء تفعيل حساب (فقط للمدير).
     */
    public function update1(Request $request, User $user)
    {
        $currentUser = $request->user();

        if (!$currentUser->isSystemAdministrator()) {
            return response()->json(['message' => 'Only administrators can update users.'], 403);
        }

        $validatedData = $request->validate([
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);

        // تحديث كلمة المرور
        if (isset($validatedData['password'])) {
            $user->password = Hash::make($validatedData['password']);
        }

        // تفعيل / إلغاء تفعيل
        if (isset($validatedData['is_active'])) {
            $user->is_active = $validatedData['is_active'];
        }

        $user->save();

        return response()->json(['message' => 'User updated successfully.', 'is_active' => $user->is_active]);
    }

    /**
     * ربط صانع محتوى بمراقب محتوى (Admin فقط).
     */
    public function linkSupervisor(Request $request, User $user)
    {
        $currentUser = $request->user();

        if (!$currentUser->isSystemAdministrator()) {
            return response()->json(['message' => 'Only administrators can link users.'], 403);
        }

        // يجب أن يكون المستخدم المراد ربطه صانع محتوى
        if (!$user->isContentCreator()) {
            return response()->json(['message' => 'Cannot link non-content creators.'], 400);
        }

        $validatedData = $request->validate([
            // تأكد أن الـ ID المُراد ربطه هو لمراقب محتوى
            'supervisor_id' => [
                'required',
                'exists:users,id',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('user_type', 'content_auditor');
                })
            ],
        ]);

        $user->supervisor_id = $validatedData['supervisor_id'];
        $user->save();

        return response()->json(['message' => 'Content Creator linked to supervisor successfully.']);
    }

    /**
     * حذف مستخدم (Admin فقط).
     */
    public function destroy(Request $request, User $user)
    {
        if (!$request->user()->isSystemAdministrator()) {
            return response()->json(['message' => 'Only administrators can delete users.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * جلب جميع حسابات مراقبين صناع المحتوى (Admin / Creator).
     */
    public function listAuditors(Request $request)
    {
        $currentUser = $request->user();

        // if (!$currentUser->isSystemAdministrator() && !$currentUser->isContentCreator()) {
        //     return response()->json(['message' => 'Unauthorized access'], 403);
        // }

        $auditors = User::where('user_type', 'content_auditor')
            ->where('is_active', true)
            ->get();

        return response()->json($auditors);
    }

    /**
     * جلب جميع حسابات صناع المحتوى (Admin فقط).
     */
    public function listCreators(Request $request)
    {
        // if (!$request->user()->isSystemAdministrator()) {
        //     return response()->json(['message' => 'Unauthorized access'], 403);
        // }

        $creators = User::where('user_type', 'content_creator')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $creators->count(),
            'data' => $creators // هنا نضع المصفوفة داخل مفتاح data
        ]);
    }

    public function listParents(Request $request)
    {
        // if (!$request->user()->isSystemAdministrator()) {
        //     return response()->json(['message' => 'Unauthorized access'], 403);
        // }

        $creators = User::where('user_type', 'parent')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $creators->count(),
            'data' => $creators // هنا نضع المصفوفة داخل مفتاح data
        ]);
    }

    public function listChilds(Request $request)
    {
        // if (!$request->user()->isSystemAdministrator()) {
        //     return response()->json(['message' => 'Unauthorized access'], 403);
        // }

        $creators = User::where('user_type', 'child')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $creators->count(),
            'data' => $creators // هنا نضع المصفوفة داخل مفتاح data
        ]);
    }
    //الدوال الخاصة بالاب من اجل دارة حساب لاولاد
    /**
     * عرض جميع الأبناء المرتبطين بالأب الحالي
     */
    public function getMyChildren(Request $request)
    {
        // استخدام العلاقة children() المعرفة في المودل الخاص بك
        $children = $request->user()->children;

        return response()->json([
            'status' => 'success',
            'count' => $children->count(),
            'data' => $children
        ]);
    }

    /**
     * تعديل بيانات ابن محدد
     */
    public function updateChild(Request $request, $id)
    {
        // البحث عن الطفل فقط ضمن قائمة أبناء هذا الأب لضمان الأمان
        $child = $request->user()->children()->find($id);

        if (!$child) {
            return response()->json(['message' => 'الطفل غير موجود أو لا تملك صلاحية الوصول'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'age' => 'sometimes|integer|min:2',
            'pin' => 'sometimes|string|size:4', // افترضنا أن الـ PIN مكون من 4 أرقام
            'is_active' => 'sometimes|boolean'
        ]);

        $child->update($validated);

        return response()->json([
            'message' => 'تم تحديث بيانات الطفل بنجاح',
            'data' => $child
        ]);
    }

    /**
     * حذف حساب ابن
     */
    public function destroyChild(Request $request, $id)
    {
        $child = $request->user()->children()->find($id);

        if (!$child) {
            return response()->json(['message' => 'تعذر العثور على الحساب المطلوب حذفه'], 404);
        }

        $child->delete();

        return response()->json(['message' => 'تم حذف حساب الطفل نهائياً']);
    }



    /**
     * تغيير حالة الحساب (قبول أو رفض) - خاص بمدير النظام فقط.
     */
    public function updateStatus(Request $request, $id)
    {
        // // 1. التأكد من أن المستخدم الحالي هو مدير نظام
        // if (!$request->user()->isSystemAdministrator()) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'عذراً، هذه الصلاحية محصورة لمدير النظام فقط.'
        //     ], 403);
        // }

        // 2. التحقق من صحة القيمة المرسلة
        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
        ]);

        // 3. البحث عن المستخدم
        $user = User::findOrFail($id);

        // 4. تحديث الحالة
        $user->account_status = $validated['status'];

        // اختيارياً: إذا تم الرفض، يمكننا تعطيل الحساب أيضاً
        if ($validated['status'] === 'rejected') {
            $user->is_active = false;
        } else {
            $user->is_active = true;
        }

        $user->save();

        // 5. الرد بالنتيجة
        $statusMessage = $user->account_status === 'accepted' ? 'مقبول' : 'مرفوض';

        return response()->json([
            'status' => 'success',
            'message' => "تم تحديث حالة حساب المستخدم ({$user->getFullNameAttribute()}) إلى {$statusMessage} بنجاح.",
            'data' => [
                'user_id' => $user->id,
                'new_status' => $user->account_status,
                'is_active' => $user->is_active
            ]
        ]);
    }
}