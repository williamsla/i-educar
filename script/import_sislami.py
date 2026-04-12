#!/usr/bin/env python3
"""
Importador e migrador de dados SISLAMI para PostgreSQL (i-Educar).

Etapas:
1) Importa todos os CSV para schema bruto (default: sislami_raw)
2) Executa migração para schema normalizado (default: sislami_migracao)

python3 script/import_sislami.py --db-password ''
"""

from __future__ import annotations

import argparse
import csv
import io
import os
import re
import subprocess
import sys
import traceback
import unicodedata
from pathlib import Path
from typing import Sequence


class EmptyOrInvalidCsvHeader(ValueError):
    """Arquivo vazio ou sem linha de cabecalho (export opcional sem dados)."""


_SQL_ERR_PREVIEW_LEN = 6000


def _format_psql_failure(
    command: list[str],
    sql: str,
    returncode: int,
    stdout: bytes | None,
    stderr: bytes | None,
) -> str:
    sql_show = sql
    if len(sql_show) > _SQL_ERR_PREVIEW_LEN:
        half = _SQL_ERR_PREVIEW_LEN // 2
        sql_show = (
            sql_show[:half]
            + f"\n... [SQL truncado, tamanho total {len(sql)} caracteres] ...\n"
            + sql_show[-half:]
        )
    out = (stdout or b"").decode("utf-8", errors="replace").rstrip()
    err = (stderr or b"").decode("utf-8", errors="replace").rstrip()
    blocks = [
        f"psql terminou com codigo de saida {returncode}",
        f"Comando executado: {command!r}",
        f"SQL enviado ao psql:\n{sql_show}",
    ]
    blocks.append(f"stdout ({len(out)} caracteres):\n{out or '(vazio)'}")
    blocks.append(f"stderr ({len(err)} caracteres):\n{err or '(vazio)'}")
    return "\n\n".join(blocks)

# Exportacoes SISLAMI as vezes quebram campos de texto longos (aspas/; no meio do texto
# ou quebra de registro no meio da descricao). O COPY do PostgreSQL falha; o modulo csv
# do Python agrega linhas corretamente e, apos reparo, geramos CSV valido de novo.

# Data/hora tipo "Jul 18 2022 11:42AM" colada ao campo anterior sem escapar o ";"
_SISLAMI_MON = r"(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)"
SISLAMI_DATE_AFTER_QUOTE_SEMICOLON = re.compile(
    r'";('
    + _SISLAMI_MON
    + r"\s+\d{1,2}\s+\d{4}(?:\s+[\d:]+(?:AM|PM)?)?)(?:\")?\s*$",
    re.IGNORECASE,
)
# Corrupcao com NUL/controle + lixo + "\";;;\s*0\"" no ultimo campo (ex.: TB_DIVISAO)
SISLAMI_TAIL_MERGED_ZERO = re.compile(r'^([\s\S]+?)";;;\s*0"\s*$')
# Ultima coluna tipo data SISLAMI (dt_criacao etc.)
SISLAMI_TIMESTAMP_TRAILING_CELL = re.compile(
    r"^(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+"
    r"\d{1,2}\s+\d{4}\s+[\d:]+\s*(?:AM|PM)\s*$",
    re.IGNORECASE,
)


def _find_timestamp_column_index(row: list[str]) -> int | None:
    for k in range(len(row) - 1, 3, -1):
        if SISLAMI_TIMESTAMP_TRAILING_CELL.match(row[k].strip()):
            return k
    return None


def _squash_programacao_overflow_row(row: list[str], ncols: int) -> list[str]:
    """Junta colunas extras causadas por aspas nao escapadas nos textos (8 colunas esperadas)."""
    r = list(row)
    if len(r) <= ncols:
        return r
    k = _find_timestamp_column_index(r)
    if k is not None and k >= 6:
        mid = r[4 : k - 2]
        body = "\n".join(mid) if mid else ""
        return r[:4] + [body, r[k - 2], r[k - 1], r[k]]
    return r[:4] + ["\n".join(r[4:]), "", "", ""]


