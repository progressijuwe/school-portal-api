<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE enrollments MODIFY status ENUM('pending', 'active', 'dropped', 'completed', 'rejected') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE enrollments MODIFY status ENUM('active', 'dropped', 'completed') NOT NULL DEFAULT 'active'");
    }
};
