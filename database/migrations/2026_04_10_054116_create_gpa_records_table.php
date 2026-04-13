<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gpa_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->enum('semester', ['first', 'second']);
            $table->decimal('gpa', 4, 2);
            $table->decimal('cgpa', 4, 2);
            $table->integer('total_credit_units');
            $table->decimal('total_grade_points', 8, 2);
            $table->integer('cumulative_credit_units');
            $table->decimal('cumulative_grade_points', 8, 2);
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_records');
    }
};