def repair_programacao_divisao_aula_rows(rows: list[list[str]]) -> list[list[str]]:
    """
    TB_PROGRAMACAO_DIVISAO_AULA: textos com \" e ; quebram o CSV e geram milhoes de
    fragmentos (linhas com <8 colunas) ou >8 colunas. Recompoe 8 colunas por registro.
    """
    if len(rows) < 2:
        return rows

    ncols = len(rows[0])
    out: list[list[str]] = [rows[0]]

    for r in rows[1:]:
        r = _squash_programacao_overflow_row(list(r), ncols)
        if len(r) == ncols:
            out.append(r)
            continue
        if not out:
            out.append(r)
            continue
        prev = out[-1]
        if len(prev) == ncols and len(r) < ncols:
            prev = list(prev)
            frag = ";".join(r) if r else ""
            prev[4] = prev[4] + "\n" + frag
            out[-1] = prev
        elif len(prev) != ncols:
            acc = list(prev)
            if acc and r:
                acc[-1] = acc[-1] + "\n" + r[0]
                acc.extend(r[1:])
            else:
                acc.extend(r)
            out[-1] = _squash_programacao_overflow_row(acc, ncols)
        else:
            out.append(r)

    out2: list[list[str]] = [out[0]]
    for r in out[1:]:
        r = _squash_programacao_overflow_row(list(r), ncols)
        if len(r) == ncols:
            out2.append(r)
        elif len(r) < ncols and out2:
            prev = list(out2[-1])
            if len(prev) == ncols:
                prev[4] = prev[4] + "\n" + ";".join(r)
                out2[-1] = prev
            else:
                out2.append(r)
        else:
            out2.append(_squash_programacao_overflow_row(list(r), ncols))

    return out2


def repair_malformed_sislami_rows(rows: list[list[str]]) -> list[list[str]]:
    """
    Corrige padroes comuns de linhas com numero errado de colunas apos csv.reader.
    Mantem a primeira linha (cabecalho) intacta.
    """
    if len(rows) < 2:
        return rows

    ncols = len(rows[0])
    out: list[list[str]] = [rows[0]]
    i = 1
    while i < len(rows):
        r = rows[i]
        if len(r) == ncols:
            out.append(r)
            i += 1
            continue

        if len(r) == 2 and i + 1 < len(rows):
            r2 = rows[i + 1]
            if len(r2) == ncols - 1:
                merged = [r[0], r[1] + "\n" + r2[0]] + r2[1:]
                out.append(merged)
                i += 2
                continue
            if len(r2) == ncols - 2:
                m = re.match(r"^;(\d+)\"\s*$", r2[0])
                if m:
                    id_div = m.group(1)
                    merged = [r[0], r[1], id_div] + r2[1:]
                    if len(merged) == ncols:
                        out.append(merged)
                        i += 2
                        continue

        if len(r) == ncols - 1:
            m = re.search(r'";(\d+)"\s*$', r[1])
            if m:
                id_div = m.group(1)
                desc = r[1][: m.start()]
                out.append([r[0], desc, id_div] + r[2:])
                i += 1
                continue

        out.append(r)
        i += 1

    return out


def repair_ragged_sislami_rows(rows: list[list[str]]) -> list[list[str]]:
    """
    Corrige linhas com 1 ou 3 colunas a menos por:
    - campo de data/hora colado apos \"; dentro de outro campo (comum em TB_DIVISAO);
    - ultimo campo mesclando trecho corrompido com \";;;0\" (bytes NUL no export).
    """
    if len(rows) < 2:
        return rows

    ncols = len(rows[0])
    out: list[list[str]] = [rows[0]]
    for row in rows[1:]:
        out.append(_fix_one_ragged_row(row, ncols))
    return out


def _fix_one_ragged_row(row: list[str], ncols: int) -> list[str]:
    r = list(row)
    if len(r) >= ncols:
        return r

    if len(r) == ncols - 1:
        for i, cell in enumerate(r):
            m = SISLAMI_DATE_AFTER_QUOTE_SEMICOLON.search(cell)
            if m:
                cut = m.start()
                left = cell[:cut]
                date_val = m.group(1).strip()
                return r[:i] + [left, date_val] + r[i + 1 :]

    if len(r) == ncols - 3 and r:
        m = SISLAMI_TAIL_MERGED_ZERO.match(r[-1])
        if m:
            return r[:-1] + [m.group(1), "", "", "0"]

    return r


