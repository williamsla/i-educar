CREATE OR REPLACE FUNCTION relatorio.get_qtde_alunos2(integer, integer, integer, integer) RETURNS bigint
    LANGUAGE sql
AS $_$
SELECT COUNT(*)
FROM pmieducar.matricula
WHERE matricula.ativo = 1
  AND (CASE WHEN 0 = $1 THEN TRUE ELSE matricula.ano = $1 END)
  AND (CASE WHEN 0 = $2 THEN TRUE ELSE matricula.ref_ref_cod_escola = $2 END)
  AND (CASE WHEN 0 = $3 THEN TRUE ELSE matricula.ref_cod_curso = $3 END)
  AND (CASE WHEN 0 = $4 THEN TRUE ELSE matricula.ref_ref_cod_serie = $4 END); $_$;
