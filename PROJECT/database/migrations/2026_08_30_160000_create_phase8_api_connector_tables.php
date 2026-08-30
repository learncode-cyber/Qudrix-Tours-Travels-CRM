<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-configurable external API connector (Directive S18).
        // The system ships the *engine*; the operator supplies the actual
        // provider contract (GDS, hotel bedbank, visa provider, payment
        // gateway, ...) through configuration. No third-party endpoint is
        // ever invented in code — a connector with no endpoints defined
        // is reported as CONTRACT REQUIRED.
        Schema::create('api_connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('category'); // flight, hotel, visa, payment, sms, whatsapp, email, ai, analytics, crm, other
            $table->string('provider_name')->nullable(); // free text: "Amadeus", "Hotelbeds", "TBO", operator's own vendor...
            $table->string('base_url');
            $table->string('auth_type')->default('none'); // none, bearer, api_key_header, api_key_query, basic, custom_headers
            $table->string('auth_key_name')->nullable(); // header/query param name for api_key_* auth
            $table->text('credentials')->nullable(); // encrypted at rest, never serialized
            $table->json('default_headers')->nullable();
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->boolean('is_active')->default(false);
            $table->string('status')->default('unconfigured'); // unconfigured, configured, connected, failed
            $table->timestamp('last_test_at')->nullable();
            $table->text('last_test_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'category', 'is_active']);
        });

        // One row per operation the operator maps onto their provider,
        // e.g. flight:search, flight:book, visa:status. request_template
        // and response_mapping let a provider's own payload shape be
        // translated to/from our normalized shape without code changes.
        Schema::create('api_connector_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_connector_id')->constrained()->cascadeOnDelete();
            $table->string('operation'); // search, quote, book, cancel, status, verify, send
            $table->string('http_method')->default('POST');
            $table->string('path'); // may contain {placeholders} filled from runtime params
            $table->json('request_template')->nullable(); // values may use {{param}} placeholders
            $table->json('query_template')->nullable();
            $table->json('response_mapping')->nullable(); // our_field => dot.path.in.provider.response
            $table->string('response_collection_path')->nullable(); // dot path to the array for list responses
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['api_connector_id', 'operation']);
        });

        // Every outbound call is logged: who, what, how long, what came
        // back. Required for the auditability rule (Directive S23) and
        // for debugging a provider integration without guesswork.
        Schema::create('api_connector_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_connector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation');
            $table->string('http_method');
            $table->text('url');
            $table->json('request_payload')->nullable();
            $table->unsignedInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'api_connector_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_connector_call_logs');
        Schema::dropIfExists('api_connector_endpoints');
        Schema::dropIfExists('api_connectors');
    }
};
