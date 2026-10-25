<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Histórico automático também durante o ano letivo (aluno Cursando).
 *
 * Antes, alterações de médias/faltas só enfileiravam a matrícula quando ela já tinha
 * histórico. Agora qualquer lançamento (ex.: envio diário do i-Diário) enfileira a
 * matrícula, para que o histórico seja criado e mantido atualizado ao longo do ano —
 * assim, se o aluno for transferido, ele já sai com o histórico em dia.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION modules.fcn_historico_auto_notas()
            RETURNS trigger AS $$
            DECLARE
                v_nota_aluno_id integer;
                v_matricula integer;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    v_nota_aluno_id := OLD.nota_aluno_id;
                ELSE
                    v_nota_aluno_id := NEW.nota_aluno_id;
                END IF;

                SELECT na.matricula_id INTO v_matricula
                  FROM modules.nota_aluno na
                 WHERE na.id = v_nota_aluno_id;

                PERFORM pmieducar.fcn_enfileirar_processamento_historico(v_matricula, 'notas');

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION modules.fcn_historico_auto_faltas()
            RETURNS trigger AS $$
            DECLARE
                v_falta_aluno_id integer;
                v_matricula integer;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    v_falta_aluno_id := OLD.falta_aluno_id;
                ELSE
                    v_falta_aluno_id := NEW.falta_aluno_id;
                END IF;

                SELECT fa.matricula_id INTO v_matricula
                  FROM modules.falta_aluno fa
                 WHERE fa.id = v_falta_aluno_id;

                PERFORM pmieducar.fcn_enfileirar_processamento_historico(v_matricula, 'faltas');

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    public function down(): void
    {
        // Volta a enfileirar notas/faltas somente quando já existe histórico
        DB::unprepared("
            CREATE OR REPLACE FUNCTION modules.fcn_historico_auto_notas()
            RETURNS trigger AS $$
            DECLARE
                v_nota_aluno_id integer;
                v_matricula integer;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    v_nota_aluno_id := OLD.nota_aluno_id;
                ELSE
                    v_nota_aluno_id := NEW.nota_aluno_id;
                END IF;

                SELECT na.matricula_id INTO v_matricula
                  FROM modules.nota_aluno na
                 WHERE na.id = v_nota_aluno_id;

                IF v_matricula IS NOT NULL AND EXISTS (
                    SELECT 1 FROM pmieducar.historico_escolar he
                     WHERE he.ref_cod_matricula = v_matricula AND he.ativo = 1
                ) THEN
                    PERFORM pmieducar.fcn_enfileirar_processamento_historico(v_matricula, 'notas');
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION modules.fcn_historico_auto_faltas()
            RETURNS trigger AS $$
            DECLARE
                v_falta_aluno_id integer;
                v_matricula integer;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    v_falta_aluno_id := OLD.falta_aluno_id;
                ELSE
                    v_falta_aluno_id := NEW.falta_aluno_id;
                END IF;

                SELECT fa.matricula_id INTO v_matricula
                  FROM modules.falta_aluno fa
                 WHERE fa.id = v_falta_aluno_id;

                IF v_matricula IS NOT NULL AND EXISTS (
                    SELECT 1 FROM pmieducar.historico_escolar he
                     WHERE he.ref_cod_matricula = v_matricula AND he.ativo = 1
                ) THEN
                    PERFORM pmieducar.fcn_enfileirar_processamento_historico(v_matricula, 'faltas');
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ");
    }
};
