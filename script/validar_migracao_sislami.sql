-- Validador pos-migracao SISLAMI -> i-Educar
-- Uso:
--   psql -U <usuario> -d <banco> -f script/validar_migracao_sislami.sql
--
-- Se nenhum schema esperado aparecer na secao 1, o psql esta no banco errado
-- ou as migrations do Laravel (php artisan migrate) ainda nao foram executadas.
-- Depois disso: import_sislami.py + migrar_sislami_para_pmieducar.sql neste mesmo banco.
-- O 7o argumento de migracao_sislami_completa.sh e o DB_NAME: e ele que recebe import + migracao.
-- Sem -d no psql, o banco costuma ser igual ao usuario (ex.: ieducar), nao ieducar_sislami.

\echo '=== 0) Conexao e pre-requisitos ==='
SELECT current_database() AS banco_atual, current_user AS usuario;

SELECT EXISTS (
    SELECT 1
    FROM information_schema.schemata
    WHERE schema_name = 'sislami_migracao'
) AS sislami_migracao_existe_neste_banco;

\echo '=== 1) Schemas esperados ==='
SELECT schema_name
FROM information_schema.schemata
WHERE schema_name IN ('sislami_raw', 'sislami_migracao', 'pmieducar', 'cadastro', 'modules')
ORDER BY schema_name;

SELECT
    count(*) FILTER (WHERE schema_name IN (
        'sislami_raw', 'sislami_migracao', 'pmieducar', 'cadastro', 'modules'
    )) AS schemas_encontrados,
    CASE
        WHEN count(*) FILTER (WHERE schema_name IN (
            'sislami_raw', 'sislami_migracao', 'pmieducar', 'cadastro', 'modules'
        )) = 0
        THEN
            'Nenhum schema esperado: use -d com o banco do i-Educar e rode php artisan migrate.'
        WHEN count(*) FILTER (WHERE schema_name = 'pmieducar') = 0
        THEN
            'Schema pmieducar ausente: migrations Laravel nao aplicadas neste banco.'
        WHEN count(*) FILTER (WHERE schema_name = 'sislami_raw') = 0
        THEN
            'Schema sislami_raw ausente: rode script/import_sislami.py neste banco.'
        ELSE 'Schemas minimos presentes; detalhes nas secoes abaixo.'
    END AS diagnostico
FROM information_schema.schemata;

DROP TABLE IF EXISTS _sislami_validacao;
CREATE TEMP TABLE _sislami_validacao (
    parte text NOT NULL,
    rotulo text NOT NULL,
    total bigint
);

-- 2) Volume bruto SISLAMI
DO $do$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.schemata WHERE schema_name = 'sislami_raw'
    ) THEN
        INSERT INTO _sislami_validacao VALUES
            ('2_raw', '** pulado ** schema sislami_raw inexistente', NULL);
        RETURN;
    END IF;
    INSERT INTO _sislami_validacao
    SELECT '2_raw', 'sislami_raw.tb_aluno', count(*) FROM sislami_raw.tb_aluno
    UNION ALL
    SELECT '2_raw', 'sislami_raw.tb_instituicao', count(*) FROM sislami_raw.tb_instituicao
    UNION ALL
    SELECT '2_raw', 'sislami_raw.tb_turma', count(*) FROM sislami_raw.tb_turma
    UNION ALL
    SELECT '2_raw', 'sislami_raw.tb_aluno_etapa', count(*) FROM sislami_raw.tb_aluno_etapa;
END
$do$;

\echo '=== 2) Volume bruto SISLAMI ==='
SELECT rotulo AS tabela, total FROM _sislami_validacao WHERE parte = '2_raw' ORDER BY rotulo;

DELETE FROM _sislami_validacao WHERE parte = '2_raw';

