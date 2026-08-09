<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('planning')
                ->check("status IN ('planning', 'watching', 'completed', 'dropped', 'on_hold')");
            $table->integer('progress')->default(0);
            $table->decimal('rating', 3, 1)->nullable()
                ->check('rating >= 0 AND rating <= 10');
            $table->boolean('favorite')->default(false);
            $table->integer('rewatch_count')->default(0);
            $table->timestamp('watched_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'movie_id']);
            $table->index('movie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlists');
    }
};
