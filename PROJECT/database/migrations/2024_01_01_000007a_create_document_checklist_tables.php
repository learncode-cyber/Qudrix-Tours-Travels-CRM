<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 5: document checklist — flagged in the Phase 4 report as a
 * reusable component rather than building it separately for Hajj/Umrah
 * "document readiness" and Student Visa "required documents" (both
 * explicitly in the spec). Same entity_type/entity_id pattern as Tags
 * and Custom Fields from Phase 2, for the same reason: admin-defined
 * per-tenant document lists without a schema migration every time one
 * changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type'); // 'hajj_booking', 'umrah_booking', 'student_visa'
            $table->string('name');
            $table->boolean('is_mandatory')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'entity_type', 'name']);
        });

        Schema::create('document_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_requirement_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('status')->default('pending'); // pending, submitted, verified, rejected
            $table->string('file_reference')->nullable(); // path/URL once file storage is wired up
            $table->text('rejection_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['document_requirement_id', 'entity_type', 'entity_id'], 'doc_submissions_unique');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_submissions');
        Schema::dropIfExists('document_requirements');
    }
};
