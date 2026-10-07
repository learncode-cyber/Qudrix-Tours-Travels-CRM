<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('webhooks')->onDelete('cascade');
            $table->uuid('delivery_id')->unique();
            $table->string('event', 100);
            $table->longText('payload');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->integer('attempt')->default(1);
            $table->integer('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            $table->index('webhook_id');
            $table->index('status');
            $table->index('delivery_id');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