_RESULTADO_ALOCACAO_JASPER_SCRUB = re.compile(
    r'(;;"0";;)"(?=[\s\S]*?JasperPrint)[\s\S]*?(?=;;;;"'
    + _SISLAMI_MON
    + r"\s)",
    re.MULTILINE,
)

_SISLAMI_UUID_SEMICOLON_FIELD = (
    r';"[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}";'
)
# Jasper multilinha apos campo UUID; retorno CSV continua com ;"digito"...
_SOLICITACAO_FASE_JASPER_SCRUB = re.compile(
    r"("
    + _SISLAMI_UUID_SEMICOLON_FIELD
    + r')"(?=[\s\S]*?JasperPrint)[\s\S]*?(?=;"\d{1,20}"(?:;+|;"\w))',
    re.MULTILINE | re.IGNORECASE,
)

_SISLAMI_LINE_STARTS_QUOTED_NUMERIC_ID = re.compile(r'^\s*"\d+"\s*;')

# TB_SOLICITACAO_FASE_* com DOC_* em Jasper serializado (multilinha) apos UUID.
_STEMS_SOLICITACAO_FASE_JASPER_MULTILINE = frozenset(
    {
        "tb_solicitacao_fase_inscricao",
        "tb_solicitacao_fase_transferencia",
    }
)


def _merge_solicitacao_fase_jasper_blocks(header: str, data_lines: list[str]) -> str:
    buf: list[str] = []
    blocks: list[str] = []
    starter = _SISLAMI_LINE_STARTS_QUOTED_NUMERIC_ID
    for line in data_lines:
        if not line.strip():
            continue
        if starter.match(line) and buf:
            blocks.append("\n".join(buf))
            buf = [line]
        else:
            buf.append(line)
    if buf:
        blocks.append("\n".join(buf))
    scrubbed = [_SOLICITACAO_FASE_JASPER_SCRUB.sub(r'\1""', b) for b in blocks]
    return header + "\n" + "\n".join(scrubbed)


def preprocess_csv_by_stem(text: str, stem: str = "") -> str:
    """
    NUL e ajustes por tabela antes do COPY / csv.reader.
    TB_RESULTADO_ALOCACAO: CR solto quebra o parser; DOC_RESULTADO pode trazer
    serializacao Jasper (binario) com aspas e ';' invalidos — removemos o blob.
    TB_SOLICITACAO_FASE_INSCRICAO / TRANSFERENCIA: blob Jasper multilinha apos UUID.
    """
    text = text.replace("\x00", "")
    if stem == "tb_resultado_alocacao":
        text = text.replace("\r\n", "\n").replace("\r", " ")
        text = _RESULTADO_ALOCACAO_JASPER_SCRUB.sub(r'\1""', text)
    elif stem in _STEMS_SOLICITACAO_FASE_JASPER_MULTILINE:
        text = text.replace("\r\n", "\n").replace("\r", " ")
        lines = text.split("\n")
        if len(lines) >= 2:
            text = _merge_solicitacao_fase_jasper_blocks(lines[0], lines[1:])
    return text


def csv_rows_from_text(text: str, delimiter: str = ";") -> list[list[str]]:
    reader = csv.reader(io.StringIO(text), delimiter=delimiter, quotechar='"')
    return list(reader)


def csv_text_from_rows(rows: list[list[str]], delimiter: str = ";") -> str:
    buf = io.StringIO()
    writer = csv.writer(
        buf,
        delimiter=delimiter,
        quotechar='"',
        quoting=csv.QUOTE_MINIMAL,
        doublequote=True,
        lineterminator="\n",
    )
    for row in rows:
        writer.writerow(row)
    return buf.getvalue()


