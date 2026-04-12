-- Uso:
-- psql -h 127.0.0.1 -p 2345 -U ieducar -d ieducar_sislami -v ON_ERROR_STOP=1 -v user_id_cad=1 -v ano_letivo=2026 -f script/migrar_sislami_para_pmieducar.sql
--
-- Premissas:
-- 1) schema sislami_migracao ja populado pelo script import_sislami.py
-- 2) carga idempotente via tabelas de mapeamento

BEGIN;

CREATE SCHEMA IF NOT EXISTS sislami_migracao;

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_instituicao (
    id_sislami_instituicao integer PRIMARY KEY,
    cod_instituicao integer NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_escola (
    id_sislami_instituicao integer PRIMARY KEY,
    cod_escola integer NOT NULL UNIQUE,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_aluno (
    id_sislami_aluno bigint PRIMARY KEY,
    idpes numeric(8,0) NOT NULL,
    cod_aluno integer NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

-- Deduplicacao por CPF pode gerar mapeamento N:1 (varios ids SISLAMI para um mesmo idpes/cod_aluno)
ALTER TABLE sislami_migracao.mapa_aluno
  DROP CONSTRAINT IF EXISTS mapa_aluno_idpes_key;
ALTER TABLE sislami_migracao.mapa_aluno
  DROP CONSTRAINT IF EXISTS mapa_aluno_cod_aluno_key;

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_matricula (
    id_sislami_aluno bigint NOT NULL,
    id_sislami_instituicao integer NOT NULL,
    cod_matricula integer NOT NULL UNIQUE,
    migrado_em timestamp without time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (id_sislami_aluno, id_sislami_instituicao)
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_curso (
    id_sislami_instituicao integer PRIMARY KEY,
    cod_curso integer NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_serie (
    id_sislami_instituicao integer NOT NULL,
    id_sislami_etapa integer NOT NULL,
    cod_serie integer NOT NULL,
    cod_curso integer NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (id_sislami_instituicao, id_sislami_etapa)
);

ALTER TABLE sislami_migracao.mapa_curso
  DROP CONSTRAINT IF EXISTS mapa_curso_cod_curso_key;
ALTER TABLE sislami_migracao.mapa_serie
  DROP CONSTRAINT IF EXISTS mapa_serie_cod_serie_key;

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_turma (
    id_sislami_turma integer PRIMARY KEY,
    cod_turma integer NOT NULL UNIQUE,
    cod_serie integer,
    cod_escola integer,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_aluno_etapa_matricula (
    id_sislami_aluno_etapa bigint PRIMARY KEY,
    id_sislami_aluno bigint NOT NULL,
    id_sislami_instituicao integer NOT NULL,
    id_sislami_turma integer,
    cod_matricula integer NOT NULL UNIQUE,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_componente_curricular (
    id_sislami_programa_item integer NOT NULL,
    id_sislami_instituicao integer NOT NULL,
    componente_curricular_id integer NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (id_sislami_programa_item, id_sislami_instituicao)
);

ALTER TABLE sislami_migracao.mapa_componente_curricular
  DROP CONSTRAINT IF EXISTS mapa_componente_curricular_componente_curricular_id_key;

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_historico_escolar (
    id_sislami_historico bigint PRIMARY KEY,
    historico_escolar_id integer NOT NULL UNIQUE,
    cod_aluno integer NOT NULL,
    sequencial integer NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_servidor (
    id_sislami_funcionario bigint PRIMARY KEY,
    idpes numeric(8,0) NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_funcao (
    id_sislami_funcao bigint PRIMARY KEY,
    cod_funcao integer NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

ALTER TABLE sislami_migracao.mapa_servidor
  DROP CONSTRAINT IF EXISTS mapa_servidor_idpes_key;

SELECT set_config('sislami.user_id_cad', :'user_id_cad', false);
SELECT set_config('sislami.ano_letivo', :'ano_letivo', false);

DO $$
DECLARE
    rec record;
    v_cod_instituicao integer;
    v_instituicao_base integer;
    v_cod_escola integer;
    v_idpes numeric(8,0);
    v_idpes_mae numeric(8,0);
    v_idpes_pai numeric(8,0);
    v_cod_aluno integer;
    v_cod_matricula integer;
    v_cep numeric(8,0);
    v_cpf numeric(11,0);
    v_cpf_mae numeric(11,0);
    v_cpf_pai numeric(11,0);
    v_idorg_rg integer;
    v_def_visual integer;
    v_def_auditiva integer;
    v_def_fisica integer;
    v_def_mental integer;
    v_def_multipla integer;
    v_def_superdotado integer;
    v_telefone_empresa numeric;
    v_ddd_telefone_empresa numeric;
    v_observacao_extra text;
    v_nacionalidade numeric;
    v_pais_residencia integer;
    v_cod_raca integer;
    v_nis numeric(11,0);
    v_localizacao_diferenciada integer;
    v_place_id integer;
    v_cod_curso integer;
    v_cod_serie integer;
    v_cod_turma integer;
    v_nivel_ensino integer;
    v_tipo_ensino integer;
    v_tipo_regime integer;
    v_turma_tipo integer;
    v_area_conhecimento integer;
    v_nota_aluno_id integer;
    v_falta_aluno_id integer;
    v_parecer_aluno_id integer;
    v_hist_id integer;
    v_tem_tb_instituicao boolean;
    v_user_id_cad integer := current_setting('sislami.user_id_cad')::int;
    v_ano_letivo integer := current_setting('sislami.ano_letivo')::int;
BEGIN
    -- Regra de negocio: no i-Educar, sempre usar instituicao codigo 1.
    v_instituicao_base := 1;
    IF NOT EXISTS (SELECT 1 FROM pmieducar.instituicao WHERE cod_instituicao = v_instituicao_base) THEN
        RAISE EXCEPTION 'Instituicao codigo 1 nao encontrada em pmieducar.instituicao.';
    END IF;

    -- Compatibilidade com execucoes antigas (quando havia UNIQUE em cod_instituicao).
    ALTER TABLE sislami_migracao.mapa_instituicao
      DROP CONSTRAINT IF EXISTS mapa_instituicao_cod_instituicao_key;

    -- Tipo de ensino (TB_TIPO_ENSINO -> pmieducar.tipo_ensino)
    IF to_regclass('sislami_raw.tb_tipo_ensino') IS NOT NULL THEN
        INSERT INTO pmieducar.tipo_ensino (
            ref_usuario_cad, nm_tipo, data_cadastro, ativo, ref_cod_instituicao, atividade_complementar
        )
        SELECT DISTINCT
            v_user_id_cad,
            LEFT(COALESCE(NULLIF(TRIM(te.dc_tipo_ensino), ''), NULLIF(TRIM(te.dc_tipo_ensino_reduzido), ''), 'Importação'), 255),
            now(),
            1,
            v_instituicao_base,
            false
        FROM sislami_raw.tb_tipo_ensino te
        WHERE COALESCE(NULLIF(TRIM(te.dc_tipo_ensino), ''), NULLIF(TRIM(te.dc_tipo_ensino_reduzido), ''), '') <> ''
          AND NOT EXISTS (
              SELECT 1
              FROM pmieducar.tipo_ensino pti
              WHERE pti.ref_cod_instituicao = v_instituicao_base
                AND lower(trim(pti.nm_tipo)) = lower(trim(COALESCE(NULLIF(TRIM(te.dc_tipo_ensino), ''), NULLIF(TRIM(te.dc_tipo_ensino_reduzido), ''))))
          );
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pmieducar.tipo_ensino) THEN
        INSERT INTO pmieducar.tipo_ensino (
            ref_usuario_cad, nm_tipo, data_cadastro, ativo, ref_cod_instituicao, atividade_complementar
        ) VALUES (
            v_user_id_cad, 'Importação', now(), 1, v_instituicao_base, false
        );
    END IF;
    SELECT MIN(cod_tipo_ensino) INTO v_tipo_ensino
    FROM pmieducar.tipo_ensino
    WHERE ref_cod_instituicao = v_instituicao_base;
    v_tipo_ensino := COALESCE(v_tipo_ensino, (SELECT MIN(cod_tipo_ensino) FROM pmieducar.tipo_ensino));

    -- Nivel de ensino (TB_NIVEL -> pmieducar.nivel_ensino)
    IF to_regclass('sislami_raw.tb_nivel') IS NOT NULL THEN
        INSERT INTO pmieducar.nivel_ensino (
            ref_usuario_cad, nm_nivel, descricao, data_cadastro, ativo, ref_cod_instituicao
        )
        SELECT DISTINCT
            v_user_id_cad,
            LEFT(COALESCE(NULLIF(TRIM(nv.dc_nivel), ''), NULLIF(TRIM(nv.dc_nivel_reduzido), ''), 'Importação'), 255),
            LEFT(COALESCE(NULLIF(TRIM(nv.dc_nivel_reduzido), ''), NULLIF(TRIM(nv.dc_nivel), ''), 'Importação SISLAME'), 255),
            now(),
            1,
            v_instituicao_base
        FROM sislami_raw.tb_nivel nv
        WHERE COALESCE(NULLIF(TRIM(nv.dc_nivel), ''), NULLIF(TRIM(nv.dc_nivel_reduzido), ''), '') <> ''
          AND NOT EXISTS (
              SELECT 1
              FROM pmieducar.nivel_ensino pne
              WHERE pne.ref_cod_instituicao = v_instituicao_base
                AND lower(trim(pne.nm_nivel)) = lower(trim(COALESCE(NULLIF(TRIM(nv.dc_nivel), ''), NULLIF(TRIM(nv.dc_nivel_reduzido), ''))))
          );
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pmieducar.nivel_ensino) THEN
        INSERT INTO pmieducar.nivel_ensino (
            ref_usuario_cad, nm_nivel, descricao, data_cadastro, ativo, ref_cod_instituicao
        ) VALUES (
            v_user_id_cad, 'Importação', 'Importação SISLAME', now(), 1, v_instituicao_base
        );
    END IF;
    SELECT MIN(cod_nivel_ensino) INTO v_nivel_ensino
    FROM pmieducar.nivel_ensino
    WHERE ref_cod_instituicao = v_instituicao_base;
    v_nivel_ensino := COALESCE(v_nivel_ensino, (SELECT MIN(cod_nivel_ensino) FROM pmieducar.nivel_ensino));

    IF NOT EXISTS (SELECT 1 FROM pmieducar.tipo_regime) THEN
        INSERT INTO pmieducar.tipo_regime (
            ref_usuario_cad, nm_tipo, data_cadastro, ativo, ref_cod_instituicao
        ) VALUES (
            v_user_id_cad, 'REGIME SISLAMI', now(), 1, v_instituicao_base
        )
        RETURNING cod_tipo_regime INTO v_tipo_regime;
    ELSE
        SELECT MIN(cod_tipo_regime) INTO v_tipo_regime FROM pmieducar.tipo_regime;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pmieducar.turma_tipo) THEN
        INSERT INTO pmieducar.turma_tipo (
            ref_usuario_cad, nm_tipo, sgl_tipo, data_cadastro, ativo, ref_cod_instituicao
        ) VALUES (
            v_user_id_cad, 'TURMA SISLAMI', 'SIS', now(), 1, v_instituicao_base
        )
        RETURNING cod_turma_tipo INTO v_turma_tipo;
    ELSE
        SELECT MIN(cod_turma_tipo) INTO v_turma_tipo FROM pmieducar.turma_tipo;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM modules.area_conhecimento) THEN
        INSERT INTO modules.area_conhecimento (
            instituicao_id, nome, secao, ordenamento_ac, agrupar_descritores
        ) VALUES (
            v_instituicao_base, 'AREA SISLAMI', NULL, 99999, false
        )
        RETURNING id INTO v_area_conhecimento;
    ELSE
        SELECT MIN(id) INTO v_area_conhecimento FROM modules.area_conhecimento;
    END IF;

    -- TB_INSTITUICAO do SISLAME representa unidades/escolas.
    -- Mapeamos todas para a instituicao 1 do i-Educar.
    v_tem_tb_instituicao := to_regclass('sislami_raw.tb_instituicao') IS NOT NULL;

    IF EXISTS (SELECT 1 FROM sislami_migracao.mapa_instituicao) THEN
        UPDATE sislami_migracao.mapa_instituicao
           SET cod_instituicao = v_instituicao_base
         WHERE cod_instituicao IS DISTINCT FROM v_instituicao_base;
    ELSIF v_tem_tb_instituicao THEN
        EXECUTE format($SQL$
            INSERT INTO sislami_migracao.mapa_instituicao (id_sislami_instituicao, cod_instituicao)
            SELECT
                NULLIF(TRIM(ti.id_instituicao), '')::int AS id_sislami_instituicao,
                %s
            FROM sislami_raw.tb_instituicao ti
            WHERE NULLIF(TRIM(ti.id_instituicao), '') IS NOT NULL
            ON CONFLICT (id_sislami_instituicao) DO UPDATE
               SET cod_instituicao = EXCLUDED.cod_instituicao
        $SQL$, v_instituicao_base);
    ELSIF to_regclass('sislami_raw.tb_aluno_instituicao') IS NOT NULL THEN
        EXECUTE format($SQL$
            INSERT INTO sislami_migracao.mapa_instituicao (id_sislami_instituicao, cod_instituicao)
            SELECT DISTINCT
                NULLIF(TRIM(ai.id_instituicao), '')::int,
                %s
            FROM sislami_raw.tb_aluno_instituicao ai
            WHERE NULLIF(TRIM(ai.id_instituicao), '') IS NOT NULL
            ON CONFLICT (id_sislami_instituicao) DO UPDATE
               SET cod_instituicao = EXCLUDED.cod_instituicao
        $SQL$, v_instituicao_base);
    ELSIF to_regclass('sislami_raw.tb_aluno_etapa') IS NOT NULL THEN
        EXECUTE format($SQL$
            INSERT INTO sislami_migracao.mapa_instituicao (id_sislami_instituicao, cod_instituicao)
            SELECT DISTINCT
                NULLIF(TRIM(ae.id_instituicao), '')::int,
                %s
            FROM sislami_raw.tb_aluno_etapa ae
            WHERE NULLIF(TRIM(ae.id_instituicao), '') IS NOT NULL
            ON CONFLICT (id_sislami_instituicao) DO UPDATE
               SET cod_instituicao = EXCLUDED.cod_instituicao
        $SQL$, v_instituicao_base);
    ELSIF to_regclass('sislami_raw.tb_funcionario_instituicao') IS NOT NULL THEN
        EXECUTE format($SQL$
            INSERT INTO sislami_migracao.mapa_instituicao (id_sislami_instituicao, cod_instituicao)
            SELECT DISTINCT
                NULLIF(TRIM(fi.id_instituicao), '')::int,
                %s
            FROM sislami_raw.tb_funcionario_instituicao fi
            WHERE NULLIF(TRIM(fi.id_instituicao), '') IS NOT NULL
            ON CONFLICT (id_sislami_instituicao) DO UPDATE
               SET cod_instituicao = EXCLUDED.cod_instituicao
        $SQL$, v_instituicao_base);
    ELSIF to_regclass('sislami_migracao.instituicao') IS NOT NULL THEN
        EXECUTE format($SQL$
            INSERT INTO sislami_migracao.mapa_instituicao (id_sislami_instituicao, cod_instituicao)
            SELECT
                i.id_sislami_instituicao,
                %s
            FROM sislami_migracao.instituicao i
            WHERE i.id_sislami_instituicao IS NOT NULL
            ON CONFLICT (id_sislami_instituicao) DO UPDATE
               SET cod_instituicao = EXCLUDED.cod_instituicao
        $SQL$, v_instituicao_base);
    ELSIF to_regclass('sislami_migracao.aluno_instituicao') IS NOT NULL THEN
        EXECUTE format($SQL$
            INSERT INTO sislami_migracao.mapa_instituicao (id_sislami_instituicao, cod_instituicao)
            SELECT DISTINCT
                ai.id_sislami_instituicao,
                %s
            FROM sislami_migracao.aluno_instituicao ai
            WHERE ai.id_sislami_instituicao IS NOT NULL
            ON CONFLICT (id_sislami_instituicao) DO UPDATE
               SET cod_instituicao = EXCLUDED.cod_instituicao
        $SQL$, v_instituicao_base);
    ELSE
        RAISE EXCEPTION 'Nao foi possivel montar mapa_instituicao: nenhuma fonte encontrada. Execute antes o import para sislami_raw no mesmo banco.';
    END IF;

    -- Escola (1:1 com instituicao do SISLAMI)
    IF v_tem_tb_instituicao THEN
        FOR rec IN
            SELECT
                mi.id_sislami_instituicao,
                mi.cod_instituicao,
                COALESCE(NULLIF(TRIM(ti.nm_instituicao), ''), 'ESCOLA SISLAMI ' || mi.id_sislami_instituicao::text) AS nm_instituicao
            FROM sislami_migracao.mapa_instituicao mi
            LEFT JOIN sislami_raw.tb_instituicao ti
                   ON NULLIF(TRIM(ti.id_instituicao), '')::int = mi.id_sislami_instituicao
            WHERE NOT EXISTS (
                SELECT 1
                FROM sislami_migracao.mapa_escola me
                WHERE me.id_sislami_instituicao = mi.id_sislami_instituicao
            )
            ORDER BY mi.id_sislami_instituicao
        LOOP
            INSERT INTO cadastro.pessoa (
                nome, data_cad, tipo, situacao, origem_gravacao, operacao
            ) VALUES (
                LEFT(rec.nm_instituicao, 150), now(), 'J', 'A', 'M', 'I'
            )
            RETURNING idpes INTO v_idpes;

            INSERT INTO cadastro.juridica (
                idpes, cnpj, origem_gravacao, data_cad, operacao, fantasia
            ) VALUES (
                v_idpes, NULL, 'M', now(), 'I', LEFT(rec.nm_instituicao, 150)
            );

            INSERT INTO pmieducar.escola (
                ref_usuario_cad,
                ref_cod_instituicao,
                ref_idpes,
                sigla,
                data_cadastro,
                ativo
            ) VALUES (
                v_user_id_cad,
                rec.cod_instituicao,
                v_idpes,
                LEFT('SL' || rec.id_sislami_instituicao::text, 20),
                now(),
                1
            )
            RETURNING cod_escola INTO v_cod_escola;

            INSERT INTO sislami_migracao.mapa_escola (id_sislami_instituicao, cod_escola)
            VALUES (rec.id_sislami_instituicao, v_cod_escola);
        END LOOP;
    ELSE
        FOR rec IN
            SELECT
                mi.id_sislami_instituicao,
                mi.cod_instituicao,
                'ESCOLA SISLAMI ' || mi.id_sislami_instituicao::text AS nm_instituicao
            FROM sislami_migracao.mapa_instituicao mi
            WHERE NOT EXISTS (
                SELECT 1
                FROM sislami_migracao.mapa_escola me
                WHERE me.id_sislami_instituicao = mi.id_sislami_instituicao
            )
            ORDER BY mi.id_sislami_instituicao
        LOOP
            INSERT INTO cadastro.pessoa (
                nome, data_cad, tipo, situacao, origem_gravacao, operacao
            ) VALUES (
                LEFT(rec.nm_instituicao, 150), now(), 'J', 'A', 'M', 'I'
            )
            RETURNING idpes INTO v_idpes;

            INSERT INTO cadastro.juridica (
                idpes, cnpj, origem_gravacao, data_cad, operacao, fantasia
            ) VALUES (
                v_idpes, NULL, 'M', now(), 'I', LEFT(rec.nm_instituicao, 150)
            );

            INSERT INTO pmieducar.escola (
                ref_usuario_cad,
                ref_cod_instituicao,
                ref_idpes,
                sigla,
                data_cadastro,
                ativo
            ) VALUES (
                v_user_id_cad,
                rec.cod_instituicao,
                v_idpes,
                LEFT('SL' || rec.id_sislami_instituicao::text, 20),
                now(),
                1
            )
            RETURNING cod_escola INTO v_cod_escola;

            INSERT INTO sislami_migracao.mapa_escola (id_sislami_instituicao, cod_escola)
            VALUES (rec.id_sislami_instituicao, v_cod_escola);
        END LOOP;
    END IF;

    -- Backfill: escolas antigas sem ref_idpes (pessoa juridica)
    IF v_tem_tb_instituicao THEN
        FOR rec IN
            SELECT
                me.id_sislami_instituicao,
                me.cod_escola,
                COALESCE(NULLIF(TRIM(ti.nm_instituicao), ''), 'ESCOLA SISLAMI ' || me.id_sislami_instituicao::text) AS nm_instituicao
            FROM sislami_migracao.mapa_escola me
            JOIN pmieducar.escola pe
              ON pe.cod_escola = me.cod_escola
            LEFT JOIN sislami_raw.tb_instituicao ti
                   ON NULLIF(TRIM(ti.id_instituicao), '')::int = me.id_sislami_instituicao
            WHERE pe.ref_idpes IS NULL
        LOOP
            INSERT INTO cadastro.pessoa (
                nome, data_cad, tipo, situacao, origem_gravacao, operacao
            ) VALUES (
                LEFT(rec.nm_instituicao, 150), now(), 'J', 'A', 'M', 'I'
            )
            RETURNING idpes INTO v_idpes;

            INSERT INTO cadastro.juridica (
                idpes, cnpj, origem_gravacao, data_cad, operacao, fantasia
            ) VALUES (
                v_idpes, NULL, 'M', now(), 'I', LEFT(rec.nm_instituicao, 150)
            );

            UPDATE pmieducar.escola
               SET ref_idpes = v_idpes
             WHERE cod_escola = rec.cod_escola
               AND ref_idpes IS NULL;
        END LOOP;
    ELSE
        FOR rec IN
            SELECT
                me.id_sislami_instituicao,
                me.cod_escola,
                'ESCOLA SISLAMI ' || me.id_sislami_instituicao::text AS nm_instituicao
            FROM sislami_migracao.mapa_escola me
            JOIN pmieducar.escola pe
              ON pe.cod_escola = me.cod_escola
            WHERE pe.ref_idpes IS NULL
        LOOP
            INSERT INTO cadastro.pessoa (
                nome, data_cad, tipo, situacao, origem_gravacao, operacao
            ) VALUES (
                LEFT(rec.nm_instituicao, 150), now(), 'J', 'A', 'M', 'I'
            )
            RETURNING idpes INTO v_idpes;

            INSERT INTO cadastro.juridica (
                idpes, cnpj, origem_gravacao, data_cad, operacao, fantasia
            ) VALUES (
                v_idpes, NULL, 'M', now(), 'I', LEFT(rec.nm_instituicao, 150)
            );

            UPDATE pmieducar.escola
               SET ref_idpes = v_idpes
             WHERE cod_escola = rec.cod_escola
               AND ref_idpes IS NULL;
        END LOOP;
    END IF;

    -- Aluno + pessoa + fisica
    SELECT COALESCE((SELECT cod_deficiencia FROM cadastro.deficiencia WHERE nm_deficiencia ILIKE 'Baixa Visão' ORDER BY cod_deficiencia LIMIT 1), 3) INTO v_def_visual;
    SELECT COALESCE((SELECT cod_deficiencia FROM cadastro.deficiencia WHERE nm_deficiencia ILIKE 'Deficiência Auditiva' ORDER BY cod_deficiencia LIMIT 1), 5) INTO v_def_auditiva;
    SELECT COALESCE((SELECT cod_deficiencia FROM cadastro.deficiencia WHERE nm_deficiencia ILIKE 'Deficiência Física' ORDER BY cod_deficiencia LIMIT 1), 7) INTO v_def_fisica;
    SELECT COALESCE((SELECT cod_deficiencia FROM cadastro.deficiencia WHERE nm_deficiencia ILIKE 'Deficiência Mental' ORDER BY cod_deficiencia LIMIT 1), 8) INTO v_def_mental;
    SELECT COALESCE((SELECT cod_deficiencia FROM cadastro.deficiencia WHERE nm_deficiencia ILIKE 'Deficiência Múltipla' ORDER BY cod_deficiencia LIMIT 1), 9) INTO v_def_multipla;
    SELECT COALESCE((SELECT cod_deficiencia FROM cadastro.deficiencia WHERE nm_deficiencia ILIKE 'Altas Habilidades/Superdotação' ORDER BY cod_deficiencia LIMIT 1), 14) INTO v_def_superdotado;

    FOR rec IN
        SELECT
            a.id_sislami_aluno,
            COALESCE(NULLIF(TRIM(a.nome), ''), 'ALUNO SISLAMI ' || a.id_sislami_aluno::text) AS nome,
            a.data_nascimento,
            CASE WHEN a.sexo IN ('M', 'F') THEN a.sexo ELSE NULL END AS sexo,
            NULLIF(TRIM(a.cor_raca), '') AS cor_raca,
            NULLIF(regexp_replace(COALESCE(a.cpf, ''), '[^0-9]', '', 'g'), '') AS cpf_limpo,
            NULLIF(regexp_replace(COALESCE(a.nis, ''), '[^0-9]', '', 'g'), '') AS nis_limpo,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_pis_pasep, ''), '[^0-9]', '', 'g'), '') AS pis_pasep_limpo,
            NULLIF(TRIM(a.nome_mae), '') AS nome_mae,
            NULLIF(TRIM(a.nome_pai), '') AS nome_pai,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_mae_cpf, ''), '[^0-9]', '', 'g'), '') AS cpf_mae_limpo,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_pai_cpf, ''), '[^0-9]', '', 'g'), '') AS cpf_pai_limpo,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_identidade, ''), '[^0-9A-Za-z]', '', 'g'), '') AS identidade_limpo,
            NULLIF(TRIM(raw_ad.dc_identidade_orgao), '') AS identidade_orgao,
            NULLIF(TRIM(raw_ad.sg_identidade_uf), '') AS identidade_uf,
            sislami_migracao.parse_sislami_timestamp(NULLIF(TRIM(raw_ad.dt_identidade_emissao), ''))::date AS data_identidade_emissao,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_titulo_eleitor, ''), '[^0-9]', '', 'g'), '') AS titulo_eleitor_limpo,
            NULLIF(regexp_replace(COALESCE(raw_ad.dc_titulo_eleitor_zona, ''), '[^0-9]', '', 'g'), '') AS titulo_eleitor_zona_limpo,
            NULLIF(regexp_replace(COALESCE(raw_ad.dc_titulo_eleitor_secao, ''), '[^0-9]', '', 'g'), '') AS titulo_eleitor_secao_limpo,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_carteira_trabalho, ''), '[^0-9]', '', 'g'), '') AS carteira_trabalho_limpo,
            NULLIF(TRIM(raw_ad.dc_carteira_trabalho_serie), '') AS carteira_trabalho_serie,
            NULLIF(TRIM(raw_ad.sg_carteira_trabalho_uf), '') AS carteira_trabalho_uf,
            NULLIF(TRIM(raw_ad.fl_deficiencia_mental), '') AS fl_deficiencia_mental,
            NULLIF(TRIM(raw_ad.fl_deficiencia_visual), '') AS fl_deficiencia_visual,
            NULLIF(TRIM(raw_ad.fl_deficiencia_auditiva), '') AS fl_deficiencia_auditiva,
            NULLIF(TRIM(raw_ad.fl_deficiencia_fisica), '') AS fl_deficiencia_fisica,
            NULLIF(TRIM(raw_ad.fl_deficiencia_multipla), '') AS fl_deficiencia_multipla,
            NULLIF(TRIM(raw_ad.fl_deficiencia_neuromotora), '') AS fl_deficiencia_neuromotora,
            NULLIF(TRIM(raw_ad.fl_deficiencia_superdotado), '') AS fl_deficiencia_superdotado,
            sislami_migracao.parse_sislami_timestamp(NULLIF(TRIM(raw_ad.dt_certidao_nascimento), ''))::date AS data_certidao_nascimento,
            NULLIF(regexp_replace(COALESCE(raw_ad.dc_certidao_nascimento_termo, ''), '[^0-9]', '', 'g'), '') AS certidao_termo_limpo,
            NULLIF(TRIM(raw_ad.dc_certidao_nascimento_livro), '') AS certidao_livro,
            NULLIF(regexp_replace(COALESCE(raw_ad.dc_certidao_nascimento_folha, ''), '[^0-9]', '', 'g'), '') AS certidao_folha_limpo,
            NULLIF(TRIM(raw_ad.dc_certidao_nascimento_cartorio), '') AS certidao_cartorio,
            NULLIF(TRIM(raw_ad.sg_certidao_nascimento_uf), '') AS certidao_uf,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_telefone_comercial, ''), '[^0-9]', '', 'g'), '') AS telefone_comercial_limpo,
            NULLIF(TRIM(raw_ad.dc_ocupacao_aluno), '') AS ocupacao_aluno,
            NULLIF(TRIM(raw_ad.dc_local_trabalho), '') AS local_trabalho,
            sislami_migracao.parse_sislami_timestamp(NULLIF(TRIM(raw_ad.dt_inicio_trabalho), ''))::date AS data_inicio_trabalho,
            NULLIF(TRIM(raw_ad.nm_responsavel), '') AS nome_responsavel,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_cns, ''), '[^0-9]', '', 'g'), '') AS cns_limpo,
            NULLIF(TRIM(raw_ad.dc_nacionalidade), '') AS nacionalidade_bruta,
            NULLIF(TRIM(raw_ad.tp_origem), '') AS tp_origem,
            NULLIF(TRIM(raw_ad.tp_moradia), '') AS tp_moradia,
            NULLIF(TRIM(raw_ad.tp_pai_escolaridade), '') AS tp_pai_escolaridade,
            NULLIF(TRIM(raw_ad.tp_mae_escolaridade), '') AS tp_mae_escolaridade,
            NULLIF(TRIM(raw_ad.dc_responsavel_profissao), '') AS profissao_responsavel,
            NULLIF(TRIM(raw_ad.tp_sanguineo), '') AS tp_sanguineo,
            NULLIF(TRIM(raw_ad.fl_bolsa_familia), '') AS fl_bolsa_familia,
            NULLIF(TRIM(raw_ad.dc_problema_saude), '') AS problema_saude,
            NULLIF(TRIM(raw_ad.dc_medicamento_utilizado), '') AS medicamento_utilizado,
            NULLIF(TRIM(raw_ad.dc_alergia_medicamento), '') AS alergia_medicamento,
            NULLIF(TRIM(raw_ad.dc_alergia_alimento), '') AS alergia_alimento,
            NULLIF(TRIM(raw_ad.nm_pessoa_busca_aluno), '') AS nm_pessoa_busca_aluno,
            NULLIF(TRIM(raw_ad.dc_grupo_social), '') AS grupo_social,
            NULLIF(TRIM(a.email), '') AS email,
            NULLIF(TRIM(a.nome_social), '') AS nome_social,
            NULLIF(TRIM(a.logradouro), '') AS logradouro,
            NULLIF(TRIM(a.numero), '') AS numero,
            NULLIF(TRIM(a.complemento), '') AS complemento,
            NULLIF(TRIM(a.bairro), '') AS bairro,
            NULLIF(TRIM(a.cep), '') AS cep,
            NULLIF(TRIM(raw_a.tp_localizacao_diferenciada), '') AS tp_localizacao_diferenciada
        FROM sislami_migracao.aluno a
        LEFT JOIN sislami_raw.tb_aluno raw_a
               ON NULLIF(TRIM(raw_a.id_aluno), '')::bigint = a.id_sislami_aluno
        LEFT JOIN sislami_raw.tb_aluno_dado raw_ad
               ON NULLIF(TRIM(raw_ad.id_aluno), '')::bigint = a.id_sislami_aluno
        WHERE a.id_sislami_aluno IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM sislami_migracao.mapa_aluno ma
              WHERE ma.id_sislami_aluno = a.id_sislami_aluno
          )
        ORDER BY a.id_sislami_aluno
    LOOP
        v_cpf := CASE
            WHEN rec.cpf_limpo IS NULL OR length(rec.cpf_limpo) > 11 THEN NULL
            ELSE NULLIF(rec.cpf_limpo, '')::numeric(11,0)
        END;
        v_nis := CASE
            WHEN COALESCE(rec.nis_limpo, rec.pis_pasep_limpo) IS NULL
              OR length(COALESCE(rec.nis_limpo, rec.pis_pasep_limpo)) > 11 THEN NULL
            ELSE NULLIF(COALESCE(rec.nis_limpo, rec.pis_pasep_limpo), '')::numeric(11,0)
        END;
        v_cod_raca := CASE
            WHEN rec.cor_raca IS NULL THEN NULL
            WHEN rec.cor_raca ~ '^[1-6]$' THEN rec.cor_raca::int
            WHEN upper(rec.cor_raca) IN ('B', 'BRANCA') THEN 1
            WHEN upper(rec.cor_raca) IN ('P', 'PRETA') THEN 2
            WHEN upper(rec.cor_raca) IN ('PARDA') THEN 3
            WHEN upper(rec.cor_raca) IN ('A', 'AMARELA') THEN 4
            WHEN upper(rec.cor_raca) IN ('I', 'INDIGENA', 'INDÍGENA') THEN 5
            WHEN upper(rec.cor_raca) IN ('N', 'NAO_DECLARADA', 'NÃO_DECLARADA', 'NAO DECLARADA', 'NÃO DECLARADA') THEN 6
            ELSE NULL
        END;
        v_localizacao_diferenciada := CASE
            WHEN rec.tp_localizacao_diferenciada ~ '^[0-9]+$' THEN rec.tp_localizacao_diferenciada::int
            ELSE NULL
        END;
        v_telefone_empresa := CASE
            WHEN rec.telefone_comercial_limpo IS NULL OR length(rec.telefone_comercial_limpo) > 11 THEN NULL
            ELSE rec.telefone_comercial_limpo::numeric
        END;
        v_ddd_telefone_empresa := CASE
            WHEN rec.telefone_comercial_limpo IS NOT NULL AND length(rec.telefone_comercial_limpo) >= 10
                THEN substring(rec.telefone_comercial_limpo from 1 for 2)::numeric
            ELSE NULL
        END;
        v_observacao_extra := NULLIF(
            concat_ws(E'\n',
                CASE WHEN rec.tp_origem IS NOT NULL THEN 'SISLAME tp_origem=' || rec.tp_origem END,
                CASE WHEN rec.tp_moradia IS NOT NULL THEN 'SISLAME tp_moradia=' || rec.tp_moradia END,
                CASE WHEN rec.tp_pai_escolaridade IS NOT NULL THEN 'SISLAME tp_pai_escolaridade=' || rec.tp_pai_escolaridade END,
                CASE WHEN rec.tp_mae_escolaridade IS NOT NULL THEN 'SISLAME tp_mae_escolaridade=' || rec.tp_mae_escolaridade END,
                CASE WHEN rec.profissao_responsavel IS NOT NULL THEN 'SISLAME profissao_responsavel=' || rec.profissao_responsavel END,
                CASE WHEN rec.tp_sanguineo IS NOT NULL THEN 'SISLAME tp_sanguineo=' || rec.tp_sanguineo END,
                CASE WHEN rec.fl_bolsa_familia IS NOT NULL THEN 'SISLAME bolsa_familia=' || rec.fl_bolsa_familia END,
                CASE WHEN rec.problema_saude IS NOT NULL THEN 'SISLAME problema_saude=' || rec.problema_saude END,
                CASE WHEN rec.medicamento_utilizado IS NOT NULL THEN 'SISLAME medicamento_utilizado=' || rec.medicamento_utilizado END,
                CASE WHEN rec.alergia_medicamento IS NOT NULL THEN 'SISLAME alergia_medicamento=' || rec.alergia_medicamento END,
                CASE WHEN rec.alergia_alimento IS NOT NULL THEN 'SISLAME alergia_alimento=' || rec.alergia_alimento END,
                CASE WHEN rec.nm_pessoa_busca_aluno IS NOT NULL THEN 'SISLAME pessoa_busca_aluno=' || rec.nm_pessoa_busca_aluno END,
                CASE WHEN rec.grupo_social IS NOT NULL THEN 'SISLAME grupo_social=' || rec.grupo_social END
            ),
            ''
        );
        v_nacionalidade := CASE
            WHEN rec.nacionalidade_bruta IS NULL THEN NULL
            WHEN rec.nacionalidade_bruta ~ '^[0-9]+$' AND length(rec.nacionalidade_bruta) <= 1 THEN rec.nacionalidade_bruta::numeric
            WHEN upper(rec.nacionalidade_bruta) IN ('BRASILEIRA', 'BRASILEIRO', 'BRASIL') THEN 1
            ELSE NULL
        END;
        v_cpf_mae := CASE
            WHEN rec.cpf_mae_limpo IS NULL OR length(rec.cpf_mae_limpo) > 11 THEN NULL
            ELSE NULLIF(rec.cpf_mae_limpo, '')::numeric(11,0)
        END;
        v_cpf_pai := CASE
            WHEN rec.cpf_pai_limpo IS NULL OR length(rec.cpf_pai_limpo) > 11 THEN NULL
            ELSE NULLIF(rec.cpf_pai_limpo, '')::numeric(11,0)
        END;

        v_idpes := NULL;
        IF v_cpf IS NOT NULL THEN
            SELECT f.idpes INTO v_idpes
            FROM cadastro.fisica f
            WHERE f.cpf = v_cpf
            ORDER BY f.idpes
            LIMIT 1;
        END IF;

        IF v_idpes IS NULL THEN
            INSERT INTO cadastro.pessoa (
                nome,
                data_cad,
                tipo,
                email,
                situacao,
                origem_gravacao,
                operacao
            ) VALUES (
                LEFT(rec.nome, 150),
                now(),
                'F',
                LEFT(rec.email, 100),
                'A',
                'M',
                'I'
            )
            RETURNING idpes INTO v_idpes;

            INSERT INTO cadastro.fisica (
                idpes,
                data_nasc,
                sexo,
                ideciv,
                nome_mae,
                nome_pai,
                origem_gravacao,
                data_cad,
                operacao,
                cpf,
                nome_social,
                nis_pis_pasep,
                localizacao_diferenciada,
                ocupacao,
                local_trabalho,
                data_admissao,
                nome_responsavel,
                sus,
                telefone_empresa,
                ddd_telefone_empresa,
                observacao,
                nacionalidade
            ) VALUES (
                v_idpes,
                rec.data_nascimento,
                rec.sexo,
                7,
                LEFT(rec.nome_mae, 150),
                LEFT(rec.nome_pai, 150),
                'M',
                now(),
                'I',
                v_cpf,
                LEFT(rec.nome_social, 150),
                v_nis,
                v_localizacao_diferenciada,
                LEFT(rec.ocupacao_aluno, 150),
                LEFT(rec.local_trabalho, 150),
                rec.data_inicio_trabalho,
                LEFT(rec.nome_responsavel, 150),
                LEFT(rec.cns_limpo, 30),
                v_telefone_empresa,
                v_ddd_telefone_empresa,
                v_observacao_extra,
                v_nacionalidade
            );
        ELSE
            UPDATE cadastro.pessoa
               SET nome = COALESCE(NULLIF(LEFT(rec.nome, 150), ''), nome),
                   email = COALESCE(NULLIF(LEFT(rec.email, 100), ''), email)
             WHERE idpes = v_idpes;

            UPDATE cadastro.fisica
               SET data_nasc = COALESCE(rec.data_nascimento, data_nasc),
                   sexo = COALESCE(rec.sexo, sexo),
                   ideciv = 7,
                   nome_mae = COALESCE(NULLIF(LEFT(rec.nome_mae, 150), ''), nome_mae),
                   nome_pai = COALESCE(NULLIF(LEFT(rec.nome_pai, 150), ''), nome_pai),
                   nome_social = COALESCE(NULLIF(LEFT(rec.nome_social, 150), ''), nome_social),
                   nis_pis_pasep = COALESCE(v_nis, nis_pis_pasep),
                   localizacao_diferenciada = COALESCE(v_localizacao_diferenciada, localizacao_diferenciada),
                   ocupacao = COALESCE(NULLIF(LEFT(rec.ocupacao_aluno, 150), ''), ocupacao),
                   local_trabalho = COALESCE(NULLIF(LEFT(rec.local_trabalho, 150), ''), local_trabalho),
                   data_admissao = COALESCE(rec.data_inicio_trabalho, data_admissao),
                   nome_responsavel = COALESCE(NULLIF(LEFT(rec.nome_responsavel, 150), ''), nome_responsavel),
                   sus = COALESCE(NULLIF(LEFT(rec.cns_limpo, 30), ''), sus),
                   telefone_empresa = COALESCE(v_telefone_empresa, telefone_empresa),
                   ddd_telefone_empresa = COALESCE(v_ddd_telefone_empresa, ddd_telefone_empresa),
                   nacionalidade = COALESCE(v_nacionalidade, nacionalidade),
                   observacao = CASE
                       WHEN v_observacao_extra IS NULL THEN observacao
                       WHEN observacao IS NULL OR btrim(observacao) = '' THEN v_observacao_extra
                       WHEN position(v_observacao_extra in observacao) > 0 THEN observacao
                       ELSE observacao || E'\n' || v_observacao_extra
                   END
             WHERE idpes = v_idpes;
        END IF;

        IF v_cod_raca IS NOT NULL THEN
            INSERT INTO cadastro.fisica_raca (ref_idpes, ref_cod_raca)
            VALUES (v_idpes::int, v_cod_raca)
            ON CONFLICT (ref_idpes) DO UPDATE
               SET ref_cod_raca = EXCLUDED.ref_cod_raca;
        END IF;

        -- Endereco do aluno no cadastro (view cadastro.endereco_pessoa)
        IF rec.logradouro IS NOT NULL OR rec.numero IS NOT NULL OR rec.bairro IS NOT NULL OR rec.cep IS NOT NULL THEN
            SELECT php.place_id
              INTO v_place_id
              FROM person_has_place php
             WHERE php.person_id = v_idpes::integer
               AND php.type = 1
             ORDER BY php.id DESC
             LIMIT 1;

            IF v_place_id IS NULL THEN
                INSERT INTO places (
                    address, number, complement, neighborhood, postal_code, created_at, updated_at
                ) VALUES (
                    LEFT(rec.logradouro, 255),
                    LEFT(rec.numero, 20),
                    LEFT(rec.complemento, 150),
                    LEFT(rec.bairro, 150),
                    LEFT(regexp_replace(COALESCE(rec.cep, ''), '[^0-9]', '', 'g'), 10),
                    now(),
                    now()
                )
                RETURNING id INTO v_place_id;

                INSERT INTO person_has_place (
                    person_id, place_id, type, created_at, updated_at
                ) VALUES (
                    v_idpes::integer, v_place_id, 1, now(), now()
                )
                ON CONFLICT (person_id, type) DO UPDATE
                   SET place_id = EXCLUDED.place_id,
                       updated_at = now();
            ELSE
                UPDATE places
                   SET address = COALESCE(NULLIF(LEFT(rec.logradouro, 255), ''), address),
                       number = COALESCE(NULLIF(LEFT(rec.numero, 20), ''), number),
                       complement = COALESCE(NULLIF(LEFT(rec.complemento, 150), ''), complement),
                       neighborhood = COALESCE(NULLIF(LEFT(rec.bairro, 150), ''), neighborhood),
                       postal_code = COALESCE(NULLIF(LEFT(regexp_replace(COALESCE(rec.cep, ''), '[^0-9]', '', 'g'), 10), ''), postal_code),
                       updated_at = now()
                 WHERE id = v_place_id;
            END IF;
        END IF;

        v_idorg_rg := NULL;
        IF rec.identidade_orgao IS NOT NULL THEN
            SELECT o.idorg_rg
              INTO v_idorg_rg
              FROM cadastro.orgao_emissor_rg o
             WHERE upper(trim(o.sigla)) = upper(trim(rec.identidade_orgao))
                OR upper(trim(o.descricao)) = upper(trim(rec.identidade_orgao))
             ORDER BY
                CASE WHEN upper(trim(o.sigla)) = upper(trim(rec.identidade_orgao)) THEN 0 ELSE 1 END,
                o.idorg_rg
             LIMIT 1;
        END IF;

        INSERT INTO cadastro.documento (
            idpes,
            rg,
            data_exp_rg,
            sigla_uf_exp_rg,
            num_cart_trabalho,
            serie_cart_trabalho,
            sigla_uf_cart_trabalho,
            num_tit_eleitor,
            zona_tit_eleitor,
            secao_tit_eleitor,
            data_emissao_cert_civil,
            num_termo,
            num_livro,
            num_folha,
            sigla_uf_cert_civil,
            cartorio_cert_civil,
            certidao_nascimento,
            idorg_exp_rg,
            origem_gravacao,
            data_cad,
            operacao
        ) VALUES (
            v_idpes,
            LEFT(rec.identidade_limpo, 20),
            rec.data_identidade_emissao,
            LEFT(rec.identidade_uf, 2),
            CASE WHEN rec.carteira_trabalho_limpo ~ '^[0-9]+$' AND length(rec.carteira_trabalho_limpo) <= 9 THEN rec.carteira_trabalho_limpo::numeric ELSE NULL END,
            CASE WHEN rec.carteira_trabalho_serie ~ '^[0-9]+$' AND length(rec.carteira_trabalho_serie) <= 5 THEN rec.carteira_trabalho_serie::numeric ELSE NULL END,
            LEFT(rec.carteira_trabalho_uf, 2),
            CASE WHEN rec.titulo_eleitor_limpo ~ '^[0-9]+$' AND length(rec.titulo_eleitor_limpo) <= 13 THEN rec.titulo_eleitor_limpo::numeric ELSE NULL END,
            CASE WHEN rec.titulo_eleitor_zona_limpo ~ '^[0-9]+$' AND length(rec.titulo_eleitor_zona_limpo) <= 4 THEN rec.titulo_eleitor_zona_limpo::numeric ELSE NULL END,
            CASE WHEN rec.titulo_eleitor_secao_limpo ~ '^[0-9]+$' AND length(rec.titulo_eleitor_secao_limpo) <= 4 THEN rec.titulo_eleitor_secao_limpo::numeric ELSE NULL END,
            rec.data_certidao_nascimento,
            CASE WHEN rec.certidao_termo_limpo ~ '^[0-9]+$' AND length(rec.certidao_termo_limpo) <= 8 THEN rec.certidao_termo_limpo::numeric ELSE NULL END,
            LEFT(rec.certidao_livro, 8),
            CASE WHEN rec.certidao_folha_limpo ~ '^[0-9]+$' AND length(rec.certidao_folha_limpo) <= 4 THEN rec.certidao_folha_limpo::numeric ELSE NULL END,
            LEFT(rec.certidao_uf, 2),
            LEFT(rec.certidao_cartorio, 200),
            LEFT(COALESCE(rec.certidao_termo_limpo, rec.certidao_livro, rec.certidao_folha_limpo), 50),
            v_idorg_rg,
            'M',
            now(),
            'I'
        )
        ON CONFLICT (idpes) DO UPDATE
           SET rg = COALESCE(NULLIF(EXCLUDED.rg, ''), cadastro.documento.rg),
               data_exp_rg = COALESCE(EXCLUDED.data_exp_rg, cadastro.documento.data_exp_rg),
               sigla_uf_exp_rg = COALESCE(NULLIF(EXCLUDED.sigla_uf_exp_rg, ''), cadastro.documento.sigla_uf_exp_rg),
               num_cart_trabalho = COALESCE(EXCLUDED.num_cart_trabalho, cadastro.documento.num_cart_trabalho),
               serie_cart_trabalho = COALESCE(EXCLUDED.serie_cart_trabalho, cadastro.documento.serie_cart_trabalho),
               sigla_uf_cart_trabalho = COALESCE(NULLIF(EXCLUDED.sigla_uf_cart_trabalho, ''), cadastro.documento.sigla_uf_cart_trabalho),
               num_tit_eleitor = COALESCE(EXCLUDED.num_tit_eleitor, cadastro.documento.num_tit_eleitor),
               zona_tit_eleitor = COALESCE(EXCLUDED.zona_tit_eleitor, cadastro.documento.zona_tit_eleitor),
               secao_tit_eleitor = COALESCE(EXCLUDED.secao_tit_eleitor, cadastro.documento.secao_tit_eleitor),
               data_emissao_cert_civil = COALESCE(EXCLUDED.data_emissao_cert_civil, cadastro.documento.data_emissao_cert_civil),
               num_termo = COALESCE(EXCLUDED.num_termo, cadastro.documento.num_termo),
               num_livro = COALESCE(NULLIF(EXCLUDED.num_livro, ''), cadastro.documento.num_livro),
               num_folha = COALESCE(EXCLUDED.num_folha, cadastro.documento.num_folha),
               sigla_uf_cert_civil = COALESCE(NULLIF(EXCLUDED.sigla_uf_cert_civil, ''), cadastro.documento.sigla_uf_cert_civil),
               cartorio_cert_civil = COALESCE(NULLIF(EXCLUDED.cartorio_cert_civil, ''), cadastro.documento.cartorio_cert_civil),
               certidao_nascimento = COALESCE(NULLIF(EXCLUDED.certidao_nascimento, ''), cadastro.documento.certidao_nascimento),
               idorg_exp_rg = COALESCE(EXCLUDED.idorg_exp_rg, cadastro.documento.idorg_exp_rg),
               data_rev = now(),
               operacao = 'A';

        -- Deficiencias: sincroniza flags do SISLAME para o cadastro.fisica_deficiencia
        DELETE FROM cadastro.fisica_deficiencia
         WHERE ref_idpes = v_idpes::int
           AND ref_cod_deficiencia IN (v_def_visual, v_def_auditiva, v_def_fisica, v_def_mental, v_def_multipla, v_def_superdotado);

        IF upper(COALESCE(rec.fl_deficiencia_visual, '0')) IN ('1', 'S', 'SIM', 'T', 'TRUE') THEN
            INSERT INTO cadastro.fisica_deficiencia (ref_idpes, ref_cod_deficiencia)
            VALUES (v_idpes::int, v_def_visual)
            ON CONFLICT DO NOTHING;
        END IF;

        IF upper(COALESCE(rec.fl_deficiencia_auditiva, '0')) IN ('1', 'S', 'SIM', 'T', 'TRUE') THEN
            INSERT INTO cadastro.fisica_deficiencia (ref_idpes, ref_cod_deficiencia)
            VALUES (v_idpes::int, v_def_auditiva)
            ON CONFLICT DO NOTHING;
        END IF;

        IF upper(COALESCE(rec.fl_deficiencia_fisica, '0')) IN ('1', 'S', 'SIM', 'T', 'TRUE')
           OR upper(COALESCE(rec.fl_deficiencia_neuromotora, '0')) IN ('1', 'S', 'SIM', 'T', 'TRUE') THEN
            INSERT INTO cadastro.fisica_deficiencia (ref_idpes, ref_cod_deficiencia)
            VALUES (v_idpes::int, v_def_fisica)
            ON CONFLICT DO NOTHING;
        END IF;

        IF upper(COALESCE(rec.fl_deficiencia_mental, '0')) IN ('1', 'S', 'SIM', 'T', 'TRUE') THEN
            INSERT INTO cadastro.fisica_deficiencia (ref_idpes, ref_cod_deficiencia)
            VALUES (v_idpes::int, v_def_mental)
            ON CONFLICT DO NOTHING;
        END IF;

        IF upper(COALESCE(rec.fl_deficiencia_multipla, '0')) IN ('1', 'S', 'SIM', 'T', 'TRUE') THEN
            INSERT INTO cadastro.fisica_deficiencia (ref_idpes, ref_cod_deficiencia)
            VALUES (v_idpes::int, v_def_multipla)
            ON CONFLICT DO NOTHING;
        END IF;

        IF upper(COALESCE(rec.fl_deficiencia_superdotado, '0')) IN ('1', 'S', 'SIM', 'T', 'TRUE') THEN
            INSERT INTO cadastro.fisica_deficiencia (ref_idpes, ref_cod_deficiencia)
            VALUES (v_idpes::int, v_def_superdotado)
            ON CONFLICT DO NOTHING;
        END IF;

        SELECT cod_aluno INTO v_cod_aluno
        FROM pmieducar.aluno
        WHERE ref_idpes = v_idpes
        ORDER BY cod_aluno
        LIMIT 1;

        IF v_cod_aluno IS NULL THEN
            INSERT INTO pmieducar.aluno (
                ref_idpes,
                data_cadastro,
                ativo,
                nm_mae,
                nm_pai
            ) VALUES (
                v_idpes,
                now(),
                1,
                LEFT(rec.nome_mae, 255),
                LEFT(rec.nome_pai, 255)
            )
            RETURNING cod_aluno INTO v_cod_aluno;
        ELSE
            UPDATE pmieducar.aluno
               SET nm_mae = COALESCE(NULLIF(LEFT(rec.nome_mae, 255), ''), nm_mae),
                   nm_pai = COALESCE(NULLIF(LEFT(rec.nome_pai, 255), ''), nm_pai)
             WHERE cod_aluno = v_cod_aluno;
        END IF;

        v_idpes_mae := NULL;
        IF NULLIF(LEFT(rec.nome_mae, 150), '') IS NOT NULL OR v_cpf_mae IS NOT NULL THEN
            IF v_cpf_mae IS NOT NULL THEN
                SELECT f2.idpes INTO v_idpes_mae
                FROM cadastro.fisica f2
                WHERE f2.cpf = v_cpf_mae
                ORDER BY f2.idpes
                LIMIT 1;
            END IF;

            IF v_idpes_mae IS NULL AND NULLIF(LEFT(rec.nome_mae, 150), '') IS NOT NULL THEN
                SELECT p.idpes INTO v_idpes_mae
                FROM cadastro.pessoa p
                JOIN cadastro.fisica f2 ON f2.idpes = p.idpes
                WHERE p.tipo = 'F'
                  AND lower(trim(p.nome)) = lower(trim(LEFT(rec.nome_mae, 150)))
                ORDER BY p.idpes
                LIMIT 1;
            END IF;

            IF v_idpes_mae IS NULL THEN
                INSERT INTO cadastro.pessoa (
                    nome, data_cad, tipo, situacao, origem_gravacao, operacao
                ) VALUES (
                    COALESCE(LEFT(rec.nome_mae, 150), 'MAE SISLAME ' || rec.id_sislami_aluno::text),
                    now(), 'F', 'A', 'M', 'I'
                )
                RETURNING idpes INTO v_idpes_mae;

                INSERT INTO cadastro.fisica (
                    idpes, sexo, ideciv, origem_gravacao, data_cad, operacao, cpf
                ) VALUES (
                    v_idpes_mae, 'F', 7, 'M', now(), 'I', v_cpf_mae
                );
            ELSIF v_cpf_mae IS NOT NULL THEN
                UPDATE cadastro.fisica
                   SET cpf = COALESCE(cpf, v_cpf_mae)
                 WHERE idpes = v_idpes_mae;
            END IF;
        END IF;

        v_idpes_pai := NULL;
        IF NULLIF(LEFT(rec.nome_pai, 150), '') IS NOT NULL OR v_cpf_pai IS NOT NULL THEN
            IF v_cpf_pai IS NOT NULL THEN
                SELECT f2.idpes INTO v_idpes_pai
                FROM cadastro.fisica f2
                WHERE f2.cpf = v_cpf_pai
                ORDER BY f2.idpes
                LIMIT 1;
            END IF;

            IF v_idpes_pai IS NULL AND NULLIF(LEFT(rec.nome_pai, 150), '') IS NOT NULL THEN
                SELECT p.idpes INTO v_idpes_pai
                FROM cadastro.pessoa p
                JOIN cadastro.fisica f2 ON f2.idpes = p.idpes
                WHERE p.tipo = 'F'
                  AND lower(trim(p.nome)) = lower(trim(LEFT(rec.nome_pai, 150)))
                ORDER BY p.idpes
                LIMIT 1;
            END IF;

            IF v_idpes_pai IS NULL THEN
                INSERT INTO cadastro.pessoa (
                    nome, data_cad, tipo, situacao, origem_gravacao, operacao
                ) VALUES (
                    COALESCE(LEFT(rec.nome_pai, 150), 'PAI SISLAME ' || rec.id_sislami_aluno::text),
                    now(), 'F', 'A', 'M', 'I'
                )
                RETURNING idpes INTO v_idpes_pai;

                INSERT INTO cadastro.fisica (
                    idpes, sexo, ideciv, origem_gravacao, data_cad, operacao, cpf
                ) VALUES (
                    v_idpes_pai, 'M', 7, 'M', now(), 'I', v_cpf_pai
                );
            ELSIF v_cpf_pai IS NOT NULL THEN
                UPDATE cadastro.fisica
                   SET cpf = COALESCE(cpf, v_cpf_pai)
                 WHERE idpes = v_idpes_pai;
            END IF;
        END IF;

        UPDATE cadastro.fisica
           SET idpes_mae = COALESCE(v_idpes_mae, idpes_mae),
               idpes_pai = COALESCE(v_idpes_pai, idpes_pai),
               nome_mae = COALESCE(NULLIF(LEFT(rec.nome_mae, 150), ''), nome_mae),
               nome_pai = COALESCE(NULLIF(LEFT(rec.nome_pai, 150), ''), nome_pai)
         WHERE idpes = v_idpes;

        INSERT INTO sislami_migracao.mapa_aluno (id_sislami_aluno, idpes, cod_aluno)
        VALUES (rec.id_sislami_aluno, v_idpes, v_cod_aluno);
    END LOOP;

    -- Backfill de nomes de pai/mae para alunos previamente mapeados
    UPDATE cadastro.fisica f
       SET nome_mae = COALESCE(NULLIF(LEFT(a.nome_mae, 150), ''), f.nome_mae),
           nome_pai = COALESCE(NULLIF(LEFT(a.nome_pai, 150), ''), f.nome_pai)
      FROM sislami_migracao.mapa_aluno ma
      JOIN sislami_migracao.aluno a
        ON a.id_sislami_aluno = ma.id_sislami_aluno
     WHERE f.idpes = ma.idpes;

    UPDATE pmieducar.aluno pa
       SET nm_mae = COALESCE(NULLIF(LEFT(a.nome_mae, 255), ''), pa.nm_mae),
           nm_pai = COALESCE(NULLIF(LEFT(a.nome_pai, 255), ''), pa.nm_pai)
      FROM sislami_migracao.mapa_aluno ma
      JOIN sislami_migracao.aluno a
        ON a.id_sislami_aluno = ma.id_sislami_aluno
     WHERE pa.cod_aluno = ma.cod_aluno;

    -- Codigo INEP do aluno (TB_ALUNO.CD_INEP -> modules.educacenso_cod_aluno)
    INSERT INTO modules.educacenso_cod_aluno (
        cod_aluno,
        cod_aluno_inep,
        nome_inep,
        fonte,
        created_at,
        updated_at
    )
    SELECT
        src.cod_aluno,
        src.cod_aluno_inep,
        src.nome_inep,
        'SISLAME' AS fonte,
        now() AS created_at,
        now() AS updated_at
    FROM (
        SELECT DISTINCT ON (ma.cod_aluno, inep.cod_aluno_inep)
            ma.cod_aluno,
            inep.cod_aluno_inep,
            LEFT(COALESCE(NULLIF(TRIM(ta.nm_aluno), ''), 'ALUNO SISLAME'), 255) AS nome_inep
        FROM sislami_migracao.mapa_aluno ma
        JOIN sislami_raw.tb_aluno ta
          ON NULLIF(TRIM(ta.id_aluno), '')::bigint = ma.id_sislami_aluno
        CROSS JOIN LATERAL (
            SELECT NULLIF(regexp_replace(COALESCE(TRIM(ta.cd_inep), ''), '[^0-9]', '', 'g'), '')::bigint AS cod_aluno_inep
        ) inep
        WHERE inep.cod_aluno_inep IS NOT NULL
        ORDER BY
            ma.cod_aluno,
            inep.cod_aluno_inep,
            (NULLIF(TRIM(ta.nm_aluno), '') IS NOT NULL) DESC,
            ma.id_sislami_aluno
    ) src
    ON CONFLICT (cod_aluno, cod_aluno_inep) DO UPDATE
       SET nome_inep = EXCLUDED.nome_inep,
           fonte = EXCLUDED.fonte,
           updated_at = now();

    -- Backfill: cria pai/mae como pessoa/fisica e vincula na fisica do aluno
    FOR rec IN
        SELECT
            ma.idpes AS idpes_aluno,
            NULLIF(LEFT(a.nome_mae, 150), '') AS nome_mae,
            NULLIF(LEFT(a.nome_pai, 150), '') AS nome_pai,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_mae_cpf, ''), '[^0-9]', '', 'g'), '') AS cpf_mae_limpo,
            NULLIF(regexp_replace(COALESCE(raw_ad.nu_pai_cpf, ''), '[^0-9]', '', 'g'), '') AS cpf_pai_limpo
        FROM sislami_migracao.mapa_aluno ma
        JOIN sislami_migracao.aluno a
          ON a.id_sislami_aluno = ma.id_sislami_aluno
        LEFT JOIN sislami_raw.tb_aluno raw_a
               ON NULLIF(TRIM(raw_a.id_aluno), '')::bigint = a.id_sislami_aluno
        LEFT JOIN sislami_raw.tb_aluno_dado raw_ad
               ON NULLIF(TRIM(raw_ad.id_aluno), '')::bigint = a.id_sislami_aluno
    LOOP
        v_cpf_mae := CASE
            WHEN rec.cpf_mae_limpo IS NULL OR length(rec.cpf_mae_limpo) > 11 THEN NULL
            ELSE NULLIF(rec.cpf_mae_limpo, '')::numeric(11,0)
        END;
        v_cpf_pai := CASE
            WHEN rec.cpf_pai_limpo IS NULL OR length(rec.cpf_pai_limpo) > 11 THEN NULL
            ELSE NULLIF(rec.cpf_pai_limpo, '')::numeric(11,0)
        END;

        v_idpes_mae := NULL;
        IF rec.nome_mae IS NOT NULL OR v_cpf_mae IS NOT NULL THEN
            IF v_cpf_mae IS NOT NULL THEN
                SELECT f2.idpes INTO v_idpes_mae
                FROM cadastro.fisica f2
                WHERE f2.cpf = v_cpf_mae
                ORDER BY f2.idpes
                LIMIT 1;
            END IF;

            IF v_idpes_mae IS NULL AND rec.nome_mae IS NOT NULL THEN
                SELECT p.idpes INTO v_idpes_mae
                FROM cadastro.pessoa p
                JOIN cadastro.fisica f2 ON f2.idpes = p.idpes
                WHERE p.tipo = 'F'
                  AND lower(trim(p.nome)) = lower(trim(rec.nome_mae))
                ORDER BY p.idpes
                LIMIT 1;
            END IF;

            IF v_idpes_mae IS NULL THEN
                INSERT INTO cadastro.pessoa (
                    nome, data_cad, tipo, situacao, origem_gravacao, operacao
                ) VALUES (
                    COALESCE(rec.nome_mae, 'MAE SISLAME ' || rec.idpes_aluno::text), now(), 'F', 'A', 'M', 'I'
                )
                RETURNING idpes INTO v_idpes_mae;

                INSERT INTO cadastro.fisica (
                    idpes, sexo, ideciv, origem_gravacao, data_cad, operacao, cpf
                ) VALUES (
                    v_idpes_mae, 'F', 7, 'M', now(), 'I', v_cpf_mae
                );
            ELSIF v_cpf_mae IS NOT NULL THEN
                UPDATE cadastro.fisica
                   SET cpf = COALESCE(cpf, v_cpf_mae)
                 WHERE idpes = v_idpes_mae;
            END IF;
        END IF;

        v_idpes_pai := NULL;
        IF rec.nome_pai IS NOT NULL OR v_cpf_pai IS NOT NULL THEN
            IF v_cpf_pai IS NOT NULL THEN
                SELECT f2.idpes INTO v_idpes_pai
                FROM cadastro.fisica f2
                WHERE f2.cpf = v_cpf_pai
                ORDER BY f2.idpes
                LIMIT 1;
            END IF;

            IF v_idpes_pai IS NULL AND rec.nome_pai IS NOT NULL THEN
                SELECT p.idpes INTO v_idpes_pai
                FROM cadastro.pessoa p
                JOIN cadastro.fisica f2 ON f2.idpes = p.idpes
                WHERE p.tipo = 'F'
                  AND lower(trim(p.nome)) = lower(trim(rec.nome_pai))
                ORDER BY p.idpes
                LIMIT 1;
            END IF;

            IF v_idpes_pai IS NULL THEN
                INSERT INTO cadastro.pessoa (
                    nome, data_cad, tipo, situacao, origem_gravacao, operacao
                ) VALUES (
                    COALESCE(rec.nome_pai, 'PAI SISLAME ' || rec.idpes_aluno::text), now(), 'F', 'A', 'M', 'I'
                )
                RETURNING idpes INTO v_idpes_pai;

                INSERT INTO cadastro.fisica (
                    idpes, sexo, ideciv, origem_gravacao, data_cad, operacao, cpf
                ) VALUES (
                    v_idpes_pai, 'M', 7, 'M', now(), 'I', v_cpf_pai
                );
            ELSIF v_cpf_pai IS NOT NULL THEN
                UPDATE cadastro.fisica
                   SET cpf = COALESCE(cpf, v_cpf_pai)
                 WHERE idpes = v_idpes_pai;
            END IF;
        END IF;

        UPDATE cadastro.fisica
           SET idpes_mae = COALESCE(v_idpes_mae, idpes_mae),
               idpes_pai = COALESCE(v_idpes_pai, idpes_pai)
         WHERE idpes = rec.idpes_aluno;
    END LOOP;

    -- Servidor: pessoa + fisica
    FOR rec IN
        SELECT
            NULLIF(TRIM(f.id_funcionario), '')::bigint AS id_funcionario,
            COALESCE(NULLIF(TRIM(f.nm_funcionario), ''), 'SERVIDOR SISLAMI ' || NULLIF(TRIM(f.id_funcionario), '')) AS nome,
            sislami_migracao.parse_sislami_timestamp(NULLIF(TRIM(f.dt_nascimento), ''))::date AS data_nascimento,
            CASE WHEN NULLIF(TRIM(f.tp_sexo), '') IN ('M', 'F') THEN NULLIF(TRIM(f.tp_sexo), '') ELSE NULL END AS sexo,
            NULLIF(TRIM(f.nm_mae), '') AS nome_mae,
            NULLIF(TRIM(f.nm_pai), '') AS nome_pai,
            NULLIF(regexp_replace(COALESCE(fd.nu_cpf, f.nu_cpf_importacao, ''), '[^0-9]', '', 'g'), '') AS cpf_limpo,
            COALESCE(NULLIF(TRIM(fd.ed_email), ''), NULLIF(TRIM(f.ed_email), '')) AS email,
            NULLIF(regexp_replace(COALESCE(f.nu_nis, ''), '[^0-9]', '', 'g'), '') AS nis_limpo,
            NULLIF(TRIM(f.tp_cor), '') AS cor_raca,
            NULLIF(TRIM(f.tp_nacionalidade), '') AS nacionalidade_bruta,
            NULLIF(TRIM(f.tp_localizacao_diferenciada), '') AS tp_localizacao_diferenciada,
            NULLIF(regexp_replace(COALESCE(f.cd_censo_pais_residencia, ''), '[^0-9]', '', 'g'), '') AS pais_residencia_limpo,
            NULLIF(TRIM(f.ed_logradouro), '') AS logradouro,
            NULLIF(TRIM(f.ed_numero), '') AS numero,
            NULLIF(TRIM(f.ed_complemento), '') AS complemento,
            NULLIF(TRIM(f.ed_bairro), '') AS bairro,
            NULLIF(TRIM(f.ed_cep), '') AS cep,
            NULLIF(TRIM(f.ed_municipio), '') AS municipio,
            NULLIF(TRIM(f.ed_uf), '') AS uf,
            NULLIF(regexp_replace(COALESCE(f.cd_inep, ''), '[^0-9]', '', 'g'), '') AS cd_inep_limpo
        FROM sislami_raw.tb_funcionario f
        LEFT JOIN sislami_raw.tb_funcionario_dado fd
               ON NULLIF(TRIM(fd.id_funcionario), '') = NULLIF(TRIM(f.id_funcionario), '')
        WHERE NULLIF(TRIM(f.id_funcionario), '') IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM sislami_migracao.mapa_servidor ms
              WHERE ms.id_sislami_funcionario = NULLIF(TRIM(f.id_funcionario), '')::bigint
          )
        ORDER BY NULLIF(TRIM(f.id_funcionario), '')::bigint
    LOOP
        v_cpf := CASE
            WHEN rec.cpf_limpo IS NULL OR length(rec.cpf_limpo) > 11 THEN NULL
            ELSE NULLIF(rec.cpf_limpo, '')::numeric(11,0)
        END;
        v_nis := CASE
            WHEN rec.nis_limpo IS NULL OR length(rec.nis_limpo) > 11 THEN NULL
            ELSE NULLIF(rec.nis_limpo, '')::numeric(11,0)
        END;
        v_cod_raca := CASE
            WHEN rec.cor_raca IS NULL THEN NULL
            WHEN rec.cor_raca ~ '^[1-6]$' THEN rec.cor_raca::int
            WHEN upper(rec.cor_raca) IN ('B', 'BRANCA') THEN 1
            WHEN upper(rec.cor_raca) IN ('P', 'PRETA') THEN 2
            WHEN upper(rec.cor_raca) IN ('PARDA') THEN 3
            WHEN upper(rec.cor_raca) IN ('A', 'AMARELA') THEN 4
            WHEN upper(rec.cor_raca) IN ('I', 'INDIGENA', 'INDÍGENA') THEN 5
            WHEN upper(rec.cor_raca) IN ('N', 'NAO_DECLARADA', 'NÃO_DECLARADA', 'NAO DECLARADA', 'NÃO DECLARADA') THEN 6
            ELSE NULL
        END;
        v_localizacao_diferenciada := CASE
            WHEN rec.tp_localizacao_diferenciada ~ '^[0-9]+$' THEN rec.tp_localizacao_diferenciada::int
            ELSE NULL
        END;
        v_nacionalidade := CASE
            WHEN rec.nacionalidade_bruta IS NULL THEN NULL
            WHEN rec.nacionalidade_bruta ~ '^[0-9]+$' AND length(rec.nacionalidade_bruta) <= 1 THEN rec.nacionalidade_bruta::numeric
            WHEN upper(rec.nacionalidade_bruta) IN ('BRASILEIRA', 'BRASILEIRO', 'BRASIL') THEN 1
            ELSE NULL
        END;
        v_pais_residencia := CASE
            WHEN rec.pais_residencia_limpo IS NULL THEN 76
            WHEN length(rec.pais_residencia_limpo) <= 9 THEN rec.pais_residencia_limpo::int
            ELSE 76
        END;

        v_idpes := NULL;
        IF v_cpf IS NOT NULL THEN
            SELECT f.idpes INTO v_idpes
            FROM cadastro.fisica f
            WHERE f.cpf = v_cpf
            ORDER BY f.idpes
            LIMIT 1;
        END IF;

        IF v_idpes IS NULL THEN
            INSERT INTO cadastro.pessoa (
                nome, data_cad, tipo, email, situacao, origem_gravacao, operacao
            ) VALUES (
                LEFT(rec.nome, 150), now(), 'F', LEFT(rec.email, 100), 'A', 'M', 'I'
            )
            RETURNING idpes INTO v_idpes;

            INSERT INTO cadastro.fisica (
                idpes, data_nasc, sexo, ideciv, nome_mae, nome_pai, origem_gravacao, data_cad, operacao, cpf,
                nis_pis_pasep, localizacao_diferenciada, nacionalidade, pais_residencia
            ) VALUES (
                v_idpes, rec.data_nascimento, rec.sexo, 7, LEFT(rec.nome_mae, 150), LEFT(rec.nome_pai, 150), 'M', now(), 'I', v_cpf,
                v_nis, v_localizacao_diferenciada, v_nacionalidade, v_pais_residencia
            );
        ELSE
            UPDATE cadastro.pessoa
               SET nome = COALESCE(NULLIF(LEFT(rec.nome, 150), ''), nome),
                   email = COALESCE(NULLIF(LEFT(rec.email, 100), ''), email)
             WHERE idpes = v_idpes;

            UPDATE cadastro.fisica
               SET data_nasc = COALESCE(rec.data_nascimento, data_nasc),
                   sexo = COALESCE(rec.sexo, sexo),
                   ideciv = 7,
                   nome_mae = COALESCE(NULLIF(LEFT(rec.nome_mae, 150), ''), nome_mae),
                   nome_pai = COALESCE(NULLIF(LEFT(rec.nome_pai, 150), ''), nome_pai),
                   nis_pis_pasep = COALESCE(v_nis, nis_pis_pasep),
                   localizacao_diferenciada = COALESCE(v_localizacao_diferenciada, localizacao_diferenciada),
                   nacionalidade = COALESCE(v_nacionalidade, nacionalidade),
                   pais_residencia = COALESCE(v_pais_residencia, pais_residencia)
             WHERE idpes = v_idpes;
        END IF;

        IF v_cod_raca IS NOT NULL THEN
            INSERT INTO cadastro.fisica_raca (ref_idpes, ref_cod_raca)
            VALUES (v_idpes::int, v_cod_raca)
            ON CONFLICT (ref_idpes) DO UPDATE
               SET ref_cod_raca = EXCLUDED.ref_cod_raca;
        END IF;

        IF rec.logradouro IS NOT NULL OR rec.numero IS NOT NULL OR rec.bairro IS NOT NULL OR rec.cep IS NOT NULL THEN
            SELECT php.place_id
              INTO v_place_id
              FROM person_has_place php
             WHERE php.person_id = v_idpes::integer
               AND php.type = 1
             ORDER BY php.id DESC
             LIMIT 1;

            IF v_place_id IS NULL THEN
                INSERT INTO places (
                    address, number, complement, neighborhood, postal_code, created_at, updated_at
                ) VALUES (
                    LEFT(rec.logradouro, 255),
                    LEFT(rec.numero, 20),
                    LEFT(rec.complemento, 150),
                    LEFT(rec.bairro, 150),
                    LEFT(regexp_replace(COALESCE(rec.cep, ''), '[^0-9]', '', 'g'), 10),
                    now(),
                    now()
                )
                RETURNING id INTO v_place_id;

                INSERT INTO person_has_place (
                    person_id, place_id, type, created_at, updated_at
                ) VALUES (
                    v_idpes::integer, v_place_id, 1, now(), now()
                )
                ON CONFLICT (person_id, type) DO UPDATE
                   SET place_id = EXCLUDED.place_id,
                       updated_at = now();
            ELSE
                UPDATE places
                   SET address = COALESCE(NULLIF(LEFT(rec.logradouro, 255), ''), address),
                       number = COALESCE(NULLIF(LEFT(rec.numero, 20), ''), number),
                       complement = COALESCE(NULLIF(LEFT(rec.complemento, 150), ''), complement),
                       neighborhood = COALESCE(NULLIF(LEFT(rec.bairro, 150), ''), neighborhood),
                       postal_code = COALESCE(NULLIF(LEFT(regexp_replace(COALESCE(rec.cep, ''), '[^0-9]', '', 'g'), 10), ''), postal_code),
                       updated_at = now()
                 WHERE id = v_place_id;
            END IF;
        END IF;

        INSERT INTO sislami_migracao.mapa_servidor (id_sislami_funcionario, idpes)
        VALUES (rec.id_funcionario, v_idpes);
    END LOOP;

    -- Codigo INEP do docente (TB_FUNCIONARIO.CD_INEP -> modules.educacenso_cod_docente)
    INSERT INTO modules.educacenso_cod_docente (
        cod_servidor,
        cod_docente_inep,
        nome_inep,
        fonte,
        created_at,
        updated_at
    )
    SELECT
        src.cod_servidor,
        src.cod_docente_inep,
        src.nome_inep,
        'SISLAME' AS fonte,
        now() AS created_at,
        now() AS updated_at
    FROM (
        SELECT DISTINCT ON (ms.idpes::integer, inep.cod_docente_inep)
            ms.idpes::integer AS cod_servidor,
            inep.cod_docente_inep,
            LEFT(COALESCE(NULLIF(TRIM(f.nm_funcionario), ''), 'DOCENTE SISLAME'), 255) AS nome_inep
        FROM sislami_raw.tb_funcionario f
        JOIN sislami_migracao.mapa_servidor ms
          ON ms.id_sislami_funcionario = NULLIF(TRIM(f.id_funcionario), '')::bigint
        CROSS JOIN LATERAL (
            SELECT NULLIF(regexp_replace(COALESCE(TRIM(f.cd_inep), ''), '[^0-9]', '', 'g'), '')::bigint AS cod_docente_inep
        ) inep
        WHERE inep.cod_docente_inep IS NOT NULL
        ORDER BY
            ms.idpes::integer,
            inep.cod_docente_inep,
            (NULLIF(TRIM(f.nm_funcionario), '') IS NOT NULL) DESC,
            NULLIF(TRIM(f.id_funcionario), '')::bigint
    ) src
    ON CONFLICT (cod_servidor, cod_docente_inep) DO UPDATE
       SET nome_inep = EXCLUDED.nome_inep,
           fonte = EXCLUDED.fonte,
           updated_at = now();

    -- Servidor: vinculo por instituicao
    INSERT INTO pmieducar.servidor (
        cod_servidor, ref_cod_instituicao, carga_horaria, data_cadastro, ativo
    )
    SELECT DISTINCT
        ms.idpes::integer AS cod_servidor,
        mi.cod_instituicao AS ref_cod_instituicao,
        40 AS carga_horaria,
        now() AS data_cadastro,
        1 AS ativo
    FROM sislami_raw.tb_funcionario_instituicao fi
    JOIN sislami_migracao.mapa_servidor ms
      ON ms.id_sislami_funcionario = NULLIF(TRIM(fi.id_funcionario), '')::bigint
    JOIN sislami_migracao.mapa_instituicao mi
      ON mi.id_sislami_instituicao = NULLIF(TRIM(fi.id_instituicao), '')::int
    WHERE NULLIF(TRIM(fi.id_funcionario), '') IS NOT NULL
      AND NULLIF(TRIM(fi.id_instituicao), '') IS NOT NULL
      AND NOT EXISTS (
          SELECT 1
          FROM pmieducar.servidor ps
          WHERE ps.cod_servidor = ms.idpes::integer
            AND ps.ref_cod_instituicao = mi.cod_instituicao
      );

    -- Funcao (TB_FUNCIONARIO_FUNCAO -> pmieducar.funcao)
    FOR rec IN
        SELECT
            NULLIF(TRIM(ff.id_funcionario_funcao), '')::bigint AS id_funcao,
            COALESCE(NULLIF(TRIM(ff.dc_funcionario_funcao), ''), 'FUNCAO SISLAME ' || NULLIF(TRIM(ff.id_funcionario_funcao), '')) AS nome_funcao,
            NULLIF(TRIM(ff.tp_tipo_funcao), '') AS tipo_funcao
        FROM sislami_raw.tb_funcionario_funcao ff
        WHERE NULLIF(TRIM(ff.id_funcionario_funcao), '') IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM sislami_migracao.mapa_funcao mf
              WHERE mf.id_sislami_funcao = NULLIF(TRIM(ff.id_funcionario_funcao), '')::bigint
          )
        ORDER BY NULLIF(TRIM(ff.id_funcionario_funcao), '')::bigint
    LOOP
        v_cod_turma := NULL;
        SELECT f.cod_funcao
          INTO v_cod_turma
          FROM pmieducar.funcao f
         WHERE f.ref_cod_instituicao = v_instituicao_base
           AND lower(trim(f.nm_funcao)) = lower(trim(LEFT(rec.nome_funcao, 255)))
         ORDER BY f.cod_funcao
         LIMIT 1;

        IF v_cod_turma IS NULL THEN
            INSERT INTO pmieducar.funcao (
                ref_usuario_cad,
                nm_funcao,
                abreviatura,
                professor,
                data_cadastro,
                ativo,
                ref_cod_instituicao
            ) VALUES (
                v_user_id_cad,
                LEFT(rec.nome_funcao, 255),
                LEFT(
                    COALESCE(
                        NULLIF(regexp_replace(upper(rec.nome_funcao), '[^A-Z0-9 ]', '', 'g'), ''),
                        'FUNCAO ' || rec.id_funcao::text
                    ),
                    30
                ),
                CASE WHEN rec.tipo_funcao IN ('D', 'P') THEN 1 ELSE 0 END,
                now(),
                1,
                v_instituicao_base
            )
            RETURNING cod_funcao INTO v_cod_turma;
        END IF;

        INSERT INTO sislami_migracao.mapa_funcao (id_sislami_funcao, cod_funcao)
        VALUES (rec.id_funcao, v_cod_turma)
        ON CONFLICT (id_sislami_funcao) DO UPDATE
           SET cod_funcao = EXCLUDED.cod_funcao;
    END LOOP;

    -- Vinculo servidor x funcao
    INSERT INTO pmieducar.servidor_funcao (
        ref_ref_cod_instituicao,
        ref_cod_servidor,
        ref_cod_funcao,
        matricula
    )
    SELECT DISTINCT
        mi.cod_instituicao AS ref_ref_cod_instituicao,
        ms.idpes::integer AS ref_cod_servidor,
        mf.cod_funcao AS ref_cod_funcao,
        NULL::varchar AS matricula
    FROM sislami_raw.tb_funcionario f
    JOIN sislami_migracao.mapa_servidor ms
      ON ms.id_sislami_funcionario = NULLIF(TRIM(f.id_funcionario), '')::bigint
    JOIN sislami_raw.tb_funcionario_instituicao fi
      ON NULLIF(TRIM(fi.id_funcionario), '')::bigint = NULLIF(TRIM(f.id_funcionario), '')::bigint
    JOIN sislami_migracao.mapa_instituicao mi
      ON mi.id_sislami_instituicao = NULLIF(TRIM(fi.id_instituicao), '')::int
    JOIN sislami_migracao.mapa_funcao mf
      ON mf.id_sislami_funcao = NULLIF(TRIM(f.tp_funcao_funcionario), '')::bigint
    WHERE NULLIF(TRIM(f.id_funcionario), '') IS NOT NULL
      AND NULLIF(TRIM(f.tp_funcao_funcionario), '') ~ '^[0-9]+$'
      AND NOT EXISTS (
          SELECT 1
          FROM pmieducar.servidor_funcao sf
          WHERE sf.ref_ref_cod_instituicao = mi.cod_instituicao
            AND sf.ref_cod_servidor = ms.idpes::integer
            AND sf.ref_cod_funcao = mf.cod_funcao
      );

    -- Matricula basica por vinculo aluno x instituicao
    -- Fallback somente para casos sem registro em TB_ALUNO_ETAPA
    FOR rec IN
        SELECT
            ai.id_sislami_aluno,
            ai.id_sislami_instituicao,
            ma.cod_aluno,
            me.cod_escola,
            COALESCE(EXTRACT(YEAR FROM ai.data_inicio)::int, v_ano_letivo) AS ano_letivo
        FROM sislami_migracao.aluno_instituicao ai
        JOIN sislami_migracao.mapa_aluno ma
          ON ma.id_sislami_aluno = ai.id_sislami_aluno
        JOIN sislami_migracao.mapa_escola me
          ON me.id_sislami_instituicao = ai.id_sislami_instituicao
        WHERE NOT EXISTS (
            SELECT 1
            FROM sislami_migracao.mapa_matricula mm
            WHERE mm.id_sislami_aluno = ai.id_sislami_aluno
              AND mm.id_sislami_instituicao = ai.id_sislami_instituicao
        )
          AND NOT EXISTS (
              SELECT 1
              FROM sislami_raw.tb_aluno_etapa ae
              WHERE NULLIF(TRIM(ae.id_aluno), '')::bigint = ai.id_sislami_aluno
                AND NULLIF(TRIM(ae.id_instituicao), '')::int = ai.id_sislami_instituicao
                AND NULLIF(TRIM(ae.id_aluno_etapa), '') IS NOT NULL
          )
        ORDER BY ai.id_sislami_aluno, ai.id_sislami_instituicao
    LOOP
        INSERT INTO pmieducar.matricula (
            ref_ref_cod_escola,
            ref_usuario_cad,
            ref_cod_aluno,
            aprovado,
            data_cadastro,
            ativo,
            ano,
            ultima_matricula
        ) VALUES (
            rec.cod_escola,
            v_user_id_cad,
            rec.cod_aluno,
            0,
            now(),
            1,
            rec.ano_letivo,
            1
        )
        RETURNING cod_matricula INTO v_cod_matricula;

        INSERT INTO sislami_migracao.mapa_matricula (
            id_sislami_aluno,
            id_sislami_instituicao,
            cod_matricula
        ) VALUES (
            rec.id_sislami_aluno,
            rec.id_sislami_instituicao,
            v_cod_matricula
        );
    END LOOP;

    -- Curso (1 por instituicao SISLAMI)
    FOR rec IN
        WITH primeira_etapa AS (
            SELECT
                NULLIF(TRIM(t.id_instituicao), '')::int AS id_instituicao,
                MIN(NULLIF(TRIM(te.id_etapa), '')::int) AS id_etapa
            FROM sislami_raw.tb_turma t
            JOIN sislami_raw.tb_turma_etapa te
              ON NULLIF(TRIM(te.id_turma), '') = NULLIF(TRIM(t.id_turma), '')
            WHERE NULLIF(TRIM(t.id_instituicao), '') IS NOT NULL
              AND NULLIF(TRIM(te.id_etapa), '') IS NOT NULL
            GROUP BY NULLIF(TRIM(t.id_instituicao), '')::int
        )
        SELECT
            mi.id_sislami_instituicao,
            mi.cod_instituicao,
            COALESCE(
                NULLIF(TRIM(tte.dc_tipo_ensino), ''),
                NULLIF(TRIM(tte.dc_tipo_ensino_reduzido), ''),
                'Importação'
            ) AS nm_base
        FROM sislami_migracao.mapa_instituicao mi
        LEFT JOIN primeira_etapa pe
               ON pe.id_instituicao = mi.id_sislami_instituicao
        LEFT JOIN sislami_raw.tb_etapa e
               ON NULLIF(TRIM(e.id_etapa), '')::int = pe.id_etapa
        LEFT JOIN sislami_raw.tb_nivel nv
               ON NULLIF(TRIM(nv.id_nivel), '')::int = NULLIF(TRIM(e.id_nivel), '')::int
        LEFT JOIN sislami_raw.tb_tipo_ensino tte
               ON NULLIF(TRIM(tte.id_tipo_ensino), '')::int = NULLIF(TRIM(nv.id_tipo_ensino), '')::int
        WHERE NOT EXISTS (
            SELECT 1 FROM sislami_migracao.mapa_curso mc
            WHERE mc.id_sislami_instituicao = mi.id_sislami_instituicao
        )
    LOOP
        SELECT c.cod_curso
          INTO v_cod_curso
          FROM pmieducar.curso c
         WHERE c.ref_cod_instituicao = rec.cod_instituicao
           AND lower(trim(c.nm_curso)) = lower(trim(LEFT(rec.nm_base, 255)))
         ORDER BY c.cod_curso
         LIMIT 1;

        IF v_cod_curso IS NULL THEN
            INSERT INTO pmieducar.curso (
                ref_usuario_cad,
                ref_cod_tipo_regime,
                ref_cod_nivel_ensino,
                ref_cod_tipo_ensino,
                nm_curso,
                sgl_curso,
                qtd_etapas,
                carga_horaria,
                data_cadastro,
                ativo,
                ref_cod_instituicao,
                padrao_ano_escolar
            ) VALUES (
                v_user_id_cad,
                v_tipo_regime,
                v_nivel_ensino,
                v_tipo_ensino,
                LEFT(rec.nm_base, 255),
                LEFT('SL' || rec.id_sislami_instituicao::text, 15),
                9,
                800,
                now(),
                1,
                rec.cod_instituicao,
                1
            )
            RETURNING cod_curso INTO v_cod_curso;
        END IF;

        INSERT INTO sislami_migracao.mapa_curso (id_sislami_instituicao, cod_curso)
        VALUES (rec.id_sislami_instituicao, v_cod_curso)
        ON CONFLICT (id_sislami_instituicao) DO UPDATE
           SET cod_curso = EXCLUDED.cod_curso;
    END LOOP;

    -- Serie (por instituicao + etapa)
    FOR rec IN
        WITH etapas_usadas AS (
            SELECT DISTINCT
                NULLIF(TRIM(t.id_instituicao), '')::int AS id_instituicao,
                NULLIF(TRIM(te.id_etapa), '')::int AS id_etapa
            FROM sislami_raw.tb_turma t
            JOIN sislami_raw.tb_turma_etapa te
              ON NULLIF(TRIM(te.id_turma), '') = NULLIF(TRIM(t.id_turma), '')
            WHERE NULLIF(TRIM(t.id_instituicao), '') IS NOT NULL
              AND NULLIF(TRIM(te.id_etapa), '') IS NOT NULL
        )
        SELECT
            eu.id_instituicao,
            eu.id_etapa,
            mc.cod_curso,
            COALESCE(NULLIF(TRIM(e.dc_etapa), ''), 'ETAPA ' || eu.id_etapa::text) AS nm_serie,
            COALESCE(NULLIF(TRIM(e.vl_carga_minima_hora), '')::double precision, 800) AS carga
        FROM etapas_usadas eu
        JOIN sislami_migracao.mapa_curso mc
          ON mc.id_sislami_instituicao = eu.id_instituicao
        LEFT JOIN sislami_raw.tb_etapa e
          ON NULLIF(TRIM(e.id_etapa), '')::int = eu.id_etapa
        WHERE NOT EXISTS (
            SELECT 1
            FROM sislami_migracao.mapa_serie ms
            WHERE ms.id_sislami_instituicao = eu.id_instituicao
              AND ms.id_sislami_etapa = eu.id_etapa
        )
        ORDER BY eu.id_instituicao, eu.id_etapa
    LOOP
        SELECT s.cod_serie
          INTO v_cod_serie
          FROM pmieducar.serie s
         WHERE s.ref_cod_curso = rec.cod_curso
           AND lower(trim(s.nm_serie)) = lower(trim(LEFT(rec.nm_serie, 255)))
         ORDER BY s.cod_serie
         LIMIT 1;

        IF v_cod_serie IS NULL THEN
            INSERT INTO pmieducar.serie (
                ref_usuario_cad,
                ref_cod_curso,
                nm_serie,
                etapa_curso,
                concluinte,
                carga_horaria,
                data_cadastro,
                ativo
            ) VALUES (
                v_user_id_cad,
                rec.cod_curso,
                LEFT(rec.nm_serie, 255),
                1,
                0,
                rec.carga,
                now(),
                1
            )
            RETURNING cod_serie INTO v_cod_serie;
        END IF;

        INSERT INTO sislami_migracao.mapa_serie (
            id_sislami_instituicao, id_sislami_etapa, cod_serie, cod_curso
        ) VALUES (
            rec.id_instituicao, rec.id_etapa, v_cod_serie, rec.cod_curso
        )
        ON CONFLICT (id_sislami_instituicao, id_sislami_etapa) DO UPDATE
           SET cod_serie = EXCLUDED.cod_serie,
               cod_curso = EXCLUDED.cod_curso;
    END LOOP;

    -- Vinculos escola_curso e escola_serie
    INSERT INTO pmieducar.escola_curso (
        ref_cod_escola, ref_cod_curso, ref_usuario_cad, data_cadastro, ativo
    )
    SELECT
        me.cod_escola,
        mc.cod_curso,
        v_user_id_cad,
        now(),
        1
    FROM sislami_migracao.mapa_escola me
    JOIN sislami_migracao.mapa_curso mc
      ON mc.id_sislami_instituicao = me.id_sislami_instituicao
    ON CONFLICT (ref_cod_escola, ref_cod_curso) DO NOTHING;

    INSERT INTO pmieducar.escola_serie (
        ref_cod_escola, ref_cod_serie, ref_usuario_cad, data_cadastro, ativo
    )
    SELECT
        me.cod_escola,
        ms.cod_serie,
        v_user_id_cad,
        now(),
        1
    FROM sislami_migracao.mapa_escola me
    JOIN sislami_migracao.mapa_serie ms
      ON ms.id_sislami_instituicao = me.id_sislami_instituicao
    ON CONFLICT (ref_cod_escola, ref_cod_serie) DO NOTHING;

    -- Turmas
    FOR rec IN
        WITH turma_etapa AS (
            SELECT
                NULLIF(TRIM(t.id_turma), '')::int AS id_turma,
                NULLIF(TRIM(t.id_instituicao), '')::int AS id_instituicao,
                COALESCE(NULLIF(TRIM(t.dc_turma), ''), 'TURMA') AS nm_turma,
                COALESCE(NULLIF(TRIM(t.qt_maxima_aluno), '')::int, 35) AS max_aluno,
                COALESCE(NULLIF(TRIM(t.fl_multiseriada), '')::int, 0) AS multiseriada,
                COALESCE(NULLIF(TRIM(t.dt_criacao), ''), NULLIF(TRIM(t.dt_alteracao), '')) AS dt_ref,
                (
                    SELECT NULLIF(TRIM(te.id_etapa), '')::int
                    FROM sislami_raw.tb_turma_etapa te
                    WHERE NULLIF(TRIM(te.id_turma), '')::int = NULLIF(TRIM(t.id_turma), '')::int
                    ORDER BY NULLIF(TRIM(te.id_etapa), '')::int
                    LIMIT 1
                ) AS id_etapa
            FROM sislami_raw.tb_turma t
            WHERE NULLIF(TRIM(t.id_turma), '') IS NOT NULL
        )
        SELECT
            te.id_turma,
            te.id_instituicao,
            te.nm_turma,
            te.max_aluno,
            te.multiseriada,
            te.dt_ref,
            ms.cod_serie,
            ms.cod_curso,
            me.cod_escola,
            mi.cod_instituicao
        FROM turma_etapa te
        JOIN sislami_migracao.mapa_serie ms
          ON ms.id_sislami_instituicao = te.id_instituicao
         AND ms.id_sislami_etapa = te.id_etapa
        JOIN sislami_migracao.mapa_escola me
          ON me.id_sislami_instituicao = te.id_instituicao
        JOIN sislami_migracao.mapa_instituicao mi
          ON mi.id_sislami_instituicao = te.id_instituicao
        WHERE NOT EXISTS (
            SELECT 1
            FROM sislami_migracao.mapa_turma mt
            WHERE mt.id_sislami_turma = te.id_turma
        )
    LOOP
        INSERT INTO pmieducar.turma (
            ref_usuario_cad,
            ref_ref_cod_serie,
            ref_ref_cod_escola,
            nm_turma,
            sgl_turma,
            max_aluno,
            multiseriada,
            data_cadastro,
            ativo,
            ref_cod_turma_tipo,
            ref_cod_instituicao,
            ref_cod_curso,
            ano
        ) VALUES (
            v_user_id_cad,
            rec.cod_serie,
            rec.cod_escola,
            LEFT(rec.nm_turma, 255),
            LEFT(rec.nm_turma, 15),
            rec.max_aluno,
            rec.multiseriada,
            now(),
            1,
            v_turma_tipo,
            rec.cod_instituicao,
            rec.cod_curso,
            COALESCE(EXTRACT(YEAR FROM sislami_migracao.parse_sislami_timestamp(rec.dt_ref))::int, v_ano_letivo)
        )
        RETURNING cod_turma INTO v_cod_turma;

        INSERT INTO sislami_migracao.mapa_turma (id_sislami_turma, cod_turma, cod_serie, cod_escola)
        VALUES (rec.id_turma, v_cod_turma, rec.cod_serie, rec.cod_escola);
    END LOOP;

    -- Normalizacao de legado: garantir referencias na instituicao 1.
    UPDATE pmieducar.escola e
       SET ref_cod_instituicao = v_instituicao_base
      FROM sislami_migracao.mapa_escola me
     WHERE e.cod_escola = me.cod_escola
       AND e.ref_cod_instituicao IS DISTINCT FROM v_instituicao_base;

    UPDATE pmieducar.curso c
       SET ref_cod_instituicao = v_instituicao_base
      FROM sislami_migracao.mapa_curso mc
     WHERE c.cod_curso = mc.cod_curso
       AND c.ref_cod_instituicao IS DISTINCT FROM v_instituicao_base;

    UPDATE pmieducar.turma t
       SET ref_cod_instituicao = v_instituicao_base
      FROM sislami_migracao.mapa_turma mt
     WHERE t.cod_turma = mt.cod_turma
       AND t.ref_cod_instituicao IS DISTINCT FROM v_instituicao_base;

    -- Matricula por aluno_etapa (mais completa para notas/parecer/historico)
    FOR rec IN
        SELECT
            NULLIF(TRIM(ae.id_aluno_etapa), '')::bigint AS id_aluno_etapa,
            NULLIF(TRIM(ae.id_aluno), '')::bigint AS id_aluno,
            NULLIF(TRIM(ae.id_instituicao), '')::int AS id_instituicao,
            aet_sel.id_turma,
            mt_sel.cod_serie,
            ma.cod_aluno,
            me.cod_escola,
            COALESCE(NULLIF(TRIM(ae.nu_ano_administrativo), '')::int, v_ano_letivo) AS ano_letivo
        FROM sislami_raw.tb_aluno_etapa ae
        JOIN sislami_migracao.mapa_aluno ma
          ON ma.id_sislami_aluno = NULLIF(TRIM(ae.id_aluno), '')::bigint
        JOIN sislami_migracao.mapa_escola me
          ON me.id_sislami_instituicao = NULLIF(TRIM(ae.id_instituicao), '')::int
        LEFT JOIN LATERAL (
            SELECT
                NULLIF(TRIM(aet.id_turma), '')::int AS id_turma
            FROM sislami_raw.tb_aluno_etapa_turma aet
            WHERE NULLIF(TRIM(aet.id_aluno_etapa), '')::bigint = NULLIF(TRIM(ae.id_aluno_etapa), '')::bigint
              AND COALESCE(NULLIF(TRIM(aet.fl_excluido), '')::int, 0) = 0
            ORDER BY
                COALESCE(NULLIF(TRIM(aet.nu_ordem), '')::int, 0) DESC,
                sislami_migracao.parse_sislami_timestamp(NULLIF(TRIM(aet.dt_enturmacao), '')) DESC NULLS LAST,
                NULLIF(TRIM(aet.id_aluno_etapa_turma), '')::bigint DESC
            LIMIT 1
        ) aet_sel ON TRUE
        LEFT JOIN sislami_migracao.mapa_turma mt_sel
          ON mt_sel.id_sislami_turma = aet_sel.id_turma
        WHERE NULLIF(TRIM(ae.id_aluno_etapa), '') IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM sislami_migracao.mapa_aluno_etapa_matricula mm
              WHERE mm.id_sislami_aluno_etapa = NULLIF(TRIM(ae.id_aluno_etapa), '')::bigint
          )
        ORDER BY NULLIF(TRIM(ae.id_aluno_etapa), '')::bigint
    LOOP
        INSERT INTO pmieducar.matricula (
            ref_ref_cod_escola,
            ref_ref_cod_serie,
            ref_usuario_cad,
            ref_cod_aluno,
            aprovado,
            data_cadastro,
            ativo,
            ano,
            ultima_matricula
        ) VALUES (
            rec.cod_escola,
            rec.cod_serie,
            v_user_id_cad,
            rec.cod_aluno,
            0,
            now(),
            1,
            rec.ano_letivo,
            1
        )
        RETURNING cod_matricula INTO v_cod_matricula;

        INSERT INTO sislami_migracao.mapa_aluno_etapa_matricula (
            id_sislami_aluno_etapa,
            id_sislami_aluno,
            id_sislami_instituicao,
            id_sislami_turma,
            cod_matricula
        ) VALUES (
            rec.id_aluno_etapa,
            rec.id_aluno,
            rec.id_instituicao,
            rec.id_turma,
            v_cod_matricula
        );
    END LOOP;

    -- Backfill de turma para aluno_etapa sem turma:
    -- quando houver exatamente uma turma valida para o mesmo aluno+instituicao.
    UPDATE sislami_migracao.mapa_aluno_etapa_matricula mm
       SET id_sislami_turma = cand.id_turma
      FROM (
          SELECT
              mm2.id_sislami_aluno_etapa,
              MIN(NULLIF(TRIM(aet.id_turma), '')::int) AS id_turma
          FROM sislami_migracao.mapa_aluno_etapa_matricula mm2
          JOIN sislami_raw.tb_aluno_etapa ae2
            ON NULLIF(TRIM(ae2.id_aluno), '')::bigint = mm2.id_sislami_aluno
           AND NULLIF(TRIM(ae2.id_instituicao), '')::int = mm2.id_sislami_instituicao
          JOIN sislami_raw.tb_aluno_etapa_turma aet
            ON NULLIF(TRIM(aet.id_aluno_etapa), '')::bigint = NULLIF(TRIM(ae2.id_aluno_etapa), '')::bigint
           AND COALESCE(NULLIF(TRIM(aet.fl_excluido), '')::int, 0) = 0
          WHERE mm2.id_sislami_turma IS NULL
            AND NULLIF(TRIM(aet.id_turma), '') IS NOT NULL
          GROUP BY mm2.id_sislami_aluno_etapa
          HAVING count(DISTINCT NULLIF(TRIM(aet.id_turma), '')::int) = 1
      ) cand
     WHERE mm.id_sislami_aluno_etapa = cand.id_sislami_aluno_etapa
       AND mm.id_sislami_turma IS NULL;

    -- Enturmacao (fonte principal: TB_ALUNO_ETAPA_TURMA)
    INSERT INTO pmieducar.matricula_turma (
        ref_cod_matricula,
        ref_cod_turma,
        sequencial,
        ref_usuario_cad,
        data_cadastro,
        ativo,
        data_enturmacao
    )
    SELECT
        mm.cod_matricula,
        mt.cod_turma,
        COALESCE(NULLIF(TRIM(aet.nu_ordem), '')::int, 1) AS sequencial,
        v_user_id_cad,
        now(),
        1,
        COALESCE(
            sislami_migracao.parse_sislami_timestamp(NULLIF(TRIM(aet.dt_enturmacao), ''))::date,
            current_date
        ) AS data_enturmacao
    FROM sislami_raw.tb_aluno_etapa_turma aet
    JOIN sislami_migracao.mapa_aluno_etapa_matricula mm
      ON mm.id_sislami_aluno_etapa = NULLIF(TRIM(aet.id_aluno_etapa), '')::bigint
    JOIN sislami_migracao.mapa_turma mt
      ON mt.id_sislami_turma = NULLIF(TRIM(aet.id_turma), '')::int
    LEFT JOIN pmieducar.matricula_turma pmt
      ON pmt.ref_cod_matricula = mm.cod_matricula
     AND pmt.ref_cod_turma = mt.cod_turma
     AND pmt.sequencial = COALESCE(NULLIF(TRIM(aet.nu_ordem), '')::int, 1)
    WHERE NULLIF(TRIM(aet.id_aluno_etapa), '') IS NOT NULL
      AND NULLIF(TRIM(aet.id_turma), '') IS NOT NULL
      AND COALESCE(NULLIF(TRIM(aet.fl_excluido), '')::int, 0) = 0
      AND pmt.id IS NULL;

    -- Enturmacao de fallback: quando nao houver registro em TB_ALUNO_ETAPA_TURMA
    INSERT INTO pmieducar.matricula_turma (
        ref_cod_matricula,
        ref_cod_turma,
        sequencial,
        ref_usuario_cad,
        data_cadastro,
        ativo,
        data_enturmacao
    )
    SELECT
        mm.cod_matricula,
        mt.cod_turma,
        1,
        v_user_id_cad,
        now(),
        1,
        current_date
    FROM sislami_migracao.mapa_aluno_etapa_matricula mm
    JOIN sislami_migracao.mapa_turma mt
      ON mt.id_sislami_turma = mm.id_sislami_turma
    LEFT JOIN pmieducar.matricula_turma pmt
      ON pmt.ref_cod_matricula = mm.cod_matricula
    WHERE mm.id_sislami_turma IS NOT NULL
      AND pmt.id IS NULL;

    -- Backfill da serie na matricula a partir da turma vinculada
    UPDATE pmieducar.matricula m
       SET ref_ref_cod_serie = t.ref_ref_cod_serie
      FROM pmieducar.matricula_turma mt
      JOIN pmieducar.turma t
        ON t.cod_turma = mt.ref_cod_turma
     WHERE mt.ref_cod_matricula = m.cod_matricula
       AND t.ref_ref_cod_serie IS NOT NULL
       AND (m.ref_ref_cod_serie IS NULL OR m.ref_ref_cod_serie <> t.ref_ref_cod_serie);

    -- Componente curricular (por instituicao + item pedagogico)
    FOR rec IN
        SELECT DISTINCT
            NULLIF(TRIM(pp.id_programa_pedagogico_item), '')::int AS id_programa_item,
            NULLIF(TRIM(t.id_instituicao), '')::int AS id_instituicao,
            COALESCE(NULLIF(TRIM(ppi.dc_programa_pedagogico_item), ''), 'COMPONENTE ' || TRIM(pp.id_programa_pedagogico_item)) AS nome
        FROM sislami_raw.tb_programacao_pedagogica pp
        JOIN sislami_raw.tb_turma t
          ON NULLIF(TRIM(t.id_turma), '') = NULLIF(TRIM(pp.id_turma), '')
        JOIN sislami_raw.tb_programa_pedagogico_item ppi
          ON NULLIF(TRIM(ppi.id_programa_pedagogico_item), '') = NULLIF(TRIM(pp.id_programa_pedagogico_item), '')
        WHERE NULLIF(TRIM(pp.id_programa_pedagogico_item), '') IS NOT NULL
          AND NULLIF(TRIM(t.id_instituicao), '') IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM sislami_migracao.mapa_componente_curricular mc
              WHERE mc.id_sislami_programa_item = NULLIF(TRIM(pp.id_programa_pedagogico_item), '')::int
                AND mc.id_sislami_instituicao = NULLIF(TRIM(t.id_instituicao), '')::int
          )
    LOOP
        SELECT cc.id
          INTO v_cod_turma -- reutilizacao da variavel para id de componente
          FROM modules.componente_curricular cc
         WHERE cc.instituicao_id = rec.id_instituicao
           AND lower(regexp_replace(trim(cc.nome), '\s+', ' ', 'g')) =
               lower(regexp_replace(trim(LEFT(rec.nome, 500)), '\s+', ' ', 'g'))
         ORDER BY cc.id
         LIMIT 1;

        IF v_cod_turma IS NULL THEN
            INSERT INTO modules.componente_curricular (
                instituicao_id, area_conhecimento_id, nome, abreviatura, tipo_base, codigo_educacenso
            ) VALUES (
                rec.id_instituicao,
                v_area_conhecimento,
                LEFT(rec.nome, 500),
                LEFT(rec.nome, 25),
                1,
                NULL
            )
            RETURNING id INTO v_cod_turma;
        END IF;

        INSERT INTO sislami_migracao.mapa_componente_curricular (
            id_sislami_programa_item, id_sislami_instituicao, componente_curricular_id
        ) VALUES (
            rec.id_programa_item, rec.id_instituicao, v_cod_turma
        )
        ON CONFLICT (id_sislami_programa_item, id_sislami_instituicao) DO UPDATE
           SET componente_curricular_id = EXCLUDED.componente_curricular_id;
    END LOOP;

    -- Normalizacao pos-carga: consolidar duplicidades por nome+instituicao no mapa
    -- (evita recriacao em execucoes futuras quando houver variacao de espacos/caixa)
    UPDATE sislami_migracao.mapa_componente_curricular mc
       SET componente_curricular_id = base.id_keep
      FROM modules.componente_curricular cdup
      JOIN (
          SELECT
              c.instituicao_id,
              lower(regexp_replace(trim(c.nome), '\s+', ' ', 'g')) AS nome_norm,
              MIN(c.id) AS id_keep
          FROM modules.componente_curricular c
          GROUP BY
              c.instituicao_id,
              lower(regexp_replace(trim(c.nome), '\s+', ' ', 'g'))
      ) base
        ON cdup.instituicao_id = base.instituicao_id
       AND lower(regexp_replace(trim(cdup.nome), '\s+', ' ', 'g')) = base.nome_norm
     WHERE cdup.id = mc.componente_curricular_id
       AND mc.componente_curricular_id <> base.id_keep;

    -- Relaciona componente x turma
    INSERT INTO modules.componente_curricular_turma (
        componente_curricular_id, ano_escolar_id, escola_id, turma_id, carga_horaria
    )
    SELECT DISTINCT
        mc.componente_curricular_id,
        COALESCE(ms.cod_serie, mt.cod_serie),
        mt.cod_escola,
        mt.cod_turma,
        80
    FROM sislami_raw.tb_programacao_pedagogica pp
    JOIN sislami_raw.tb_turma t
      ON NULLIF(TRIM(t.id_turma), '') = NULLIF(TRIM(pp.id_turma), '')
    JOIN sislami_migracao.mapa_turma mt
      ON mt.id_sislami_turma = NULLIF(TRIM(pp.id_turma), '')::int
    LEFT JOIN sislami_raw.tb_turma_etapa te
      ON NULLIF(TRIM(te.id_turma), '') = NULLIF(TRIM(pp.id_turma), '')
    LEFT JOIN sislami_migracao.mapa_serie ms
      ON ms.id_sislami_instituicao = NULLIF(TRIM(t.id_instituicao), '')::int
     AND ms.id_sislami_etapa = NULLIF(TRIM(te.id_etapa), '')::int
    JOIN sislami_migracao.mapa_componente_curricular mc
      ON mc.id_sislami_programa_item = NULLIF(TRIM(pp.id_programa_pedagogico_item), '')::int
     AND mc.id_sislami_instituicao = NULLIF(TRIM(t.id_instituicao), '')::int
    ON CONFLICT (componente_curricular_id, turma_id) DO NOTHING;

    -- Garantia de registros base de nota/falta/parecer por matricula
    INSERT INTO modules.nota_aluno (matricula_id)
    SELECT DISTINCT cod_matricula
    FROM sislami_migracao.mapa_aluno_etapa_matricula
    ON CONFLICT (matricula_id) DO NOTHING;

    INSERT INTO modules.falta_aluno (matricula_id, tipo_falta)
    SELECT DISTINCT cod_matricula, 1
    FROM sislami_migracao.mapa_aluno_etapa_matricula
    ON CONFLICT (matricula_id) DO NOTHING;

    INSERT INTO modules.parecer_aluno (matricula_id, parecer_descritivo)
    SELECT DISTINCT cod_matricula, 1
    FROM sislami_migracao.mapa_aluno_etapa_matricula
    ON CONFLICT (matricula_id) DO NOTHING;

    -- Nota geral (resultado final)
    DELETE FROM modules.nota_geral ng
    USING modules.nota_aluno na
    JOIN sislami_migracao.mapa_aluno_etapa_matricula mm
      ON mm.cod_matricula = na.matricula_id
    WHERE ng.nota_aluno_id = na.id
      AND ng.etapa = '1';

    INSERT INTO modules.nota_geral (nota_aluno_id, nota, nota_arredondada, etapa)
    SELECT
        na.id AS nota_aluno_id,
        COALESCE(NULLIF(REPLACE(rf.vl_nota_calculo, ',', '.'), '')::numeric, NULLIF(REPLACE(rf.vl_nota, ',', '.'), '')::numeric, 0) AS nota,
        COALESCE(NULLIF(rf.vl_nota_calculo, ''), NULLIF(rf.vl_nota, ''), '0') AS nota_arredondada,
        '1' AS etapa
    FROM sislami_raw.tb_resultado_final rf
    JOIN sislami_migracao.mapa_aluno_etapa_matricula mm
      ON mm.id_sislami_aluno_etapa = NULLIF(TRIM(rf.id_aluno_etapa), '')::bigint
    JOIN modules.nota_aluno na
      ON na.matricula_id = mm.cod_matricula;

    -- Falta geral
    INSERT INTO modules.falta_geral (falta_aluno_id, quantidade, etapa)
    SELECT
        fa.id AS falta_aluno_id,
        0 AS quantidade,
        '1' AS etapa
    FROM sislami_raw.tb_aluno_etapa ae
    JOIN sislami_migracao.mapa_aluno_etapa_matricula mm
      ON mm.id_sislami_aluno_etapa = NULLIF(TRIM(ae.id_aluno_etapa), '')::bigint
    JOIN modules.falta_aluno fa
      ON fa.matricula_id = mm.cod_matricula
    ON CONFLICT (falta_aluno_id, etapa) DO UPDATE
       SET quantidade = EXCLUDED.quantidade;

    -- Parecer geral (texto descritivo mais recente por aluno/turma)
    INSERT INTO modules.parecer_geral (parecer_aluno_id, parecer, etapa)
    SELECT
        pa.id AS parecer_aluno_id,
        LEFT(MAX(aad.dc_avaliacao_descritivo), 10000) AS parecer,
        '1' AS etapa
    FROM sislami_raw.tb_aluno_avaliacao_descritivo aad
    JOIN sislami_migracao.mapa_aluno ma
      ON ma.id_sislami_aluno = NULLIF(TRIM(aad.id_aluno), '')::bigint
    JOIN sislami_migracao.mapa_turma mt
      ON mt.id_sislami_turma = NULLIF(TRIM(aad.id_turma), '')::int
    JOIN sislami_migracao.mapa_aluno_etapa_matricula mm
      ON mm.id_sislami_aluno = ma.id_sislami_aluno
     AND mm.id_sislami_turma = mt.id_sislami_turma
    JOIN modules.parecer_aluno pa
      ON pa.matricula_id = mm.cod_matricula
    GROUP BY pa.id
    ON CONFLICT (parecer_aluno_id, etapa) DO UPDATE
       SET parecer = EXCLUDED.parecer;

    -- Nota e falta por componente (a partir do resultado final por programa/item)
    INSERT INTO modules.nota_componente_curricular (
        nota_aluno_id, componente_curricular_id, nota, nota_arredondada, etapa
    )
    SELECT DISTINCT ON (src.nota_aluno_id, src.componente_curricular_id, src.etapa)
        src.nota_aluno_id,
        src.componente_curricular_id,
        src.nota,
        src.nota_arredondada,
        src.etapa
    FROM (
        SELECT
            na.id AS nota_aluno_id,
            mc.componente_curricular_id,
            COALESCE(NULLIF(REPLACE(rfp.vl_nota_processada, ',', '.'), '')::numeric, NULLIF(REPLACE(rfp.vl_nota, ',', '.'), '')::numeric, 0) AS nota,
            COALESCE(NULLIF(rfp.vl_nota_processada, ''), NULLIF(rfp.vl_nota, ''), '0') AS nota_arredondada,
            LEFT(COALESCE(NULLIF(rfp.nu_indice, ''), '1'), 2) AS etapa,
            sislami_migracao.parse_sislami_timestamp(NULLIF(TRIM(rfp.dt_criacao_registro), '')) AS dt_ref,
            CASE
                WHEN NULLIF(TRIM(rfp.id_periodo), '') ~ '^[0-9]+$'
                THEN NULLIF(TRIM(rfp.id_periodo), '')::int
                ELSE NULL
            END AS periodo_ref
        FROM sislami_raw.tb_resultado_final_programa rfp
        JOIN sislami_raw.tb_aluno_etapa ae
          ON NULLIF(TRIM(ae.id_aluno), '') = NULLIF(TRIM(rfp.id_aluno), '')
         AND NULLIF(TRIM(ae.id_instituicao), '') IS NOT NULL
        JOIN sislami_migracao.mapa_aluno_etapa_matricula mm
          ON mm.id_sislami_aluno = NULLIF(TRIM(rfp.id_aluno), '')::bigint
         AND mm.id_sislami_instituicao = NULLIF(TRIM(ae.id_instituicao), '')::int
        JOIN modules.nota_aluno na
          ON na.matricula_id = mm.cod_matricula
        JOIN sislami_migracao.mapa_componente_curricular mc
          ON mc.id_sislami_programa_item = NULLIF(TRIM(rfp.id_programa_pedagogico_item), '')::int
         AND mc.id_sislami_instituicao = mm.id_sislami_instituicao
    ) src
    ORDER BY
        src.nota_aluno_id,
        src.componente_curricular_id,
        src.etapa,
        src.dt_ref DESC NULLS LAST,
        src.periodo_ref DESC NULLS LAST
    ON CONFLICT (nota_aluno_id, componente_curricular_id, etapa) DO UPDATE
       SET nota = EXCLUDED.nota,
           nota_arredondada = EXCLUDED.nota_arredondada;

    INSERT INTO modules.falta_componente_curricular (
        falta_aluno_id, componente_curricular_id, quantidade, etapa
    )
    SELECT DISTINCT ON (src.falta_aluno_id, src.componente_curricular_id, src.etapa)
        src.falta_aluno_id,
        src.componente_curricular_id,
        src.quantidade,
        src.etapa
    FROM (
        SELECT
            fa.id AS falta_aluno_id,
            mc.componente_curricular_id,
            GREATEST(COALESCE(NULLIF(rfp.vl_frequencia, '')::int, 0), 0) AS quantidade,
            LEFT(COALESCE(NULLIF(rfp.nu_indice, ''), '1'), 2) AS etapa,
            sislami_migracao.parse_sislami_timestamp(NULLIF(TRIM(rfp.dt_criacao_registro), '')) AS dt_ref,
            CASE
                WHEN NULLIF(TRIM(rfp.id_periodo), '') ~ '^[0-9]+$'
                THEN NULLIF(TRIM(rfp.id_periodo), '')::int
                ELSE NULL
            END AS periodo_ref
        FROM sislami_raw.tb_resultado_final_programa rfp
        JOIN sislami_raw.tb_aluno_etapa ae
          ON NULLIF(TRIM(ae.id_aluno), '') = NULLIF(TRIM(rfp.id_aluno), '')
         AND NULLIF(TRIM(ae.id_instituicao), '') IS NOT NULL
        JOIN sislami_migracao.mapa_aluno_etapa_matricula mm
          ON mm.id_sislami_aluno = NULLIF(TRIM(rfp.id_aluno), '')::bigint
         AND mm.id_sislami_instituicao = NULLIF(TRIM(ae.id_instituicao), '')::int
        JOIN modules.falta_aluno fa
          ON fa.matricula_id = mm.cod_matricula
        JOIN sislami_migracao.mapa_componente_curricular mc
          ON mc.id_sislami_programa_item = NULLIF(TRIM(rfp.id_programa_pedagogico_item), '')::int
         AND mc.id_sislami_instituicao = mm.id_sislami_instituicao
    ) src
    ORDER BY
        src.falta_aluno_id,
        src.componente_curricular_id,
        src.etapa,
        src.dt_ref DESC NULLS LAST,
        src.periodo_ref DESC NULLS LAST
    ON CONFLICT (falta_aluno_id, componente_curricular_id, etapa) DO UPDATE
       SET quantidade = EXCLUDED.quantidade;

    -- Historico escolar
    FOR rec IN
        SELECT
            NULLIF(TRIM(h.id_historico), '')::bigint AS id_historico,
            ma.cod_aluno,
            COALESCE(NULLIF(TRIM(h.nu_ano_conclusao), '')::int, v_ano_letivo) AS ano,
            CASE
                WHEN NULLIF(TRIM(h.vl_carga_horaria), '') IS NULL THEN 0
                WHEN TRIM(h.vl_carga_horaria) ~ '^[0-9]{1,3}(\.[0-9]{3})+(,[0-9]+)?$'
                    THEN REPLACE(REPLACE(TRIM(h.vl_carga_horaria), '.', ''), ',', '.')::double precision
                WHEN TRIM(h.vl_carga_horaria) ~ '^[0-9]+,[0-9]+$'
                    THEN REPLACE(TRIM(h.vl_carga_horaria), ',', '.')::double precision
                WHEN TRIM(h.vl_carga_horaria) ~ '^[0-9]+:[0-9]+$'
                    THEN REPLACE(TRIM(h.vl_carga_horaria), ':', '.')::double precision
                WHEN TRIM(h.vl_carga_horaria) ~ '^[0-9]+\.[0-9]+$'
                    THEN TRIM(h.vl_carga_horaria)::double precision
                WHEN TRIM(h.vl_carga_horaria) ~ '^[0-9]+$'
                    THEN TRIM(h.vl_carga_horaria)::double precision
                ELSE 0
            END AS carga,
            COALESCE(NULLIF(TRIM(h.qt_dia_letivo), '')::int, 0) AS dias_letivos,
            CASE
                WHEN NULLIF(TRIM(h.vl_frequencia_global), '') IS NULL THEN NULL
                WHEN TRIM(h.vl_frequencia_global) ~ '^[0-9]{1,3}([,\.][0-9]{1,2})?$'
                    THEN REPLACE(TRIM(h.vl_frequencia_global), ',', '.')::numeric
                ELSE NULL
            END AS frequencia,
            CASE
                WHEN NULLIF(TRIM(h.qt_falta_total), '') ~ '^[0-9]+$' THEN NULLIF(TRIM(h.qt_falta_total), '')::int
                ELSE 0
            END AS faltas_globalizadas,
            NULLIF(
                concat_ws(E'\n',
                    NULLIF(TRIM(h.dc_observacao), ''),
                    CASE WHEN NULLIF(TRIM(h.dc_observacao_ef), '') IS NOT NULL THEN 'OBS EF: ' || TRIM(h.dc_observacao_ef) END,
                    CASE WHEN NULLIF(TRIM(h.dc_observacao_em), '') IS NOT NULL THEN 'OBS EM: ' || TRIM(h.dc_observacao_em) END,
                    CASE WHEN NULLIF(TRIM(h.tp_origem_dados), '') IS NOT NULL THEN 'origem_dados=' || TRIM(h.tp_origem_dados) END,
                    CASE WHEN NULLIF(TRIM(h.vl_resultado), '') IS NOT NULL THEN 'resultado=' || TRIM(h.vl_resultado) END,
                    CASE WHEN NULLIF(TRIM(h.id_resultado_final), '') IS NOT NULL THEN 'id_resultado_final=' || TRIM(h.id_resultado_final) END,
                    CASE WHEN NULLIF(TRIM(h.id_lei), '') IS NOT NULL THEN 'id_lei=' || TRIM(h.id_lei) END,
                    CASE WHEN NULLIF(TRIM(h.id_aluno_etapa), '') IS NOT NULL THEN 'id_aluno_etapa=' || TRIM(h.id_aluno_etapa) END,
                    CASE WHEN NULLIF(TRIM(h.dt_conclusao), '') IS NOT NULL THEN 'dt_conclusao=' || TRIM(h.dt_conclusao) END,
                    CASE WHEN NULLIF(TRIM(h.dt_registro), '') IS NOT NULL THEN 'dt_registro=' || TRIM(h.dt_registro) END,
                    CASE WHEN NULLIF(TRIM(h.nm_responsavel_registro), '') IS NOT NULL THEN 'responsavel_registro=' || TRIM(h.nm_responsavel_registro) END
                ),
                ''
            ) AS obs,
            COALESCE(NULLIF(TRIM(he.nm_escola), ''), 'ESCOLA SISLAMI') AS escola,
            COALESCE(NULLIF(TRIM(he.nm_municipio), ''), 'NAO INFORMADO') AS cidade,
            COALESCE(NULLIF(TRIM(he.sg_uf), ''), 'AL') AS uf,
            LEFT(COALESCE(NULLIF(TRIM(h.nu_registro), ''), NULLIF(TRIM(h.id_historico), ''), 'SISLAME'), 50) AS registro,
            LEFT(NULLIF(TRIM(h.nu_livro_registro), ''), 50) AS livro,
            LEFT(NULLIF(TRIM(h.nu_folha_registro), ''), 50) AS folha,
            COALESCE(NULLIF(TRIM(et.dc_etapa), ''), 'SISLAMI') AS nm_serie,
            me.cod_escola AS ref_cod_escola,
            v_instituicao_base AS ref_cod_instituicao,
            CASE
                WHEN NULLIF(TRIM(h.vl_resultado), '') IS NULL THEN 1
                WHEN upper(TRIM(h.vl_resultado)) IN ('A', 'APROVADO', 'APROVADA', 'SIM', 'S') THEN 1
                WHEN upper(TRIM(h.vl_resultado)) IN ('R', 'REPROVADO', 'REPROVADA', 'NAO', 'NÃO', 'N') THEN 2
                WHEN TRIM(h.vl_resultado) ~ '^[0-9]+$' THEN GREATEST(1, LEAST(9, TRIM(h.vl_resultado)::int))::smallint
                ELSE 1
            END AS aprovado,
            CASE
                WHEN NULLIF(TRIM(h.tp_origem_dados), '') ~ '^[0-9]+$' THEN NULLIF(TRIM(h.tp_origem_dados), '')::smallint
                ELSE NULL
            END AS origem
        FROM sislami_raw.tb_historico h
        JOIN sislami_migracao.mapa_aluno ma
          ON ma.id_sislami_aluno = NULLIF(TRIM(h.id_aluno), '')::bigint
        LEFT JOIN sislami_migracao.mapa_escola me
          ON me.id_sislami_instituicao = NULLIF(TRIM(h.id_instituicao), '')::int
        LEFT JOIN sislami_raw.tb_historico_escola he
          ON NULLIF(TRIM(he.id_escola), '')::int = NULLIF(TRIM(h.id_escola), '')::int
        LEFT JOIN sislami_raw.tb_etapa et
          ON NULLIF(TRIM(et.id_etapa), '')::int = NULLIF(TRIM(h.id_etapa), '')::int
        WHERE NULLIF(TRIM(h.id_historico), '') IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM sislami_migracao.mapa_historico_escolar mh
              WHERE mh.id_sislami_historico = NULLIF(TRIM(h.id_historico), '')::bigint
          )
        ORDER BY NULLIF(TRIM(h.id_historico), '')::bigint
    LOOP
        INSERT INTO pmieducar.historico_escolar (
            ref_cod_aluno,
            sequencial,
            ref_usuario_cad,
            ano,
            carga_horaria,
            dias_letivos,
            escola,
            escola_cidade,
            escola_uf,
            observacao,
            aprovado,
            data_cadastro,
            ativo,
            nm_serie,
            frequencia,
            faltas_globalizadas,
            registro,
            livro,
            folha,
            origem,
            ref_cod_escola,
            ref_cod_instituicao
        ) VALUES (
            rec.cod_aluno,
            COALESCE((SELECT MAX(sequencial) + 1 FROM pmieducar.historico_escolar WHERE ref_cod_aluno = rec.cod_aluno), 1),
            v_user_id_cad,
            rec.ano,
            rec.carga,
            rec.dias_letivos,
            LEFT(rec.escola, 255),
            LEFT(rec.cidade, 255),
            LEFT(rec.uf, 3),
            rec.obs,
            rec.aprovado,
            now(),
            1,
            LEFT(rec.nm_serie, 255),
            CASE WHEN rec.frequencia IS NOT NULL AND rec.frequencia <= 999.99 THEN rec.frequencia::numeric(5,2) ELSE NULL END,
            GREATEST(COALESCE(rec.faltas_globalizadas, 0), 0),
            rec.registro,
            rec.livro,
            rec.folha,
            rec.origem,
            rec.ref_cod_escola,
            rec.ref_cod_instituicao
        )
        RETURNING id, sequencial INTO v_hist_id, v_cod_turma;

        INSERT INTO sislami_migracao.mapa_historico_escolar (
            id_sislami_historico, historico_escolar_id, cod_aluno, sequencial
        ) VALUES (
            rec.id_historico, v_hist_id, rec.cod_aluno, v_cod_turma
        );
    END LOOP;

    -- Disciplinas no historico
    INSERT INTO pmieducar.historico_disciplinas (
        sequencial, ref_ref_cod_aluno, ref_sequencial, nm_disciplina, nota, faltas, ordenamento, carga_horaria_disciplina
    )
    SELECT
        COALESCE(NULLIF(TRIM(hi.vl_ordem), '')::int, 1) AS sequencial,
        mh.cod_aluno AS ref_ref_cod_aluno,
        mh.sequencial AS ref_sequencial,
        LEFT(COALESCE(NULLIF(TRIM(d.dc_disciplina), ''), 'DISCIPLINA SISLAMI'), 255) AS nm_disciplina,
        COALESCE(NULLIF(TRIM(hi.dc_resultado), ''), 'S/N') AS nota,
        COALESCE(NULLIF(TRIM(hi.qt_falta), '')::int, 0) AS faltas,
        COALESCE(NULLIF(TRIM(hi.vl_ordem), '')::int, 1) AS ordenamento,
        CASE
            WHEN NULLIF(TRIM(hi.vl_carga_horaria), '') IS NULL THEN 0
            WHEN TRIM(hi.vl_carga_horaria) ~ '^[0-9]{1,3}(\.[0-9]{3})+(,[0-9]+)?$'
                THEN ROUND(REPLACE(REPLACE(TRIM(hi.vl_carga_horaria), '.', ''), ',', '.')::numeric)::int
            WHEN TRIM(hi.vl_carga_horaria) ~ '^[0-9]+,[0-9]+$'
                THEN ROUND(REPLACE(TRIM(hi.vl_carga_horaria), ',', '.')::numeric)::int
            WHEN TRIM(hi.vl_carga_horaria) ~ '^[0-9]+:[0-9]+$'
                THEN ROUND(REPLACE(TRIM(hi.vl_carga_horaria), ':', '.')::numeric)::int
            WHEN TRIM(hi.vl_carga_horaria) ~ '^[0-9]+\.[0-9]+$'
                THEN ROUND(TRIM(hi.vl_carga_horaria)::numeric)::int
            WHEN TRIM(hi.vl_carga_horaria) ~ '^[0-9]+$'
                THEN TRIM(hi.vl_carga_horaria)::int
            ELSE 0
        END AS carga_horaria_disciplina
    FROM sislami_raw.tb_historico_item hi
    JOIN sislami_migracao.mapa_historico_escolar mh
      ON mh.id_sislami_historico = NULLIF(TRIM(hi.id_historico), '')::bigint
    LEFT JOIN sislami_raw.tb_historico_disciplina d
      ON NULLIF(TRIM(d.id_disciplina), '')::int = NULLIF(TRIM(hi.id_disciplina), '')::int
    ON CONFLICT (sequencial, ref_ref_cod_aluno, ref_sequencial) DO NOTHING;
