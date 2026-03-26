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
        Schema::create('learning_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained('educational_paths')->onDelete('cascade');
            $table->string('course_name');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('content_type');
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_contents');
    }
};
