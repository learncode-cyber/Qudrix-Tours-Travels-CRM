<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained('api_keys')->onDelete('cascade');
            $table->text('url');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->string('secret', 255);
            $table->integer('retry_count')->default(0);
            $table->integer('retry_limit')->default(5);
            $table->timestamp('last_triggered_at')->nullable();
            $table->string('last_triggered_status')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->index('api_key_id');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('webhooks');
    }
};