END;
$$;

-- Tabelas de mapeamento padronizadas (map_*) para auditoria/integração externa
CREATE TABLE IF NOT EXISTS sislami_migracao.map_aluno (
    id_sislami bigint PRIMARY KEY,
    id_ieducar integer NOT NULL
);

CREATE TABLE IF NOT EXISTS sislami_migracao.map_escola (
    id_sislami bigint PRIMARY KEY,
    id_ieducar integer NOT NULL
);

CREATE TABLE IF NOT EXISTS sislami_migracao.map_instituicao (
    id_sislami bigint PRIMARY KEY,
    id_ieducar integer NOT NULL
);

CREATE TABLE IF NOT EXISTS sislami_migracao.map_curso (
    id_sislami bigint PRIMARY KEY,
    id_ieducar integer NOT NULL
);

CREATE TABLE IF NOT EXISTS sislami_migracao.map_serie (
    id_sislami bigint PRIMARY KEY,
    id_ieducar integer NOT NULL
);

CREATE TABLE IF NOT EXISTS sislami_migracao.map_turma (
    id_sislami bigint PRIMARY KEY,
    id_ieducar integer NOT NULL
);

CREATE TABLE IF NOT EXISTS sislami_migracao.map_matricula (
    id_sislami bigint PRIMARY KEY,
    id_ieducar integer NOT NULL
);

