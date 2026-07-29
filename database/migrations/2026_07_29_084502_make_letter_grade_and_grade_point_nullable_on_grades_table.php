<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->string('letter_grade')->nullable()->change();
            $table->decimal('grade_point', 3, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->string('letter_grade')->nullable(false)->change();
            $table->decimal('grade_point', 3, 2)->nullable(false)->change();
        });
    }
};