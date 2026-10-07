<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 16: SEO metadata + UTM/campaign attribution. Genuinely new.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type'); // 'package', 'destination', 'page'
            $table->unsignedBigInteger('entity_id')->nullable(); // null for static pages identified by slug
            $table->string('slug')->nullable(); // for static pages, e.g. 'home', 'about'
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image_url')->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('schema_markup')->nullable(); // raw JSON-LD structured data
            $table->string('robots_directive')->default('index,follow');
            $table->timestamps();

            $table->unique(['tenant_id', 'entity_type', 'entity_id', 'slug'], 'seo_metadata_unique');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']);
        });

        Schema::dropIfExists('seo_metadata');
    }
};