CREATE TABLE IF NOT EXISTS sislami_migracao.map_professor (
    id_sislami bigint PRIMARY KEY,
    id_ieducar integer NOT NULL
);

CREATE TABLE IF NOT EXISTS sislami_migracao.map_funcao (
    id_sislami bigint PRIMARY KEY,
    id_ieducar integer NOT NULL
);

INSERT INTO sislami_migracao.map_aluno (id_sislami, id_ieducar)
SELECT id_sislami_aluno, cod_aluno
FROM sislami_migracao.mapa_aluno
ON CONFLICT (id_sislami) DO UPDATE
SET id_ieducar = EXCLUDED.id_ieducar;

INSERT INTO sislami_migracao.map_escola (id_sislami, id_ieducar)
SELECT id_sislami_instituicao, cod_escola
FROM sislami_migracao.mapa_escola
ON CONFLICT (id_sislami) DO UPDATE
SET id_ieducar = EXCLUDED.id_ieducar;

INSERT INTO sislami_migracao.map_instituicao (id_sislami, id_ieducar)
SELECT id_sislami_instituicao, cod_instituicao
FROM sislami_migracao.mapa_instituicao
ON CONFLICT (id_sislami) DO UPDATE
SET id_ieducar = EXCLUDED.id_ieducar;

