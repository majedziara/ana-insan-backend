<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->string('text', 1000);
            $table->string('text_en', 1000)->nullable();
            $table->unsignedTinyInteger('duration_days')->default(1);
            $table->boolean('is_active')->default(true);
            $table->dateTime('published_at');
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->index(['is_active', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