def normalize_csv_for_postgres_copy(text: str, stem: str = "") -> str:
    text = preprocess_csv_by_stem(text, stem)
    rows = csv_rows_from_text(text)
    if not rows:
        return text

    ncols = len(rows[0])
    if not any(len(row) != ncols for row in rows[1:]):
        return csv_text_from_rows(rows)

    if stem == "tb_aluno_avaliacao_descritivo":
        rows = repair_malformed_sislami_rows(rows)
    elif stem == "tb_divisao":
        rows = repair_ragged_sislami_rows(rows)
    elif stem == "tb_programacao_divisao_aula":
        rows = repair_programacao_divisao_aula_rows(rows)
    else:
        if ncols == 8:
            rows_try = repair_programacao_divisao_aula_rows(list(rows))
            if not any(len(row) != ncols for row in rows_try[1:]):
                rows = rows_try
            else:
                rows = repair_malformed_sislami_rows(rows)
                rows = repair_ragged_sislami_rows(rows)
        else:
            rows = repair_malformed_sislami_rows(rows)
            rows = repair_ragged_sislami_rows(rows)

    bad = [i for i, row in enumerate(rows[1:], start=2) if len(row) != ncols]
    if bad:
        raise ValueError(
            f"Ainda ha linhas com {len(bad)} erro(s) de coluna apos reparo "
            f"(ex.: linha logica {bad[0]}, esperado {ncols} colunas)."
        )
    return csv_text_from_rows(rows)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Importa e migra dados SISLAMI para PostgreSQL."
    )
    parser.add_argument(
        "--input-dir",
        default="import/SISLAME_IGACI",
        help="Diretorio com os arquivos CSV do SISLAMI.",
    )
    parser.add_argument(
        "--schema-raw",
        default="sislami_raw",
        help="Schema de carga bruta dos CSVs.",
    )
    parser.add_argument(
        "--schema-migration",
        default="sislami_migracao",
        help="Schema de dados normalizados para migracao.",
    )
    parser.add_argument(
        "--db-name",
        default="ieducar_sislami",
        help="Nome do banco PostgreSQL.",
    )
    parser.add_argument(
        "--db-user",
        default="ieducar",
        help="Usuario PostgreSQL.",
    )
    parser.add_argument(
        "--db-host",
        default="localhost",
        help="Host do PostgreSQL quando nao usar Docker.",
    )
    parser.add_argument(
        "--db-port",
        default="2345",
        help="Porta do PostgreSQL quando nao usar Docker.",
    )
    parser.add_argument(
        "--db-password",
        default="",
        help="Senha do PostgreSQL (opcional; pode usar variavel PGPASSWORD).",
    )
    parser.add_argument(
        "--use-docker",
        action="store_true",
        help="Executa psql via docker compose exec.",
    )
    parser.add_argument(
        "--docker-service",
        default="postgres",
        help="Servico do PostgreSQL no docker-compose (quando --use-docker).",
    )
    parser.add_argument(
        "--truncate",
        action="store_true",
        help="Trunca tabelas de destino antes de copiar os CSVs.",
    )
    parser.add_argument(
        "--skip-import",
        action="store_true",
        help="Pula a etapa de importacao de CSV.",
    )
    parser.add_argument(
        "--skip-migration",
        action="store_true",
        help="Pula a etapa de migracao/normalizacao.",
    )
    parser.add_argument(
        "--python-csv-only",
        action="store_true",
        help="Nao usa COPY direto do arquivo: sempre re-serializa via Python (mais lento, mais tolerante).",
    )
    return parser.parse_args()


def sanitize_identifier(name: str) -> str:
    normalized = unicodedata.normalize("NFKD", name)
    ascii_name = normalized.encode("ascii", "ignore").decode("ascii")
    ascii_name = ascii_name.strip().lower()
    ascii_name = re.sub(r"[^a-z0-9_]+", "_", ascii_name)
    ascii_name = re.sub(r"_+", "_", ascii_name).strip("_")
    if not ascii_name:
        ascii_name = "coluna"
    if ascii_name[0].isdigit():
        ascii_name = f"c_{ascii_name}"
    return ascii_name


def dedupe_columns(columns: Sequence[str]) -> list[str]:
    seen: dict[str, int] = {}
    deduped: list[str] = []

    for col in columns:
        candidate = sanitize_identifier(col)
        if candidate not in seen:
            seen[candidate] = 0
            deduped.append(candidate)
            continue

        seen[candidate] += 1
        deduped.append(f"{candidate}_{seen[candidate]}")

    return deduped


def decode_csv_bytes(raw: bytes) -> tuple[str, str]:
    for encoding in ("utf-8-sig", "utf-8", "latin-1", "cp1252"):
        try:
            return raw.decode(encoding), encoding
        except UnicodeDecodeError:
            continue

    return raw.decode("utf-8", errors="replace"), "utf-8-replace"


