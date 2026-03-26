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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('learning_content_id')->constrained()->onDelete('cascade');
            $table->integer('stars')->default(1); // من 1 إلى 5
            $table->text('comment')->nullable();
            $table->timestamps();

            // منع تكرار التقييم لنفس المستخدم على نفس المحتوى
            $table->unique(['user_id', 'learning_content_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
