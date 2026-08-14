<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropTmdbIdUnique();

        if (! Schema::hasIndex('movies', ['tmdb_id', 'media_type'])) {
            Schema::table('movies', function (Blueprint $table) {
                $table->unique(['tmdb_id', 'media_type']);
            });
        }

        foreach ([
            'number_of_seasons' => 'unsignedInteger',
            'number_of_episodes' => 'unsignedInteger',
            'seasons' => 'json',
        ] as $column => $type) {
            if (! Schema::hasColumn('movies', $column)) {
                Schema::table('movies', function (Blueprint $table) use ($column, $type) {
                    $table->{$type}($column)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('movies', ['tmdb_id', 'media_type'])) {
            Schema::table('movies', function (Blueprint $table) {
                $table->dropUnique(['tmdb_id', 'media_type']);
            });
        }

        foreach (['number_of_seasons', 'number_of_episodes', 'seasons'] as $column) {
            if (Schema::hasColumn('movies', $column)) {
                Schema::table('movies', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (! Schema::hasIndex('movies', 'movies_tmdb_id_unique')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->integer('tmdb_id')->unique()->change();
            });
        }
    }

    protected function dropTmdbIdUnique(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            if (Schema::hasIndex('movies', 'movies_tmdb_id_unique')) {
                Schema::table('movies', function (Blueprint $table) {
                    $table->dropUnique('movies_tmdb_id_unique');
                });
            }

            return;
        }

        foreach (Schema::getIndexes('movies') as $index) {
            if (! $index['unique'] || $index['primary'] || $index['columns'] !== ['tmdb_id']) {
                continue;
            }

            $constraint = DB::selectOne(
                'select c.conname from pg_constraint c '
                .'join pg_class ic on ic.oid = c.conindid '
                .'where ic.relname = ?',
                [$index['name']]
            );

            if ($constraint) {
                DB::statement('alter table "movies" drop constraint "'.$constraint->conname.'"');
            } else {
                DB::statement('drop index "'.$index['name'].'"');
            }
        }
    }
};
