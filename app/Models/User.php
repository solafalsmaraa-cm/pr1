<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * الحقول القابلة للتعبئة (Mass Assignable).
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'father_name',
        'last_name',
        'mother_name',
        'national_id',
        'user_type',
        'account_status', // الحقل الجديد: حالة الحساب
        'is_active',
        'age',
        'education_level',
        'email',
        'phone_number',
        'profile_picture',
        'image_url',       // الحقل الجديد: رابط الصورة
        'pin',
        'is_mobile',
        'parent_id',
        'supervisor_id',
        'password',
    ];

    /**
     * الحقول التي يجب إخفاؤها عند تحويل النموذج إلى JSON.
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * الحقول التي يجب تحويلها إلى أنواع بيانات معينة.
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'is_mobile' => 'boolean',
    ];

    /* ---------------------------------------------------------------------- */
    /* علاقات Eloquent (Relationships)                                       */
    /* ---------------------------------------------------------------------- */

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function supervisedCreators()
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /* ---------------------------------------------------------------------- */
    /* دوال مساعدة للتحقق من النوع والحالة (Helpers)                         */
    /* ---------------------------------------------------------------------- */

    // التحقق من نوع المستخدم
    public function isSystemAdministrator(): bool
    {
        return $this->user_type === 'system_administrator';
    }
    public function isParent(): bool
    {
        return $this->user_type === 'parent';
    }
    public function isContentCreator(): bool
    {
        return $this->user_type === 'content_creator';
    }
    public function isContentAuditor(): bool
    {
        return $this->user_type === 'content_auditor';
    }
    public function isChild(): bool
    {
        return $this->user_type === 'child';
    }

    // التحقق من حالة الحساب (الإضافات الجديدة)
    public function isPending(): bool
    {
        return $this->account_status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->account_status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->account_status === 'rejected';
    }

    // دالة مساعدة للحصول على الاسم الكامل.
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->father_name} {$this->last_name}";
    }
}