-- 3) Mapeamentos
DO $do$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.schemata WHERE schema_name = 'sislami_migracao'
    ) THEN
        INSERT INTO _sislami_validacao VALUES
            ('3_map', '** pulado ** schema sislami_migracao inexistente', NULL);
        RETURN;
    END IF;
    INSERT INTO _sislami_validacao
    SELECT '3_map', 'mapa_instituicao', count(*) FROM sislami_migracao.mapa_instituicao
    UNION ALL
    SELECT '3_map', 'mapa_escola', count(*) FROM sislami_migracao.mapa_escola
    UNION ALL
    SELECT '3_map', 'mapa_curso', count(*) FROM sislami_migracao.mapa_curso
    UNION ALL
    SELECT '3_map', 'mapa_serie', count(*) FROM sislami_migracao.mapa_serie
    UNION ALL
    SELECT '3_map', 'mapa_turma', count(*) FROM sislami_migracao.mapa_turma
    UNION ALL
    SELECT '3_map', 'mapa_aluno', count(*) FROM sislami_migracao.mapa_aluno
    UNION ALL
    SELECT '3_map', 'mapa_matricula', count(*) FROM sislami_migracao.mapa_matricula
    UNION ALL
    SELECT '3_map', 'mapa_aluno_etapa_matricula', count(*) FROM sislami_migracao.mapa_aluno_etapa_matricula
    UNION ALL
    SELECT '3_map', 'mapa_componente_curricular', count(*) FROM sislami_migracao.mapa_componente_curricular
    UNION ALL
    SELECT '3_map', 'mapa_historico_escolar', count(*) FROM sislami_migracao.mapa_historico_escolar;
END
$do$;

\echo '=== 3) Mapeamentos da migracao ==='
SELECT rotulo AS mapa, total FROM _sislami_validacao WHERE parte = '3_map' ORDER BY rotulo;

DELETE FROM _sislami_validacao WHERE parte = '3_map';

-- 4) pmieducar principal
DO $do$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.schemata WHERE schema_name = 'pmieducar'
    ) THEN
        INSERT INTO _sislami_validacao VALUES
            ('4_pmi', '** pulado ** schema pmieducar inexistente', NULL);
        RETURN;
    END IF;
    INSERT INTO _sislami_validacao
    SELECT '4_pmi', 'pmieducar.instituicao', count(*) FROM pmieducar.instituicao
    UNION ALL
    SELECT '4_pmi', 'pmieducar.escola', count(*) FROM pmieducar.escola
    UNION ALL
    SELECT '4_pmi', 'pmieducar.curso', count(*) FROM pmieducar.curso
    UNION ALL
    SELECT '4_pmi', 'pmieducar.serie', count(*) FROM pmieducar.serie
    UNION ALL
    SELECT '4_pmi', 'pmieducar.turma', count(*) FROM pmieducar.turma
    UNION ALL
    SELECT '4_pmi', 'pmieducar.aluno', count(*) FROM pmieducar.aluno
    UNION ALL
    SELECT '4_pmi', 'pmieducar.matricula', count(*) FROM pmieducar.matricula
    UNION ALL
    SELECT '4_pmi', 'pmieducar.matricula_turma', count(*) FROM pmieducar.matricula_turma
    UNION ALL
    SELECT '4_pmi', 'pmieducar.historico_escolar', count(*) FROM pmieducar.historico_escolar
    UNION ALL
    SELECT '4_pmi', 'pmieducar.historico_disciplinas', count(*) FROM pmieducar.historico_disciplinas;
END
$do$;

\echo '=== 4) Tabelas finais principais ==='
SELECT rotulo AS tabela, total FROM _sislami_validacao WHERE parte = '4_pmi' ORDER BY rotulo;

DELETE FROM _sislami_validacao WHERE parte = '4_pmi';