INSERT INTO sislami_migracao.map_curso (id_sislami, id_ieducar)
SELECT id_sislami_instituicao, cod_curso
FROM sislami_migracao.mapa_curso
ON CONFLICT (id_sislami) DO UPDATE
SET id_ieducar = EXCLUDED.id_ieducar;

INSERT INTO sislami_migracao.map_serie (id_sislami, id_ieducar)
SELECT ((id_sislami_instituicao::bigint * 1000000) + id_sislami_etapa::bigint) AS id_sislami,
       cod_serie
FROM sislami_migracao.mapa_serie
ON CONFLICT (id_sislami) DO UPDATE
SET id_ieducar = EXCLUDED.id_ieducar;

INSERT INTO sislami_migracao.map_turma (id_sislami, id_ieducar)
SELECT id_sislami_turma, cod_turma
FROM sislami_migracao.mapa_turma
ON CONFLICT (id_sislami) DO UPDATE
SET id_ieducar = EXCLUDED.id_ieducar;

INSERT INTO sislami_migracao.map_matricula (id_sislami, id_ieducar)
SELECT id_sislami_aluno_etapa, cod_matricula
FROM sislami_migracao.mapa_aluno_etapa_matricula
ON CONFLICT (id_sislami) DO UPDATE
SET id_ieducar = EXCLUDED.id_ieducar;

