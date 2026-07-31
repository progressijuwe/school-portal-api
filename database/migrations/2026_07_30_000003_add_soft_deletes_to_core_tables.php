<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every foreign key in this schema is restrictOnDelete, so a graduated
     * student or a retired course can never be hard-deleted without orphaning
     * an academic record. Soft deletes give the admin UI a working "remove"
     * action while keeping transcripts intact.
     *
     * @var array<int, string>
     */
    private array $tables = ['users', 'courses', 'venues'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
