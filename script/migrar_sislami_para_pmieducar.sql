-- Uso:
-- psql -v ON_ERROR_STOP=1 -v user_id_cad=1 -v ano_letivo=2026 -f script/migrar_sislami_para_pmieducar.sql
--
-- Premissas:
-- 1) schema sislami_migracao ja populado pelo script import_sislami.py
-- 2) carga idempotente via tabelas de mapeamento

BEGIN;

CREATE SCHEMA IF NOT EXISTS sislami_migracao;

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_instituicao (
    id_sislami_instituicao integer PRIMARY KEY,
    cod_instituicao integer NOT NULL UNIQUE,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_escola (
    id_sislami_instituicao integer PRIMARY KEY,
    cod_escola integer NOT NULL UNIQUE,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_aluno (
    id_sislami_aluno bigint PRIMARY KEY,
    idpes numeric(8,0) NOT NULL UNIQUE,
    cod_aluno integer NOT NULL UNIQUE,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_matricula (
    id_sislami_aluno bigint NOT NULL,
    id_sislami_instituicao integer NOT NULL,
    cod_matricula integer NOT NULL UNIQUE,
    migrado_em timestamp without time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (id_sislami_aluno, id_sislami_instituicao)
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_curso (
    id_sislami_instituicao integer PRIMARY KEY,
    cod_curso integer NOT NULL UNIQUE,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_serie (
    id_sislami_instituicao integer NOT NULL,
    id_sislami_etapa integer NOT NULL,
    cod_serie integer NOT NULL UNIQUE,
    cod_curso integer NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (id_sislami_instituicao, id_sislami_etapa)
);

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
    componente_curricular_id integer NOT NULL UNIQUE,
    migrado_em timestamp without time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (id_sislami_programa_item, id_sislami_instituicao)
);

CREATE TABLE IF NOT EXISTS sislami_migracao.mapa_historico_escolar (
    id_sislami_historico bigint PRIMARY KEY,
    historico_escolar_id integer NOT NULL UNIQUE,
    cod_aluno integer NOT NULL,
    sequencial integer NOT NULL,
    migrado_em timestamp without time zone NOT NULL DEFAULT now()
);

SELECT set_config('sislami.user_id_cad', :'user_id_cad', false);
SELECT set_config('sislami.ano_letivo', :'ano_letivo', false);

DO $$
DECLARE
    rec record;
    v_cod_instituicao integer;
    v_instituicao_base integer;
    v_cod_escola integer;
    v_idpes numeric(8,0);
    v_cod_aluno integer;
    v_cod_matricula integer;
    v_cep numeric(8,0);
    v_cpf numeric(11,0);
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
    v_user_id_cad integer := current_setting('sislami.user_id_cad')::int;
    v_ano_letivo integer := current_setting('sislami.ano_letivo')::int;
BEGIN
    SELECT MIN(cod_instituicao) INTO v_instituicao_base FROM pmieducar.instituicao;
    IF v_instituicao_base IS NULL THEN
        RAISE EXCEPTION 'Tabela pmieducar.instituicao sem registros. Rode as migrations/seeders do i-Educar antes da migracao SISLAMI.';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pmieducar.nivel_ensino) THEN
        INSERT INTO pmieducar.nivel_ensino (
            ref_usuario_cad, nm_nivel, descricao, data_cadastro, ativo, ref_cod_instituicao
        ) VALUES (
            v_user_id_cad, 'NIVEL SISLAMI', 'Criado automaticamente para migracao SISLAMI', now(), 1, v_instituicao_base
        )
        RETURNING cod_nivel_ensino INTO v_nivel_ensino;
    ELSE
        SELECT MIN(cod_nivel_ensino) INTO v_nivel_ensino FROM pmieducar.nivel_ensino;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pmieducar.tipo_ensino) THEN
        INSERT INTO pmieducar.tipo_ensino (
            ref_usuario_cad, nm_tipo, data_cadastro, ativo, ref_cod_instituicao, atividade_complementar
        ) VALUES (
            v_user_id_cad, 'TIPO SISLAMI', now(), 1, v_instituicao_base, false
        )
        RETURNING cod_tipo_ensino INTO v_tipo_ensino;
    ELSE
        SELECT MIN(cod_tipo_ensino) INTO v_tipo_ensino FROM pmieducar.tipo_ensino;
    END IF;

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

    -- Instituicao
    FOR rec IN
        SELECT
            i.id_sislami_instituicao,
            COALESCE(NULLIF(TRIM(ti.nm_instituicao), ''), 'INSTITUICAO SISLAMI ' || i.id_sislami_instituicao::text) AS nm_instituicao,
            COALESCE(NULLIF(TRIM(ti.ed_municipio), ''), 'NAO INFORMADO') AS cidade,
            COALESCE(NULLIF(TRIM(ti.ed_bairro), ''), 'CENTRO') AS bairro,
            COALESCE(NULLIF(TRIM(ti.ed_logradouro), ''), 'NAO INFORMADO') AS logradouro,
            NULLIF(regexp_replace(COALESCE(ti.ed_cep, ''), '[^0-9]', '', 'g'), '') AS cep_limpo,
            COALESCE(NULLIF(TRIM(ti.ed_uf), ''), 'AL') AS uf
        FROM sislami_migracao.instituicao i
        LEFT JOIN sislami_raw.tb_instituicao ti
               ON NULLIF(TRIM(ti.id_instituicao), '')::int = i.id_sislami_instituicao
        WHERE i.id_sislami_instituicao IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM sislami_migracao.mapa_instituicao mi
              WHERE mi.id_sislami_instituicao = i.id_sislami_instituicao
          )
        ORDER BY i.id_sislami_instituicao
    LOOP
        v_cep := COALESCE(NULLIF(rec.cep_limpo, '')::numeric(8,0), 0);

        INSERT INTO pmieducar.instituicao (
            ref_usuario_cad,
            ref_idtlog,
            ref_sigla_uf,
            cep,
            cidade,
            bairro,
            logradouro,
            nm_responsavel,
            data_cadastro,
            ativo,
            nm_instituicao
        ) VALUES (
            v_user_id_cad,
            'RUA',
            LEFT(rec.uf, 2),
            v_cep,
            LEFT(rec.cidade, 60),
            LEFT(rec.bairro, 40),
            LEFT(rec.logradouro, 255),
            'MIGRACAO SISLAMI',
            now(),
            1,
            LEFT(rec.nm_instituicao, 255)
        )
        RETURNING cod_instituicao INTO v_cod_instituicao;

        INSERT INTO sislami_migracao.mapa_instituicao (id_sislami_instituicao, cod_instituicao)
        VALUES (rec.id_sislami_instituicao, v_cod_instituicao);
    END LOOP;

    -- Escola (1:1 com instituicao do SISLAMI)
    FOR rec IN
        SELECT
            mi.id_sislami_instituicao,
            mi.cod_instituicao
        FROM sislami_migracao.mapa_instituicao mi
        WHERE NOT EXISTS (
            SELECT 1
            FROM sislami_migracao.mapa_escola me
            WHERE me.id_sislami_instituicao = mi.id_sislami_instituicao
        )
        ORDER BY mi.id_sislami_instituicao
    LOOP
        INSERT INTO pmieducar.escola (
            ref_usuario_cad,
            ref_cod_instituicao,
            sigla,
            data_cadastro,
            ativo
        ) VALUES (
            v_user_id_cad,
            rec.cod_instituicao,
            LEFT('SL' || rec.id_sislami_instituicao::text, 20),
            now(),
            1
        )
        RETURNING cod_escola INTO v_cod_escola;

        INSERT INTO sislami_migracao.mapa_escola (id_sislami_instituicao, cod_escola)
        VALUES (rec.id_sislami_instituicao, v_cod_escola);
    END LOOP;

    -- Aluno + pessoa + fisica
    FOR rec IN
        SELECT
            a.id_sislami_aluno,
            COALESCE(NULLIF(TRIM(a.nome), ''), 'ALUNO SISLAMI ' || a.id_sislami_aluno::text) AS nome,
            a.data_nascimento,
            CASE WHEN a.sexo IN ('M', 'F') THEN a.sexo ELSE NULL END AS sexo,
            NULLIF(regexp_replace(COALESCE(a.cpf, ''), '[^0-9]', '', 'g'), '') AS cpf_limpo,
            NULLIF(TRIM(a.nome_mae), '') AS nome_mae,
            NULLIF(TRIM(a.nome_pai), '') AS nome_pai,
            NULLIF(TRIM(a.email), '') AS email,
            NULLIF(TRIM(a.nome_social), '') AS nome_social
        FROM sislami_migracao.aluno a
        WHERE a.id_sislami_aluno IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM sislami_migracao.mapa_aluno ma
              WHERE ma.id_sislami_aluno = a.id_sislami_aluno
          )
        ORDER BY a.id_sislami_aluno
    LOOP
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

        v_cpf := CASE
            WHEN rec.cpf_limpo IS NULL THEN NULL
            ELSE NULLIF(rec.cpf_limpo, '')::numeric(11,0)
        END;

        INSERT INTO cadastro.fisica (
            idpes,
            data_nasc,
            sexo,
            nome_mae,
            nome_pai,
            origem_gravacao,
            data_cad,
            operacao,
            cpf,
            nome_social
        ) VALUES (
            v_idpes,
            rec.data_nascimento,
            rec.sexo,
            LEFT(rec.nome_mae, 150),
            LEFT(rec.nome_pai, 150),
            'M',
            now(),
            'I',
            v_cpf,
            LEFT(rec.nome_social, 150)
        );

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

        INSERT INTO sislami_migracao.mapa_aluno (id_sislami_aluno, idpes, cod_aluno)
        VALUES (rec.id_sislami_aluno, v_idpes, v_cod_aluno);
    END LOOP;

    -- Matricula basica por vinculo aluno x instituicao
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
        SELECT
            mi.id_sislami_instituicao,
            mi.cod_instituicao,
            COALESCE(NULLIF(TRIM(ti.nm_instituicao_reduzido), ''), NULLIF(TRIM(ti.nm_instituicao), ''), 'CURSO SISLAMI') AS nm_base
        FROM sislami_migracao.mapa_instituicao mi
        LEFT JOIN sislami_raw.tb_instituicao ti
               ON NULLIF(TRIM(ti.id_instituicao), '')::int = mi.id_sislami_instituicao
        WHERE NOT EXISTS (
            SELECT 1 FROM sislami_migracao.mapa_curso mc
            WHERE mc.id_sislami_instituicao = mi.id_sislami_instituicao
        )
    LOOP
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
            LEFT(rec.nm_base || ' - SISLAMI', 255),
            LEFT('SL' || rec.id_sislami_instituicao::text, 15),
            9,
            800,
            now(),
            1,
            rec.cod_instituicao,
            1
        )
        RETURNING cod_curso INTO v_cod_curso;

        INSERT INTO sislami_migracao.mapa_curso (id_sislami_instituicao, cod_curso)
        VALUES (rec.id_sislami_instituicao, v_cod_curso);
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

        INSERT INTO sislami_migracao.mapa_serie (
            id_sislami_instituicao, id_sislami_etapa, cod_serie, cod_curso
        ) VALUES (
            rec.id_instituicao, rec.id_etapa, v_cod_serie, rec.cod_curso
        );
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

    -- Matricula por aluno_etapa (mais completa para notas/parecer/historico)
    FOR rec IN
        SELECT
            NULLIF(TRIM(ae.id_aluno_etapa), '')::bigint AS id_aluno_etapa,
            NULLIF(TRIM(ae.id_aluno), '')::bigint AS id_aluno,
            NULLIF(TRIM(ae.id_instituicao), '')::int AS id_instituicao,
            aet_sel.id_turma,
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

    -- Enturmacao
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
     AND pmt.ref_cod_turma = mt.cod_turma
     AND pmt.sequencial = 1
    WHERE pmt.id IS NULL;

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
        RETURNING id INTO v_cod_turma; -- reutilizacao da variavel para id de componente

        INSERT INTO sislami_migracao.mapa_componente_curricular (
            id_sislami_programa_item, id_sislami_instituicao, componente_curricular_id
        ) VALUES (
            rec.id_programa_item, rec.id_instituicao, v_cod_turma
        );
    END LOOP;

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
            COALESCE(NULLIF(REPLACE(REPLACE(h.vl_carga_horaria, ':', ''), '.', ''), '')::double precision, 0) AS carga,
            COALESCE(NULLIF(TRIM(h.qt_dia_letivo), '')::int, 0) AS dias_letivos,
            COALESCE(NULLIF(TRIM(h.dc_observacao), ''), 'IMPORTADO SISLAMI') AS obs,
            COALESCE(NULLIF(TRIM(he.nm_escola), ''), 'ESCOLA SISLAMI') AS escola,
            COALESCE(NULLIF(TRIM(he.nm_municipio), ''), 'NAO INFORMADO') AS cidade,
            COALESCE(NULLIF(TRIM(he.sg_uf), ''), 'AL') AS uf
        FROM sislami_raw.tb_historico h
        JOIN sislami_migracao.mapa_aluno ma
          ON ma.id_sislami_aluno = NULLIF(TRIM(h.id_aluno), '')::bigint
        LEFT JOIN sislami_raw.tb_historico_escola he
          ON NULLIF(TRIM(he.id_escola), '')::int = NULLIF(TRIM(h.id_escola), '')::int
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
            nm_serie
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
            1,
            now(),
            1,
            'SISLAMI'
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
        COALESCE(NULLIF(REPLACE(hi.vl_carga_horaria, ':', ''), '')::int, 0) AS carga_horaria_disciplina
    FROM sislami_raw.tb_historico_item hi
    JOIN sislami_migracao.mapa_historico_escolar mh
      ON mh.id_sislami_historico = NULLIF(TRIM(hi.id_historico), '')::bigint
    LEFT JOIN sislami_raw.tb_historico_disciplina d
      ON NULLIF(TRIM(d.id_disciplina), '')::int = NULLIF(TRIM(hi.id_disciplina), '')::int
    ON CONFLICT (sequencial, ref_ref_cod_aluno, ref_sequencial) DO NOTHING;
END;
$$;

COMMIT;