INSERT INTO sislami_migracao.map_professor (id_sislami, id_ieducar)
SELECT id_sislami_funcionario, idpes::integer
FROM sislami_migracao.mapa_servidor
ON CONFLICT (id_sislami) DO UPDATE
SET id_ieducar = EXCLUDED.id_ieducar;

INSERT INTO sislami_migracao.map_funcao (id_sislami, id_ieducar)
SELECT id_sislami_funcao, cod_funcao
FROM sislami_migracao.mapa_funcao
ON CONFLICT (id_sislami) DO UPDATE
SET id_ieducar = EXCLUDED.id_ieducar;

-- Backfill de slug para pessoas fisicas importadas (necessario para listagem atendidos_lst.php)
UPDATE cadastro.pessoa p
   SET slug = trim(
       regexp_replace(
           lower(
               regexp_replace(
                   unaccent(COALESCE(p.nome, '')),
                   '[^[:alnum:]]+',
                   ' ',
                   'g'
               )
           ),
           '\s+',
           ' ',
           'g'
       )
   )
 WHERE p.tipo = 'F'
   AND (p.slug IS NULL OR btrim(p.slug) = '')
   AND EXISTS (
       SELECT 1
       FROM cadastro.fisica f
       WHERE f.idpes = p.idpes
         AND f.ativo = 1
   );

