<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropUnique('movies_tmdb_id_unique');
            $table->unique(['tmdb_id', 'media_type']);
            $table->unsignedInteger('number_of_seasons')->nullable()->after('media_type');
            $table->unsignedInteger('number_of_episodes')->nullable()->after('number_of_seasons');
            $table->json('seasons')->nullable()->after('number_of_episodes');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropUnique(['tmdb_id', 'media_type']);
            $table->dropColumn(['number_of_seasons', 'number_of_episodes', 'seasons']);
            $table->integer('tmdb_id')->unique()->change();
        });
    }
};
