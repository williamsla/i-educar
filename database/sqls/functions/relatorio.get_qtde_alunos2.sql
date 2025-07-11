CREATE OR REPLACE FUNCTION relatorio.get_qtde_alunos2(integer, integer, integer, integer) RETURNS bigint
    LANGUAGE sql
AS $_$
SELECT COUNT(DISTINCT matricula.cod_matricula)
FROM pmieducar.matricula
INNER JOIN pmieducar.matricula_turma ON matricula_turma.ref_cod_matricula = matricula.cod_matricula
WHERE matricula.ativo = 1
  AND matricula.aprovado <> 5 -- Não contabiliza reclassificados
  AND COALESCE(matricula_turma.remanejado, false) = false
  AND (CASE WHEN 0 = $1 THEN TRUE ELSE matricula.ano = $1 END)
  AND (CASE WHEN 0 = $2 THEN TRUE ELSE matricula.ref_ref_cod_escola = $2 END)
  AND (CASE WHEN 0 = $3 THEN TRUE ELSE matricula.ref_cod_curso = $3 END)
  AND (CASE WHEN 0 = $4 THEN TRUE ELSE matricula.ref_ref_cod_serie = $4 END); $_$;
