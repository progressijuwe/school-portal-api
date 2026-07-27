<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->date('first_semester_start')->nullable()->after('end_year');
            $table->date('first_semester_end')->nullable()->after('first_semester_start');
            $table->date('second_semester_start')->nullable()->after('first_semester_end');
            $table->date('second_semester_end')->nullable()->after('second_semester_start');
        });
    }

    public function down(): void
    {
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'first_semester_start',
                'first_semester_end',
                'second_semester_start',
                'second_semester_end',
            ]);
        });
    }
};