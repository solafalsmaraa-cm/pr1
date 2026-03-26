<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // 1. حقول التعريف الأساسية والشخصية
            $table->string('first_name');
            $table->string('father_name');
            $table->string('last_name');
            $table->string('mother_name')->nullable();

            // 2. حقول الهوية والنوع
            $table->string('national_id')->unique();

            $table->enum('user_type', [
                'system_administrator',
                'parent',
                'content_creator',
                'content_auditor',
                'child'
            ]);

            // 3. حالة الحساب والمعلومات الاتصال
            $table->boolean('is_active')->default(true);

            // --- الحقول الجديدة المضافة هنا ---
            $table->string('image_url')->nullable(); // لتخزين رابط الصورة
            $table->enum('account_status', ['pending', 'accepted', 'rejected'])
                ->default('pending'); // حالة الحساب (بانتظار، مقبول، غير مقبول)
            // -------------------------------

            $table->unsignedSmallInteger('age')->nullable();
            $table->string('education_level')->nullable();

            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('profile_picture')->nullable();

            // 4. حقول المصادقة والأمان
            $table->string('password');
            $table->string('pin')->nullable();
            $table->boolean('is_mobile')->default(false);

            // 5. علاقات الـ Reference الذاتية
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->foreignId('supervisor_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};