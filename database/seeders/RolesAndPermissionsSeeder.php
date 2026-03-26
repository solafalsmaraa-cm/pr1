<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. إعادة تعيين ذاكرة التخزين المؤقت للأدوار والصلاحيات
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. إنشاء الصلاحيات (Permissions)
        $permissions = [
            // صلاحيات مدير النظام وإدارة المستخدمين
            'manage_users',
            'view_reports',
            'full_system_access',

            // صلاحيات الآباء
            'create_child_account',
            'monitor_child_progress',

            // صلاحيات صانعي المحتوى
            'create_course',
            'edit_own_content',
            'submit_for_review',

            // صلاحيات مراقبي المحتوى
            'review_content',
            'approve_content',
            'reject_content',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 3. إنشاء الأدوار (Roles) وتعيين الصلاحيات

        // الدور 1: مدير النظام (System Administrator) - له كل الصلاحيات
        $adminRole = Role::firstOrCreate(['name' => 'System Administrator']);
        $adminRole->givePermissionTo(Permission::all());

        // الدور 2: الأب (Parent)
        $parentRole = Role::firstOrCreate(['name' => 'Parent']);
        $parentRole->givePermissionTo([
            'create_child_account',
            'monitor_child_progress',
        ]);

        // الدور 3: صانع المحتوى (Content Creator)
        $creatorRole = Role::firstOrCreate(['name' => 'Content Creator']);
        $creatorRole->givePermissionTo([
            'create_course',
            'edit_own_content',
            'submit_for_review',
        ]);

        // الدور 4: مراقب صانع المحتوى (Content Auditor)
        $auditorRole = Role::firstOrCreate(['name' => 'Content Auditor']);
        $auditorRole->givePermissionTo([
            'review_content',
            'approve_content',
            'reject_content',
        ]);

        // *******************************************************************
        // مثال: إنشاء أول مدير نظام (للتجربة)
        // *******************************************************************

        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'مدير',
                'father_name' => 'النظام',
                'last_name' => 'الأساسي',
                'national_id' => '00000000000',
                'password' => bcrypt('password'), // يجب تغيير كلمة المرور بعد التنصيب
                'is_active' => true,
                'is_admin' => true,
            ]
        );
        $user->assignRole($adminRole); // تعيين دور المدير له
        // *******************************************************************
    }
}