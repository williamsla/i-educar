<?php

use App\Support\Database\AuditTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A função public.audit() gravava a linha inteira em before e after em todo
 * INSERT, UPDATE e DELETE. No diário isso vira milhões de JSON por dia.
 * UPDATE passa a gravar só as colunas que mudaram (mais as chaves da linha)
 * e ignora atualização vazia ou só de carimbo de tempo.
 *
 * Também remove o trigger das tabelas operacionais. Desde 2023 a lista de
 * exclusão usava schema.tabela e a comparação olhava só o nome, então
 * portal.acesso, modules.auditoria e as tabelas *_excluidos voltaram a ser
 * auditadas. Telescope saiu da lista na mesma época.
 */
return new class extends Migration
{
    use AuditTrigger;

    public function up(): void
    {
        DB::unprepared($this->auditFunctionSql());
        $this->dropTriggersOnSkippedTables();
    }

    public function down(): void
    {
        DB::unprepared($this->legacyAuditFunctionSql());
    }

    private function dropTriggersOnSkippedTables(): void
    {
        $names = array_values(array_unique(array_map(
            'strtolower',
            $this->getSkippedTables()
        )));

        if ($names === []) {
            return;
        }

        $literals = implode(',', array_map(
            fn (string $name) => "'" . str_replace("'", "''", $name) . "'",
            $names
        ));

        DB::unprepared(<<<SQL
DO \$body\$
DECLARE
    r record;
BEGIN
    FOR r IN
        SELECT n.nspname, c.relname, t.tgname
          FROM pg_trigger t
          JOIN pg_class c ON c.oid = t.tgrelid
          JOIN pg_namespace n ON n.oid = c.relnamespace
          JOIN pg_proc p ON p.oid = t.tgfoid
          JOIN pg_namespace pn ON pn.oid = p.pronamespace
         WHERE NOT t.tgisinternal
           AND p.proname = 'audit'
           AND pn.nspname = 'public'
           AND (
                lower(c.relname) IN ($literals)
                OR lower(n.nspname || '.' || c.relname) IN ($literals)
           )
    LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I.%I', r.tgname, r.nspname, r.relname);
    END LOOP;
END
\$body\$;
SQL);
    }

    private function auditFunctionSql(): string
    {
        return <<<'SQL'
CREATE OR REPLACE FUNCTION public.audit()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    v_old jsonb;
    v_new jsonb;
    v_changed text[];
    v_keys text[];
    v_before jsonb;
    v_after jsonb;
BEGIN
    IF public.audit_enabled() = false THEN
        RETURN NULL;
    END IF;

    IF TG_OP = 'DELETE' THEN
        INSERT INTO public.ieducar_audit ("date", "schema", "table", "context", "before", "after")
        VALUES (now(), TG_TABLE_SCHEMA::text, TG_TABLE_NAME::text, public.audit_context(), to_jsonb(OLD), NULL);

        RETURN NULL;
    END IF;

    IF TG_OP = 'INSERT' THEN
        INSERT INTO public.ieducar_audit ("date", "schema", "table", "context", "before", "after")
        VALUES (now(), TG_TABLE_SCHEMA::text, TG_TABLE_NAME::text, public.audit_context(), NULL, to_jsonb(NEW));

        RETURN NULL;
    END IF;

    IF TG_OP = 'UPDATE' THEN
        v_old := to_jsonb(OLD);
        v_new := to_jsonb(NEW);

        SELECT COALESCE(array_agg(o.key), ARRAY[]::text[])
          INTO v_changed
          FROM jsonb_each(v_old) AS o
         WHERE (v_new -> o.key) IS DISTINCT FROM o.value;

        SELECT COALESCE(array_agg(n.key), ARRAY[]::text[])
          INTO v_keys
          FROM jsonb_each(v_new) AS n
         WHERE NOT jsonb_exists(v_old, n.key);

        v_changed := v_changed || v_keys;

        IF COALESCE(array_length(v_changed, 1), 0) = 0 THEN
            RETURN NULL;
        END IF;

        IF v_changed <@ ARRAY['updated_at', 'created_at', 'last_used_at']::text[] THEN
            RETURN NULL;
        END IF;

        SELECT COALESCE(array_agg(DISTINCT k), ARRAY[]::text[])
          INTO v_keys
          FROM (
                SELECT unnest(v_changed) AS k
                UNION
                SELECT e.key
                  FROM jsonb_each(v_old) AS e
                 WHERE e.key IN ('id', 'sequencial', 'etapa')
                    OR right(e.key, 3) = '_id'
                    OR left(e.key, 4) = 'cod_'
                    OR position('cod_' in e.key) > 0
          ) AS identificadores;

        SELECT jsonb_object_agg(chave, v_old -> chave),
               jsonb_object_agg(chave, v_new -> chave)
          INTO v_before, v_after
          FROM unnest(v_keys) AS chave;

        INSERT INTO public.ieducar_audit ("date", "schema", "table", "context", "before", "after")
        VALUES (now(), TG_TABLE_SCHEMA::text, TG_TABLE_NAME::text, public.audit_context(), v_before, v_after);

        RETURN NULL;
    END IF;

    RETURN NULL;
END;
$function$;
SQL;
    }

    private function legacyAuditFunctionSql(): string
    {
        return <<<'SQL'
CREATE OR REPLACE FUNCTION public.audit()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    IF (public.audit_enabled() = false) THEN
        RETURN NULL;
    END IF;

    IF (TG_OP = 'DELETE') THEN
        INSERT INTO public.ieducar_audit ("date", "schema", "table", "context", "before", "after")
        VALUES (now(), TG_TABLE_SCHEMA::text, TG_TABLE_NAME::text, public.audit_context(), to_json(OLD.*), NULL);

        RETURN OLD;
    END IF;

    IF (TG_OP = 'UPDATE') THEN
        INSERT INTO public.ieducar_audit ("date", "schema", "table", "context", "before", "after")
        VALUES (now(), TG_TABLE_SCHEMA::text, TG_TABLE_NAME::text, public.audit_context(), to_json(OLD.*), to_json(NEW.*));

        RETURN OLD;
    END IF;

    IF (TG_OP = 'INSERT') THEN
        INSERT INTO public.ieducar_audit ("date", "schema", "table", "context", "before", "after")
        VALUES (now(), TG_TABLE_SCHEMA::text, TG_TABLE_NAME::text, public.audit_context(), NULL, to_json(NEW.*));

        RETURN OLD;
    END IF;

    RETURN NULL;
END;
$function$;
SQL;
    }
};