-- Validacoes de consistencia (regra 7)
DO $$
DECLARE
    v_raw_alunos bigint;
    v_final_alunos bigint;
    v_raw_matriculas bigint;
    v_final_matriculas bigint;
    v_dup_cpf bigint;
BEGIN
    SELECT count(*) INTO v_raw_alunos
    FROM sislami_raw.tb_aluno
    WHERE NULLIF(TRIM(id_aluno), '') IS NOT NULL;

    SELECT count(*) INTO v_final_alunos
    FROM pmieducar.aluno;

    SELECT count(*) INTO v_raw_matriculas
    FROM sislami_raw.tb_aluno_etapa
    WHERE NULLIF(TRIM(id_aluno_etapa), '') IS NOT NULL;

    SELECT count(*) INTO v_final_matriculas
    FROM pmieducar.matricula;

    SELECT count(*) INTO v_dup_cpf
    FROM (
        SELECT f.cpf
        FROM cadastro.fisica f
        WHERE f.cpf IS NOT NULL
        GROUP BY f.cpf
        HAVING count(*) > 1
    ) d;

    RAISE NOTICE 'Validacao: alunos raw=% final=%', v_raw_alunos, v_final_alunos;
    RAISE NOTICE 'Validacao: matriculas raw=% final=%', v_raw_matriculas, v_final_matriculas;
    RAISE NOTICE 'Validacao: CPFs duplicados em cadastro.fisica=%', v_dup_cpf;

    IF v_dup_cpf > 0 THEN
        RAISE WARNING 'Foram encontrados % CPFs duplicados em cadastro.fisica. Revise deduplicacao antes de publicar em producao.', v_dup_cpf;
    END IF;
END;
$$;

COMMIT;