-- 5) modules
DO $do$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.schemata WHERE schema_name = 'modules'
    ) THEN
        INSERT INTO _sislami_validacao VALUES
            ('5_mod', '** pulado ** schema modules inexistente', NULL);
        RETURN;
    END IF;
    INSERT INTO _sislami_validacao
    SELECT '5_mod', 'modules.componente_curricular', count(*) FROM modules.componente_curricular
    UNION ALL
    SELECT '5_mod', 'modules.componente_curricular_turma', count(*) FROM modules.componente_curricular_turma
    UNION ALL
    SELECT '5_mod', 'modules.nota_aluno', count(*) FROM modules.nota_aluno
    UNION ALL
    SELECT '5_mod', 'modules.nota_geral', count(*) FROM modules.nota_geral
    UNION ALL
    SELECT '5_mod', 'modules.nota_componente_curricular', count(*) FROM modules.nota_componente_curricular
    UNION ALL
    SELECT '5_mod', 'modules.falta_aluno', count(*) FROM modules.falta_aluno
    UNION ALL
    SELECT '5_mod', 'modules.falta_geral', count(*) FROM modules.falta_geral
    UNION ALL
    SELECT '5_mod', 'modules.falta_componente_curricular', count(*) FROM modules.falta_componente_curricular
    UNION ALL
    SELECT '5_mod', 'modules.parecer_aluno', count(*) FROM modules.parecer_aluno
    UNION ALL
    SELECT '5_mod', 'modules.parecer_geral', count(*) FROM modules.parecer_geral;
END
$do$;

\echo '=== 5) Tabelas modules (avaliacao/frequencia/parecer) ==='
SELECT rotulo AS tabela, total FROM _sislami_validacao WHERE parte = '5_mod' ORDER BY rotulo;

DELETE FROM _sislami_validacao WHERE parte = '5_mod';

-- 6) Integridade
DO $do$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.schemata WHERE schema_name = 'pmieducar'
    ) THEN
        INSERT INTO _sislami_validacao VALUES
            ('6_int', '** pulado ** schema pmieducar inexistente', NULL);
        RETURN;
    END IF;
    INSERT INTO _sislami_validacao
    SELECT '6_int', 'matricula_sem_aluno', count(*)
    FROM pmieducar.matricula m
    LEFT JOIN pmieducar.aluno a ON a.cod_aluno = m.ref_cod_aluno
    WHERE a.cod_aluno IS NULL
    UNION ALL
    SELECT '6_int', 'matricula_sem_escola', count(*)
    FROM pmieducar.matricula m
    LEFT JOIN pmieducar.escola e ON e.cod_escola = m.ref_ref_cod_escola
    WHERE e.cod_escola IS NULL
    UNION ALL
    SELECT '6_int', 'enturmacao_sem_matricula', count(*)
    FROM pmieducar.matricula_turma mt
    LEFT JOIN pmieducar.matricula m ON m.cod_matricula = mt.ref_cod_matricula
    WHERE m.cod_matricula IS NULL
    UNION ALL
    SELECT '6_int', 'enturmacao_sem_turma', count(*)
    FROM pmieducar.matricula_turma mt
    LEFT JOIN pmieducar.turma t ON t.cod_turma = mt.ref_cod_turma
    WHERE t.cod_turma IS NULL;
END
$do$;

\echo '=== 6) Integridade minima (esperado: zero) ==='
SELECT rotulo AS validacao, total FROM _sislami_validacao WHERE parte = '6_int' ORDER BY rotulo;

DELETE FROM _sislami_validacao WHERE parte = '6_int';

-- 7) Duplicidade em mapas
DO $do$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.schemata WHERE schema_name = 'sislami_migracao'
    ) THEN
        INSERT INTO _sislami_validacao VALUES
            ('7_dup', '** pulado ** schema sislami_migracao inexistente', NULL);
        RETURN;
    END IF;
    INSERT INTO _sislami_validacao
    SELECT '7_dup', 'mapa_aluno_cod_aluno_duplicado', count(*)
    FROM (
        SELECT cod_aluno
        FROM sislami_migracao.mapa_aluno
        GROUP BY cod_aluno
        HAVING count(*) > 1
    ) x
    UNION ALL
    SELECT '7_dup', 'mapa_matricula_cod_matricula_duplicado', count(*)
    FROM (
        SELECT cod_matricula
        FROM sislami_migracao.mapa_matricula
        GROUP BY cod_matricula
        HAVING count(*) > 1
    ) x
    UNION ALL
    SELECT '7_dup', 'mapa_turma_cod_turma_duplicado', count(*)
    FROM (
        SELECT cod_turma
        FROM sislami_migracao.mapa_turma
        GROUP BY cod_turma
        HAVING count(*) > 1
    ) x;
