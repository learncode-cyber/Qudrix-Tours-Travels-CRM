<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 2: Tags and Custom Fields — genuinely new features (no prior
 * model, migration, or controller existed for either).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#6366f1');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            // Nullable: Laravel's morphToMany sync()/attach() calls don't
            // populate extra pivot columns unless explicitly told to, and
            // this pivot is written to via the standard relation helpers
            // in App\Models\Concerns\HasTags — so tenant_id here is
            // informational/for future denormalized queries only. Tenant
            // isolation for tags themselves is enforced by tags.tenant_id.
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique');
            $table->index(['taggable_type', 'taggable_id']);
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // 'entity_type' identifies which module this field applies to,
            // e.g. 'customer', 'lead', 'deal' — a short slug, not a class
            // string, so it stays stable if models move/rename.
            $table->string('entity_type');
            $table->string('name');
            $table->string('label');
            $table->enum('field_type', ['text', 'number', 'date', 'select', 'multi_select', 'boolean']);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'entity_type', 'name']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_id', 'entity_type', 'entity_id'], 'custom_field_values_unique');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
