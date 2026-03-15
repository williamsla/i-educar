<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1️⃣ Cria a coluna nm_religiao se a tabela existir
        $tableExists = DB::select("SELECT to_regclass('pmieducar.religions') AS exists")[0]->exists;

        if ($tableExists) {
            if (!Schema::hasColumn('pmieducar.religions', 'nm_religiao')) {
                DB::statement("ALTER TABLE pmieducar.religions ADD COLUMN nm_religiao VARCHAR(255)");
            }
        }

        // 2️⃣ Cria ou recria a view exporter_person apenas se tabela pessoa existir
        $pessoaExists = DB::select("SELECT to_regclass('cadastro.pessoa') AS exists")[0]->exists;

        if ($pessoaExists) {
            DB::statement("
                CREATE OR REPLACE VIEW public.exporter_person AS
                SELECT
                    p.idpes AS id,
                    p.nome AS name,
                    f.nome_social AS social_name,
                    trim(to_char(f.cpf, '000\".\"000\".\"000\"-\"00\"')) AS cpf,
                    d.rg,
                    d.data_exp_rg AS rg_issue_date,
                    d.sigla_uf_exp_rg AS rg_state_abbreviation,
                    f.data_nasc AS date_of_birth,
                    p.email,
                    f.sus,
                    f.nis_pis_pasep AS nis,
                    f.ocupacao AS occupation,
                    f.empresa AS organization,
                    f.renda_mensal AS monthly_income,
                    f.sexo AS gender,
                    r.nm_raca AS race,
                    f.idpes_mae AS mother_id,
                    f.idpes_pai AS father_id,
                    f.idpes_responsavel AS guardian_id,
                    CASE f.nacionalidade
                        WHEN 1 THEN 'Brasileira'
                        WHEN 2 THEN 'Naturalizado brasileiro'
                        WHEN 3 THEN 'Estrangeira'
                        ELSE 'Não informado'
                    END AS nationality,
                    COALESCE(ci.name || ' - ' || st.abbreviation, 'Não informado') AS birthplace,
                    re.nm_religiao AS religion,
                    CASE f.localizacao_diferenciada
                        WHEN 1 THEN 'Área de assentamento'
                        WHEN 2 THEN 'Terra indígena'
                        WHEN 3 THEN 'Comunidade quilombola'
                        WHEN 8 THEN 'Área onde se localizam povos e comunidades tradicionais'
                        WHEN 7 THEN 'Não está em área de localização diferenciada'
                        ELSE 'Não informado'
                    END AS localization_type
                FROM cadastro.pessoa p
                JOIN cadastro.fisica f ON f.idpes = p.idpes
                LEFT JOIN cadastro.fisica_raca fr ON fr.ref_idpes = f.idpes
                LEFT JOIN cadastro.raca r ON r.cod_raca = fr.ref_cod_raca
                LEFT JOIN cadastro.documento d ON d.idpes = p.idpes
                LEFT JOIN public.cities ci ON ci.id = f.idmun_nascimento
                LEFT JOIN public.states st ON ci.state_id = st.id
                LEFT JOIN pmieducar.religions re ON re.cod_religiao = f.ref_cod_religiao
                WHERE f.ativo = 1
            ");
        }
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS public.exporter_person");
        // opcional: remover coluna nm_religiao se quiser reverter completamente
        // DB::statement("ALTER TABLE pmieducar.religions DROP COLUMN IF EXISTS nm_religiao");
    }
};