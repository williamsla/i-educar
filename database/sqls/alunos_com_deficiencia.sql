-- Query para selecionar alunos que possuem deficiência
-- Retorna: nome do aluno, CPF, deficiência(s), telefone do aluno e telefone do responsável

SELECT
    p.nome AS aluno,
    TRIM(TO_CHAR(f.cpf, '000"."000"."000"-"00')) AS cpf,
    STRING_AGG(d.nm_deficiencia, '; ' ORDER BY d.nm_deficiencia) AS deficiencia,
    (
        SELECT STRING_AGG(CONCAT('(', fp.ddd, ') ', fp.fone), ', ' ORDER BY fp.tipo, fp.fone)
        FROM cadastro.fone_pessoa fp
        WHERE fp.idpes = a.ref_idpes
          AND NULLIF(fp.fone, 0) IS NOT NULL
          AND NULLIF(fp.ddd, 0) IS NOT NULL
    ) AS telefone_aluno,
    (
        SELECT STRING_AGG(CONCAT('(', fp.ddd, ') ', fp.fone), ', ' ORDER BY fp.tipo, fp.fone)
        FROM cadastro.fone_pessoa fp
        WHERE fp.idpes = f.idpes_responsavel
          AND NULLIF(fp.fone, 0) IS NOT NULL
          AND NULLIF(fp.ddd, 0) IS NOT NULL
    ) AS telefone_responsavel,
    COALESCE(
        (
            SELECT STRING_AGG(CONCAT('(', fp.ddd, ') ', fp.fone), ', ' ORDER BY fp.tipo, fp.fone)
            FROM cadastro.fone_pessoa fp
            WHERE fp.idpes = f.idpes_responsavel
              AND NULLIF(fp.fone, 0) IS NOT NULL
              AND NULLIF(fp.ddd, 0) IS NOT NULL
        ),
        (
            SELECT STRING_AGG(CONCAT('(', fp.ddd, ') ', fp.fone), ', ' ORDER BY fp.tipo, fp.fone)
            FROM cadastro.fone_pessoa fp
            WHERE fp.idpes = a.ref_idpes
              AND NULLIF(fp.fone, 0) IS NOT NULL
              AND NULLIF(fp.ddd, 0) IS NOT NULL
        )
    ) AS telefone_contato
FROM pmieducar.aluno a
INNER JOIN cadastro.pessoa p ON p.idpes = a.ref_idpes
INNER JOIN cadastro.fisica f ON f.idpes = p.idpes
INNER JOIN cadastro.fisica_deficiencia fd ON fd.ref_idpes = a.ref_idpes
INNER JOIN cadastro.deficiencia d ON d.cod_deficiencia = fd.ref_cod_deficiencia
WHERE a.ativo = 1
GROUP BY a.cod_aluno, p.nome, f.cpf, f.idpes_responsavel, a.ref_idpes
ORDER BY p.nome;
