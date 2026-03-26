<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SystemAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // بيانات المدير الأساسي
        $adminData = [
            'first_name' => 'مدير',
            'father_name' => 'النظام',
            'last_name' => 'الأساسي',
            'national_id' => '00000000000', // رقم تعريفي مؤقت
            'user_type' => 'system_administrator', // تحديد نوع المستخدم
            'is_active' => true,
            'email' => 'admin@example.com', // الإيميل الافتراضي
            'password' => Hash::make('password'), // كلمة المرور: 'password'
            'age' => 30,
        ];

        // التحقق مما إذا كان المدير موجودًا بالفعل قبل إنشائه
        $admin = User::where('email', $adminData['email'])->first();

        if (is_null($admin)) {
            // إنشاء المستخدم
            User::create($adminData);
            $this->command->info('System Administrator created successfully!');
        } else {
            // تحديث المستخدم إذا كان موجودًا (للتأكد من الإعدادات الصحيحة)
            $admin->update($adminData);
            $this->command->info('System Administrator updated successfully!');
        }
    }
}