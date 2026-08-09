<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_list_movies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('custom_lists')->cascadeOnDelete();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['list_id', 'movie_id']);
            $table->index('movie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_list_movies');
    }
};