def read_header(csv_path: Path) -> list[str]:
    data = csv_path.read_bytes()
    if not data:
        raise EmptyOrInvalidCsvHeader(f"0 bytes: {csv_path}")
    text, _ = decode_csv_bytes(data)
    if not text.strip():
        raise EmptyOrInvalidCsvHeader(f"sem conteudo apos decodificar: {csv_path}")
    reader = csv.reader(io.StringIO(text), delimiter=";", quotechar='"')
    try:
        header = next(reader)
    except StopIteration:
        raise EmptyOrInvalidCsvHeader(f"nenhuma linha no CSV: {csv_path}")

    if not header:
        raise ValueError(f"Cabecalho ausente: {csv_path}")

    cleaned = [h.strip().strip('"') for h in header]
    return dedupe_columns(cleaned)


def compose_psql_command(
    args: argparse.Namespace,
    sql: str,
) -> list[str]:
    if args.use_docker:
        return [
            "docker-compose",
            "exec",
            "-T",
            args.docker_service,
            "psql",
            "-v",
            "ON_ERROR_STOP=1",
            "-U",
            args.db_user,
            "-d",
            args.db_name,
            "-c",
            sql,
        ]

    return [
        "psql",
        "-v",
        "ON_ERROR_STOP=1",
        "-h",
        args.db_host,
        "-p",
        str(args.db_port),
        "-U",
        args.db_user,
        "-d",
        args.db_name,
        "-c",
        sql,
    ]


def run_psql(
    args: argparse.Namespace,
    sql: str,
    stdin_data: bytes | None = None,
) -> None:
    command = compose_psql_command(args=args, sql=sql)
    env = None
    if args.db_password:
        env = dict(os.environ, PGPASSWORD=args.db_password)

    try:
        result = subprocess.run(
            command,
            input=stdin_data,
            check=False,
            capture_output=True,
            env=env,
        )
    except FileNotFoundError as exc:
        raise RuntimeError(
            f"Executavel nao encontrado (psql ou docker-compose no PATH?): {exc!r}\n"
            f"Comando pretendido: {command!r}"
        ) from exc
    except OSError as exc:
        raise RuntimeError(
            f"Falha ao executar subprocesso: {exc!r}\nComando: {command!r}"
        ) from exc

    if result.returncode != 0:
        raise RuntimeError(
            _format_psql_failure(
                command,
                sql,
                result.returncode,
                result.stdout,
                result.stderr,
            )
        )


def quote_ident(identifier: str) -> str:
    return '"' + identifier.replace('"', '""') + '"'


def ensure_raw_schema(args: argparse.Namespace) -> None:
    run_psql(
        args=args,
        sql=f"CREATE SCHEMA IF NOT EXISTS {quote_ident(args.schema_raw)};",
    )


def import_csv_file(args: argparse.Namespace, csv_path: Path) -> None:
    table_name = sanitize_identifier(csv_path.stem)
    try:
        columns = read_header(csv_path)
    except EmptyOrInvalidCsvHeader as exc:
        print(f"[SKIP] {csv_path.name} -> {exc}")
        return

    column_defs = ", ".join(f"{quote_ident(col)} text" for col in columns)
    column_list = ", ".join(quote_ident(col) for col in columns)

    create_table_sql = (
        f"CREATE TABLE IF NOT EXISTS {quote_ident(args.schema_raw)}.{quote_ident(table_name)} "
        f"({column_defs});"
    )
    run_psql(
        args=args,
        sql=create_table_sql,
    )

    if args.truncate:
        run_psql(
            args=args,
            sql=f"TRUNCATE TABLE {quote_ident(args.schema_raw)}.{quote_ident(table_name)};",
        )

    raw = csv_path.read_bytes()
    text, encoding = decode_csv_bytes(raw)
    text = preprocess_csv_by_stem(text, table_name)
    copy_sql = (
        f"\\copy {quote_ident(args.schema_raw)}.{quote_ident(table_name)} "
        f"({column_list}) FROM STDIN WITH (FORMAT csv, HEADER true, DELIMITER ';', QUOTE '\"', ESCAPE '\"');"
    )

    payload = text.encode("utf-8")
    if args.python_csv_only:
        try:
            payload = normalize_csv_for_postgres_copy(text, stem=table_name).encode(
                "utf-8"
            )
        except ValueError as exc:
            raise RuntimeError(f"{csv_path.name}: {exc}") from exc
    try:
        run_psql(
            args=args,
            sql=copy_sql,
            stdin_data=payload,
        )
    except RuntimeError as primeira_falha:
        if args.python_csv_only:
            raise
        try:
            payload = normalize_csv_for_postgres_copy(text, stem=table_name).encode(
                "utf-8"
            )
        except ValueError as exc:
            raise RuntimeError(
                f"{csv_path.name}: COPY falhou e o reparo CSV nao resolveu.\n\n"
                f"--- Detalhe do reparo CSV ---\n{exc!r}\n\n"
                f"--- Detalhe da primeira tentativa (COPY) ---\n{primeira_falha}"
            ) from exc
        try:
            run_psql(
                args=args,
                sql=copy_sql,
                stdin_data=payload,
            )
        except RuntimeError as segunda_falha:
            raise RuntimeError(
                f"{csv_path.name}: COPY falhou; reparo CSV aplicado; segunda tentativa tambem falhou.\n\n"
                f"--- Primeira tentativa (COPY direto) ---\n{primeira_falha}\n\n"
                f"--- Segunda tentativa (apos re-serializar CSV no Python) ---\n{segunda_falha}"
            ) from segunda_falha
        print(
            f"[OK] {csv_path.name} -> {args.schema_raw}.{table_name} "
            f"(enc: {encoding}, re-serializado via Python apos falha do COPY)"
        )
    else:
        print(f"[OK] {csv_path.name} -> {args.schema_raw}.{table_name} (enc: {encoding})")


