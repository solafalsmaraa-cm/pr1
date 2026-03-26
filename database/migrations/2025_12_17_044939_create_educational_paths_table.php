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
        Schema::create('educational_paths', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->integer('number_of_courses')->default(0);
            $table->enum('status', ['draft', 'pending', 'published', 'rejected'])->default('draft');
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('auditor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('educational_paths');
    }
};
