<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes matching the query shapes the admin queues and student
     * views actually run. `where('status', ...)->latest()->paginate()` was a
     * full table scan on both enrollments and grades — those are the two
     * queries behind every admin approval screen.
     *
     * Single-column foreign keys are deliberately absent: `foreignId()
     * ->constrained()` already creates an index for each of them in MySQL.
     *
     * @var array<string, array<int, array<int, string>>>
     */
    private array $indexes = [
        'enrollments' => [['status', 'created_at']],
        'grades' => [['status', 'created_at']],
        'course_offerings' => [['academic_session_id', 'semester']],
        'timetable_slots' => [['venue_id', 'day']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            foreach ($definitions as $columns) {
                $name = $this->indexName($table, $columns);

                if (Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            foreach ($definitions as $columns) {
                $name = $this->indexName($table, $columns);

                if (! Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function indexName(string $table, array $columns): string
    {
        return $table.'_'.implode('_', $columns).'_index';
    }
};