END
$do$;

\echo '=== 7) Duplicidade em mapeamentos (esperado: zero) ==='
SELECT rotulo AS validacao, total FROM _sislami_validacao WHERE parte = '7_dup' ORDER BY rotulo;

DELETE FROM _sislami_validacao WHERE parte = '7_dup';

-- 8) Amostras: preenchimento via EXECUTE (evita erro de parse se tabelas nao existirem)
\echo '=== 8) Amostras funcionais ==='

CREATE TEMP TABLE _sislami_v8_turma (
    cod_turma integer,
    nm_turma character varying(255),
    ref_ref_cod_escola integer,
    ref_ref_cod_serie integer
);

CREATE TEMP TABLE _sislami_v8_matricula (
    cod_matricula integer,
    ref_cod_aluno integer,
    nome character varying(255)
);

CREATE TEMP TABLE _sislami_v8_nota_geral (
    id integer,
    nota_aluno_id integer,
    nota text,
    etapa character varying(10)
);

CREATE TEMP TABLE _sislami_v8_hist (
    id integer,
    ref_cod_aluno integer,
    ano integer,
    escola character varying(255),
    nm_serie character varying(255)
);

DO $do$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'pmieducar' AND table_name = 'turma'
    ) THEN
        EXECUTE $q$
            INSERT INTO _sislami_v8_turma
            SELECT t.cod_turma, t.nm_turma, t.ref_ref_cod_escola, t.ref_ref_cod_serie
            FROM pmieducar.turma t
            ORDER BY t.cod_turma DESC
            LIMIT 20
        $q$;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'pmieducar' AND table_name = 'matricula'
    )
    AND EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'pmieducar' AND table_name = 'aluno'
    )
    AND EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'cadastro' AND table_name = 'pessoa'
    ) THEN
        EXECUTE $q$
            INSERT INTO _sislami_v8_matricula
            SELECT m.cod_matricula, m.ref_cod_aluno, p.nome
            FROM pmieducar.matricula m
            JOIN pmieducar.aluno a ON a.cod_aluno = m.ref_cod_aluno
            JOIN cadastro.pessoa p ON p.idpes = a.ref_idpes
            ORDER BY m.cod_matricula DESC
            LIMIT 20
        $q$;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'modules' AND table_name = 'nota_geral'
    ) THEN
        EXECUTE $q$
            INSERT INTO _sislami_v8_nota_geral
            SELECT ng.id, ng.nota_aluno_id, ng.nota::text, ng.etapa
            FROM modules.nota_geral ng
            ORDER BY ng.id DESC
            LIMIT 20
        $q$;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'pmieducar' AND table_name = 'historico_escolar'
    ) THEN
        EXECUTE $q$
            INSERT INTO _sislami_v8_hist
            SELECT he.id, he.ref_cod_aluno, he.ano, he.escola, he.nm_serie
            FROM pmieducar.historico_escolar he
            ORDER BY he.id DESC
            LIMIT 20
        $q$;
    END IF;
END
$do$;

\echo '-- Turmas recentes (vazio = tabela inexistente ou sem dados)'
SELECT * FROM _sislami_v8_turma;

\echo '-- Matriculas recentes com nome'
SELECT * FROM _sislami_v8_matricula;

\echo '-- Notas gerais recentes'
SELECT * FROM _sislami_v8_nota_geral;

\echo '-- Historicos recentes'
SELECT * FROM _sislami_v8_hist;

DROP TABLE IF EXISTS _sislami_v8_turma;
DROP TABLE IF EXISTS _sislami_v8_matricula;
DROP TABLE IF EXISTS _sislami_v8_nota_geral;
DROP TABLE IF EXISTS _sislami_v8_hist;
DROP TABLE IF EXISTS _sislami_validacao;

\echo '=== Fim da validacao ==='
