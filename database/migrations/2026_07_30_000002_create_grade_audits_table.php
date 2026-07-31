<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable history of every write to a grade.
     *
     * `grades` is mutated in place by updateOrCreate, so without this there is
     * no way to answer "who changed this mark, when, and from what" — which for
     * an academic record is usually a compliance requirement, not a nicety.
     */
    public function up(): void
    {
        Schema::create('grade_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);              // App\Enums\GradeAuditAction
            $table->json('before')->nullable();
            $table->json('after');
            $table->string('reason', 500)->nullable(); // rejection reason, correction note
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['grade_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_audits');
    }
};
