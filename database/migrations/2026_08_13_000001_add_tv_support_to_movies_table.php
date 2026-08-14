<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->upPgsql();

            return;
        }

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
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'alter table "movies" '
                .'drop constraint if exists "movies_tmdb_id_media_type_unique", '
                .'drop column if exists number_of_seasons, '
                .'drop column if exists number_of_episodes, '
                .'drop column if exists seasons'
            );

            return;
        }

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
    }

    protected function upPgsql(): void
    {
        DB::statement(<<<'SQL'
            do $$
            declare
                drop_stmt text;
            begin
                for drop_stmt in
                    select 'alter table "movies" drop constraint if exists "' || con.conname || '"'
                    from pg_constraint con
                    where con.conrelid = 'movies'::regclass
                      and con.contype in ('p', 'u', 'c', 'f', 'x')
                      and exists (
                          select 1
                          from pg_attribute att
                          where att.attrelid = con.conrelid
                            and att.attnum = any (con.conkey)
                            and att.attname = 'tmdb_id'
                      )
                    union all
                    select 'drop index if exists "' || i.relname || '"'
                    from pg_index idx
                    join pg_class i on i.oid = idx.indexrelid
                    where idx.indrelid = 'movies'::regclass
                      and idx.indisunique = true
                      and not exists (
                          select 1
                          from pg_constraint con
                          where con.conindid = idx.indexrelid
                      )
                      and not exists (
                          select 1
                          from pg_attribute att
                          where att.attrelid = idx.indrelid
                            and att.attnum = any (idx.indkey)
                            and att.attnum > 0
                            and att.attname <> 'tmdb_id'
                      )
                loop
                    execute drop_stmt;
                end loop;

                if not exists (
                    select 1
                    from pg_index idx
                    join pg_class i on i.oid = idx.indexrelid
                    where idx.indrelid = 'movies'::regclass
                      and idx.indisunique = true
                      and not idx.indisprimary
                      and not exists (
                          select 1
                          from pg_attribute att
                          where att.attrelid = idx.indrelid
                            and att.attnum = any (idx.indkey)
                            and att.attnum > 0
                            and att.attname not in ('tmdb_id', 'media_type')
                      )
                ) then
                    execute format('alter table "movies" add constraint %I unique (tmdb_id, media_type)', 'movies_tmdb_id_media_type_unique');
                end if;
            end
            $$;
            SQL);

        DB::statement(
            'alter table "movies" '
            .'add column if not exists number_of_seasons integer, '
            .'add column if not exists number_of_episodes integer, '
            .'add column if not exists seasons json'
        );
    }
};
