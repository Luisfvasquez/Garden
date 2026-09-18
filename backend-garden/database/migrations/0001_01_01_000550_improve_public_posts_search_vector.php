<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `search_vector` column, its GIN index and a trigger have existed since
 * Fase 2D but nothing ever queried them. Fase 4D turns them on, and fixes two
 * things that would have been immediately visible to anyone using the search.
 *
 *  1. **`simple` → `spanish`.** `pg_catalog.simple` does no stemming at all, so
 *     searching "cartas" would not find "carta", and "escribiendo" would not
 *     find "escribir". The product writes in Spanish; this is the config that
 *     matches it. (One column can only carry one configuration — English posts
 *     lose stemming in exchange. Documented in docs/api/blog.md.)
 *
 *  2. **Title weighted above body.** `tsvector_update_trigger()` cannot assign
 *     weights, so it needs a hand-written trigger function. A post whose title
 *     is "Cartas a mi padre" should outrank one that says "cartas" once in the
 *     middle of a paragraph.
 *
 * Existing rows are re-indexed in place; nothing is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS public_posts_search_vector_update ON public_posts');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public_posts_search_vector_trigger() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector :=
                    setweight(to_tsvector('pg_catalog.spanish', coalesce(NEW.title, '')), 'A') ||
                    setweight(to_tsvector('pg_catalog.spanish', coalesce(NEW.body_plain, '')), 'B');
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER public_posts_search_vector_update
            BEFORE INSERT OR UPDATE OF title, body_plain ON public_posts
            FOR EACH ROW EXECUTE FUNCTION public_posts_search_vector_trigger()
        SQL);

        // Re-index what is already there under the new configuration.
        DB::statement(<<<'SQL'
            UPDATE public_posts SET search_vector =
                setweight(to_tsvector('pg_catalog.spanish', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('pg_catalog.spanish', coalesce(body_plain, '')), 'B')
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS public_posts_search_vector_update ON public_posts');
        DB::statement('DROP FUNCTION IF EXISTS public_posts_search_vector_trigger()');

        DB::statement(<<<'SQL'
            CREATE TRIGGER public_posts_search_vector_update
            BEFORE INSERT OR UPDATE OF title, body_plain ON public_posts
            FOR EACH ROW EXECUTE FUNCTION
            tsvector_update_trigger(search_vector, 'pg_catalog.simple', title, body_plain)
        SQL);

        DB::statement(<<<'SQL'
            UPDATE public_posts SET search_vector =
                to_tsvector('pg_catalog.simple', coalesce(title, '') || ' ' || coalesce(body_plain, ''))
        SQL);
    }
};
