<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE grades MODIFY status ENUM('draft', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'draft'");

        Schema::table('grades', function (Blueprint $table) {
            $table->unsignedTinyInteger('ca_score')->nullable()->after('score');
            $table->unsignedTinyInteger('project_score')->nullable()->after('ca_score');
            $table->unsignedTinyInteger('exam_score')->nullable()->after('project_score');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn(['ca_score', 'project_score', 'exam_score']);
        });

        DB::statement("ALTER TABLE grades MODIFY status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
    }
};