def run_import(args: argparse.Namespace) -> None:
    input_dir = Path(args.input_dir)
    if not input_dir.exists():
        raise FileNotFoundError(f"Diretorio nao encontrado: {input_dir}")

    csv_files = sorted(input_dir.glob("*.csv"))
    if not csv_files:
        raise FileNotFoundError(f"Nenhum CSV encontrado em: {input_dir}")

    ensure_raw_schema(args)

    print(f"Iniciando importacao de {len(csv_files)} arquivos CSV...")
    for csv_file in csv_files:
        import_csv_file(args, csv_file)


MIGRATION_SQL = """
CREATE SCHEMA IF NOT EXISTS {schema_migration};

CREATE OR REPLACE FUNCTION {schema_migration}.parse_sislami_timestamp(value text)
RETURNS timestamp AS $$
BEGIN
    IF value IS NULL OR btrim(value) = '' THEN
        RETURN NULL;
    END IF;

    BEGIN
        RETURN to_timestamp(value, 'Mon DD YYYY HH12:MIAM');
    EXCEPTION WHEN others THEN
        BEGIN
            RETURN to_timestamp(value, 'Mon FMDD YYYY HH12:MIAM');
        EXCEPTION WHEN others THEN
            BEGIN
                RETURN to_timestamp(value, 'YYYY-MM-DD HH24:MI:SS');
            EXCEPTION WHEN others THEN
                BEGIN
                    RETURN value::timestamp;
                EXCEPTION WHEN others THEN
                    RETURN NULL;
                END;
            END;
        END;
    END;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

DROP TABLE IF EXISTS {schema_migration}.instituicao CASCADE;
CREATE TABLE {schema_migration}.instituicao AS
SELECT DISTINCT ON (nullif(btrim(ti.id_instituicao), '')::int)
    nullif(btrim(ti.id_instituicao), '')::int AS id_sislami_instituicao,
    NULL::text AS cnpj,
    NULL::text AS email,
    NULL::text AS diretor,
    NULL::text AS cpf_diretor,
    NULL::text AS tipo_rede_ensino,
    NULL::text AS tipo_escola,
    NULL::text AS email_alternativo,
    now() AS migrado_em
FROM {schema_raw}.tb_instituicao ti
WHERE nullif(btrim(ti.id_instituicao), '') IS NOT NULL
ORDER BY nullif(btrim(ti.id_instituicao), '')::int;

DROP TABLE IF EXISTS {schema_migration}.aluno CASCADE;
CREATE TABLE {schema_migration}.aluno AS
SELECT
    nullif(btrim(a.id_aluno), '')::bigint AS id_sislami_aluno,
    nullif(btrim(a.nm_aluno), '') AS nome,
    nullif(btrim(a.nm_aluno_social), '') AS nome_social,
    nullif(btrim(a.nu_nis), '') AS nis,
    nullif(btrim(ad.nu_cpf), '') AS cpf,
    nullif(btrim(a.tp_sexo), '') AS sexo,
    nullif(btrim(a.tp_cor), '') AS cor_raca,
    nullif(btrim(a.nm_mae), '') AS nome_mae,
    nullif(btrim(a.nm_pai), '') AS nome_pai,
    nullif(btrim(a.ed_logradouro), '') AS logradouro,
    nullif(btrim(a.ed_numero), '') AS numero,
    nullif(btrim(a.ed_complemento), '') AS complemento,
    nullif(btrim(a.ed_bairro), '') AS bairro,
    nullif(btrim(a.ed_municipio), '') AS municipio,
    nullif(btrim(a.ed_uf), '') AS uf,
    nullif(btrim(a.ed_cep), '') AS cep,
    {schema_migration}.parse_sislami_timestamp(a.dt_nascimento)::date AS data_nascimento,
    nullif(btrim(ad.nu_telefone), '') AS telefone,
    nullif(btrim(ad.nu_telefone_celular), '') AS celular,
    nullif(btrim(ad.ed_email_aluno), '') AS email,
    now() AS migrado_em
FROM {schema_raw}.tb_aluno a
LEFT JOIN {schema_raw}.tb_aluno_dado ad
       ON nullif(btrim(ad.id_aluno), '') = nullif(btrim(a.id_aluno), '');

DROP TABLE IF EXISTS {schema_migration}.aluno_instituicao CASCADE;
CREATE TABLE {schema_migration}.aluno_instituicao AS
SELECT
    nullif(btrim(ai.id_aluno), '')::bigint AS id_sislami_aluno,
    nullif(btrim(ai.id_instituicao), '')::int AS id_sislami_instituicao,
    {schema_migration}.parse_sislami_timestamp(ai.dt_criacao)::date AS data_inicio,
    NULL::date AS data_fim,
    nullif(btrim(ai.tp_situacao_registro), '') AS situacao,
    now() AS migrado_em
FROM {schema_raw}.tb_aluno_instituicao ai;

CREATE INDEX IF NOT EXISTS idx_sislami_migracao_aluno_id
    ON {schema_migration}.aluno (id_sislami_aluno);

CREATE INDEX IF NOT EXISTS idx_sislami_migracao_instituicao_id
    ON {schema_migration}.instituicao (id_sislami_instituicao);

CREATE INDEX IF NOT EXISTS idx_sislami_migracao_aluno_instituicao_aluno
    ON {schema_migration}.aluno_instituicao (id_sislami_aluno);

CREATE INDEX IF NOT EXISTS idx_sislami_migracao_aluno_instituicao_instituicao
    ON {schema_migration}.aluno_instituicao (id_sislami_instituicao);
"""


