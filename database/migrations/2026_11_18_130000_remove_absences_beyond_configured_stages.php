<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Falta gravada numa etapa que o calendário da matrícula não tem
 * (por exemplo etapa 3 numa turma de 2 etapas) entra no fechamento
 * como se fosse a última etapa e mantém o aluno cursando.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            DELETE FROM modules.falta_geral AS fg
            USING modules.falta_aluno AS fa,
                  pmieducar.matricula AS m,
                  pmieducar.matricula_turma AS mt,
                  pmieducar.turma AS t,
                  pmieducar.curso AS c
            WHERE fg.falta_aluno_id = fa.id
              AND fa.matricula_id = m.cod_matricula
              AND mt.ref_cod_matricula = m.cod_matricula
              AND mt.ativo = 1
              AND m.ativo = 1
              AND t.cod_turma = mt.ref_cod_turma
              AND c.cod_curso = t.ref_cod_curso
              AND fg.etapa ~ '^[0-9]+$'
              AND (
                  CASE
                      WHEN c.padrao_ano_escolar = 1 THEN (
                          SELECT COUNT(*)
                          FROM pmieducar.ano_letivo_modulo AS anm
                          WHERE anm.ref_ref_cod_escola = t.ref_ref_cod_escola
                            AND anm.ref_ano = t.ano
                      )
                      ELSE (
                          SELECT COUNT(*)
                          FROM pmieducar.turma_modulo AS tm
                          WHERE tm.ref_cod_turma = t.cod_turma
                      )
                  END
              ) > 0
              AND fg.etapa::int > (
                  CASE
                      WHEN c.padrao_ano_escolar = 1 THEN (
                          SELECT COUNT(*)
                          FROM pmieducar.ano_letivo_modulo AS anm
                          WHERE anm.ref_ref_cod_escola = t.ref_ref_cod_escola
                            AND anm.ref_ano = t.ano
                      )
                      ELSE (
                          SELECT COUNT(*)
                          FROM pmieducar.turma_modulo AS tm
                          WHERE tm.ref_cod_turma = t.cod_turma
                      )
                  END
              );

            DELETE FROM modules.falta_componente_curricular AS fcc
            USING modules.falta_aluno AS fa,
                  pmieducar.matricula AS m,
                  pmieducar.matricula_turma AS mt,
                  pmieducar.turma AS t,
                  pmieducar.curso AS c
            WHERE fcc.falta_aluno_id = fa.id
              AND fa.matricula_id = m.cod_matricula
              AND mt.ref_cod_matricula = m.cod_matricula
              AND mt.ativo = 1
              AND m.ativo = 1
              AND t.cod_turma = mt.ref_cod_turma
              AND c.cod_curso = t.ref_cod_curso
              AND fcc.etapa ~ '^[0-9]+$'
              AND (
                  CASE
                      WHEN c.padrao_ano_escolar = 1 THEN (
                          SELECT COUNT(*)
                          FROM pmieducar.ano_letivo_modulo AS anm
                          WHERE anm.ref_ref_cod_escola = t.ref_ref_cod_escola
                            AND anm.ref_ano = t.ano
                      )
                      ELSE (
                          SELECT COUNT(*)
                          FROM pmieducar.turma_modulo AS tm
                          WHERE tm.ref_cod_turma = t.cod_turma
                      )
                  END
              ) > 0
              AND fcc.etapa::int > (
                  CASE
                      WHEN c.padrao_ano_escolar = 1 THEN (
                          SELECT COUNT(*)
                          FROM pmieducar.ano_letivo_modulo AS anm
                          WHERE anm.ref_ref_cod_escola = t.ref_ref_cod_escola
                            AND anm.ref_ano = t.ano
                      )
                      ELSE (
                          SELECT COUNT(*)
                          FROM pmieducar.turma_modulo AS tm
                          WHERE tm.ref_cod_turma = t.cod_turma
                      )
                  END
              );
        SQL);
    }

    public function down(): void
    {
    }
};
