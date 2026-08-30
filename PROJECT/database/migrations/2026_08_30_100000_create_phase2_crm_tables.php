<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('designation')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('notable_type');
            $table->unsignedBigInteger('notable_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamps();

            $table->index(['notable_type', 'notable_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk')->default('local');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['documentable_type', 'documentable_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#6b7280');

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');

            $table->primary(['tag_id', 'taggable_type', 'taggable_id']);
            $table->index(['taggable_type', 'taggable_id']);
        });

        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->string('key');
            $table->string('label');
            $table->string('field_type')->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'entity_type', 'key']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_definition_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_definition_id', 'entity_type', 'entity_id'], 'custom_field_values_unique');
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('remindable_type')->nullable();
            $table->unsignedBigInteger('remindable_id')->nullable();
            $table->string('title');
            $table->timestamp('remind_at');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['remindable_type', 'remindable_id']);
            $table->index(['user_id', 'status', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
    }
};
