<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Processamento automático do histórico escolar.
 *
 * Cria a fila pmieducar.historico_processamento_fila e as triggers que a alimentam:
 *
 *  - pmieducar.matricula: sempre que a coluna "aprovado" (situação) mudar.
 *    Pega TODOS os caminhos que alteram a situação (boletim/diário, alteração manual,
 *    atualização em lote, importação, abandono, reclassificação, SQL direto, etc).
 *
 *  - modules.nota_componente_curricular_media / modules.media_geral /
 *    modules.falta_componente_curricular / modules.falta_geral: quando uma média ou
 *    falta muda e a matrícula JÁ possui histórico processado. Isso cobre o caso em que
 *    o professor corrige uma nota sem que a situação do aluno mude (ex.: continua
 *    Aprovado, mas a nota do histórico ficaria desatualizada).
 *
 * As triggers apenas enfileiram (operação barata e transacional: se a alteração sofrer
 * rollback, o item da fila também some). O processamento em si é feito pelo PHP
 * (App\Services\SchoolHistory\SchoolHistoryAutoProcessService), reaproveitando
 * exatamente a mesma rotina da tela "Processamento de histórico escolar".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            CREATE TABLE IF NOT EXISTS pmieducar.historico_processamento_fila (
                id bigserial PRIMARY KEY,
                ref_cod_matricula integer NOT NULL,
                origem varchar(20) NOT NULL DEFAULT 'situacao',
                situacao_anterior smallint NULL,
                situacao_nova smallint NULL,
                ref_usuario integer NULL,
                status varchar(20) NOT NULL DEFAULT 'pendente',
                tentativas smallint NOT NULL DEFAULT 0,
                mensagem text NULL,
                created_at timestamp NOT NULL DEFAULT now(),
                iniciado_em timestamp NULL,
                processado_em timestamp NULL
            );

            COMMENT ON TABLE pmieducar.historico_processamento_fila IS
                'Fila do processamento automático de histórico escolar (alimentada por triggers).';
            COMMENT ON COLUMN pmieducar.historico_processamento_fila.status IS
                'pendente | processando | processado | atualizado | ignorado | erro';

            -- Apenas um item pendente por matrícula (alterações em sequência são agrupadas)
            CREATE UNIQUE INDEX IF NOT EXISTS historico_processamento_fila_pendente_unique
                ON pmieducar.historico_processamento_fila (ref_cod_matricula)
                WHERE status = 'pendente';

            CREATE INDEX IF NOT EXISTS historico_processamento_fila_status_idx
                ON pmieducar.historico_processamento_fila (status, id);

            CREATE INDEX IF NOT EXISTS historico_processamento_fila_matricula_idx
                ON pmieducar.historico_processamento_fila (ref_cod_matricula);

            -- Usado pelas triggers de notas/faltas para saber se a matrícula já tem histórico
            CREATE INDEX IF NOT EXISTS historico_escolar_ref_cod_matricula_ativo_idx
                ON pmieducar.historico_escolar (ref_cod_matricula)
                WHERE ativo = 1;
        ");

        // Função que enfileira. Lê o usuário do contexto de auditoria (definido pelo
        // listener ConfigureAuthenticatedUserForAudit) quando disponível.
        DB::unprepared("
            CREATE OR REPLACE FUNCTION pmieducar.fcn_enfileirar_processamento_historico(
                p_matricula integer,
                p_origem varchar,
                p_situacao_anterior integer DEFAULT NULL,
                p_situacao_nova integer DEFAULT NULL
            ) RETURNS void AS $$
            DECLARE
                v_usuario integer;
            BEGIN
                IF p_matricula IS NULL THEN
                    RETURN;
                END IF;

                BEGIN
                    v_usuario := NULLIF(current_setting('audit.context', true), '')::json->>'user_id';
                EXCEPTION WHEN others THEN
                    v_usuario := NULL;
                END;

                INSERT INTO pmieducar.historico_processamento_fila
                    (ref_cod_matricula, origem, situacao_anterior, situacao_nova, ref_usuario)
                VALUES
                    (p_matricula, p_origem, p_situacao_anterior, p_situacao_nova, v_usuario)
                ON CONFLICT (ref_cod_matricula) WHERE status = 'pendente'
                DO UPDATE SET
                    situacao_nova = COALESCE(EXCLUDED.situacao_nova, historico_processamento_fila.situacao_nova),
                    ref_usuario = COALESCE(EXCLUDED.ref_usuario, historico_processamento_fila.ref_usuario),
                    origem = CASE
                        WHEN historico_processamento_fila.origem = 'situacao' THEN 'situacao'
                        ELSE EXCLUDED.origem
                    END;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // 1) Mudança de situação da matrícula
        DB::unprepared("
            CREATE OR REPLACE FUNCTION pmieducar.fcn_historico_auto_matricula_situacao()
            RETURNS trigger AS $$
            BEGIN
                PERFORM pmieducar.fcn_enfileirar_processamento_historico(
                    NEW.cod_matricula, 'situacao', OLD.aprovado, NEW.aprovado
                );

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_historico_auto_matricula_situacao ON pmieducar.matricula;

            CREATE TRIGGER trg_historico_auto_matricula_situacao
                AFTER UPDATE OF aprovado ON pmieducar.matricula
                FOR EACH ROW
                WHEN (OLD.aprovado IS DISTINCT FROM NEW.aprovado)
                EXECUTE FUNCTION pmieducar.fcn_historico_auto_matricula_situacao();
        ");

        // 2) Médias (nota_aluno_id -> matrícula), somente se já existe histórico
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

            DROP TRIGGER IF EXISTS trg_historico_auto_media_componente ON modules.nota_componente_curricular_media;
            CREATE TRIGGER trg_historico_auto_media_componente
                AFTER INSERT OR UPDATE OR DELETE ON modules.nota_componente_curricular_media
                FOR EACH ROW
                EXECUTE FUNCTION modules.fcn_historico_auto_notas();

            DROP TRIGGER IF EXISTS trg_historico_auto_media_geral ON modules.media_geral;
            CREATE TRIGGER trg_historico_auto_media_geral
                AFTER INSERT OR UPDATE OR DELETE ON modules.media_geral
                FOR EACH ROW
                EXECUTE FUNCTION modules.fcn_historico_auto_notas();
        ");

        // 3) Faltas (falta_aluno_id -> matrícula), somente se já existe histórico
        DB::unprepared("
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

            DROP TRIGGER IF EXISTS trg_historico_auto_falta_componente ON modules.falta_componente_curricular;
            CREATE TRIGGER trg_historico_auto_falta_componente
                AFTER INSERT OR UPDATE OR DELETE ON modules.falta_componente_curricular
                FOR EACH ROW
                EXECUTE FUNCTION modules.fcn_historico_auto_faltas();

            DROP TRIGGER IF EXISTS trg_historico_auto_falta_geral ON modules.falta_geral;
            CREATE TRIGGER trg_historico_auto_falta_geral
                AFTER INSERT OR UPDATE OR DELETE ON modules.falta_geral
                FOR EACH ROW
                EXECUTE FUNCTION modules.fcn_historico_auto_faltas();
        ");
    }

    public function down(): void
    {
        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_historico_auto_matricula_situacao ON pmieducar.matricula;
            DROP TRIGGER IF EXISTS trg_historico_auto_media_componente ON modules.nota_componente_curricular_media;
            DROP TRIGGER IF EXISTS trg_historico_auto_media_geral ON modules.media_geral;
            DROP TRIGGER IF EXISTS trg_historico_auto_falta_componente ON modules.falta_componente_curricular;
            DROP TRIGGER IF EXISTS trg_historico_auto_falta_geral ON modules.falta_geral;

            DROP FUNCTION IF EXISTS pmieducar.fcn_historico_auto_matricula_situacao();
            DROP FUNCTION IF EXISTS modules.fcn_historico_auto_notas();
            DROP FUNCTION IF EXISTS modules.fcn_historico_auto_faltas();
            DROP FUNCTION IF EXISTS pmieducar.fcn_enfileirar_processamento_historico(integer, varchar, integer, integer);

            DROP INDEX IF EXISTS pmieducar.historico_escolar_ref_cod_matricula_ativo_idx;
            DROP TABLE IF EXISTS pmieducar.historico_processamento_fila;
        ');
    }
};
