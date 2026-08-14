<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $connection = 'pgsql_direct';

    public function up(): void
    {
        // The old single-column unique on tmdb_id (from create_movies_table)
        // would block the TV feature: a TV show sharing a tmdb_id with an
        // existing movie could never be inserted. It is replaced by the
        // composite (tmdb_id, media_type) unique below.
        if (Schema::hasIndex('movies', ['tmdb_id'])) {
            Schema::table('movies', function (Blueprint $table) {
                $table->dropUnique(['tmdb_id']);
            });
        }

        if (! Schema::hasIndex('movies', ['tmdb_id', 'media_type'])) {
            Schema::table('movies', function (Blueprint $table) {
                $table->unique(
                    ['tmdb_id', 'media_type'],
                    'movies_tmdb_id_media_type_unique'
                );
            });
        }

        Schema::table('movies', function (Blueprint $table) {
            if (! Schema::hasColumn('movies', 'number_of_seasons')) {
                $table->unsignedInteger('number_of_seasons')->nullable();
            }

            if (! Schema::hasColumn('movies', 'number_of_episodes')) {
                $table->unsignedInteger('number_of_episodes')->nullable();
            }

            if (! Schema::hasColumn('movies', 'seasons')) {
                $table->json('seasons')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            if (Schema::hasColumn('movies', 'number_of_seasons')) {
                $table->dropColumn('number_of_seasons');
            }

            if (Schema::hasColumn('movies', 'number_of_episodes')) {
                $table->dropColumn('number_of_episodes');
            }

            if (Schema::hasColumn('movies', 'seasons')) {
                $table->dropColumn('seasons');
            }

            if (Schema::hasIndex('movies', ['tmdb_id', 'media_type'])) {
                $table->dropUnique('movies_tmdb_id_media_type_unique');
            }
        });
    }
};
