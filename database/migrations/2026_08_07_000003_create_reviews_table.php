<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->text('review');
            $table->boolean('contains_spoilers')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'movie_id']);
            $table->index('movie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