def run_migration(args: argparse.Namespace) -> None:
    sql = MIGRATION_SQL.format(
        schema_raw=quote_ident(args.schema_raw),
        schema_migration=quote_ident(args.schema_migration),
    )
    run_psql(
        args=args,
        sql=sql,
    )
    print(
        f"[OK] Migracao concluida para schema {args.schema_migration} "
        "(instituicao, aluno, aluno_instituicao)."
    )


def main() -> int:
    args = parse_args()
    print(
        f"[INFO] PostgreSQL destino: {args.db_user}@{args.db_host}:{args.db_port}/{args.db_name} "
        f"(nao usa .env do Laravel; use --db-name para outro banco)"
    )
    if args.skip_migration:
        print(
            "[AVISO] --skip-migration: schema sislami_migracao (normalizacao) nao sera criado/atualizado."
        )

    if args.skip_import and args.skip_migration:
        print("Nada a executar: ambos --skip-import e --skip-migration foram informados.")
        return 1

    try:
        if not args.skip_import:
            run_import(args)

        if not args.skip_migration:
            run_migration(args)
    except Exception as exc:  # pragma: no cover
        print(
            f"\n[ERRO] Execucao interrompida: {type(exc).__name__}: {exc}",
            file=sys.stderr,
        )
        if exc.__cause__ is not None:
            print(
                f"\n[Causa direta] {type(exc.__cause__).__name__}: {exc.__cause__}",
                file=sys.stderr,
            )
        print("\n[Traceback completo]", file=sys.stderr)
        traceback.print_exc(file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
