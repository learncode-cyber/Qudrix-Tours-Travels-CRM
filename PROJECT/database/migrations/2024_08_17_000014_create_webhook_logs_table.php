<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('webhooks')->onDelete('cascade');
            $table->foreignId('delivery_id')->nullable()->constrained('webhook_deliveries')->onDelete('cascade');
            $table->text('message');
            $table->enum('status', ['success', 'failed', 'scheduled', 'retrying'])->default('scheduled');
            $table->timestamp('retry_at')->nullable();
            $table->timestamps();
            
            $table->index('webhook_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('webhook_logs');
    }
};
