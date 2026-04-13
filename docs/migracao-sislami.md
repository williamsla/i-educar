# Importacao e migracao SISLAMI -> i-Educar

Este projeto agora possui um script unico para importar os CSVs do SISLAMI e gerar um schema de migracao inicial no PostgreSQL.

## Arquivo

- `script/import_sislami.py`
- `script/migrar_sislami_para_pmieducar.sql`
- `script/migracao_sislami_completa.sh`

## O que o script faz

1. **Importacao bruta**
   - Le todos os CSV de `import/SISLAME_IGACI` (ou diretorio informado).
   - Cria tabelas no schema `sislami_raw` (uma por arquivo CSV).
   - Normaliza nomes de colunas para padrao SQL (`snake_case`, sem acentos).
   - Faz `\copy` para carga em lote.

2. **Migracao inicial**
   - Cria schema `sislami_migracao`.
   - Cria funcao `parse_sislami_timestamp()` para tratar formatos de data diferentes.
   - Materializa tabelas:
     - `sislami_migracao.instituicao`
     - `sislami_migracao.aluno`
     - `sislami_migracao.aluno_instituicao`

3. **Carga para i-Educar (`pmieducar.*`)**
   - Script SQL idempotente com tabelas de mapeamento em `sislami_migracao`:
     - `mapa_instituicao`
     - `mapa_escola`
     - `mapa_aluno`
     - `mapa_matricula`
   - Carrega dados em:
     - `pmieducar.instituicao`
     - `pmieducar.escola`
     - `pmieducar.curso`
     - `pmieducar.serie`
     - `pmieducar.turma`
     - `cadastro.pessoa`
     - `cadastro.fisica`
     - `pmieducar.aluno`
     - `pmieducar.matricula`
     - `pmieducar.matricula_turma`
     - `modules.componente_curricular`
     - `modules.componente_curricular_turma`
     - `modules.nota_aluno`
     - `modules.nota_geral`
     - `modules.nota_componente_curricular`
     - `modules.falta_aluno`
     - `modules.falta_geral`
     - `modules.falta_componente_curricular`
     - `modules.parecer_aluno`
     - `modules.parecer_geral`
     - `pmieducar.historico_escolar`
     - `pmieducar.historico_disciplinas`

## Pre-requisitos

- PostgreSQL acessivel via `psql`
- Base i-Educar criada (padrao: `ieducar`)

## Execucao

Na raiz do projeto:

```bash
python3 script/import_sislami.py --truncate
```

### Opcoes uteis

- `--input-dir <caminho>`: altera origem dos CSVs
- `--schema-raw <schema>`: altera schema bruto
- `--schema-migration <schema>`: altera schema de migracao
- `--skip-import`: roda apenas a parte de migracao
- `--skip-migration`: roda apenas a importacao

Exemplo:

```bash
python3 script/import_sislami.py \
  --input-dir import/SISLAME_IGACI \
  --schema-raw sislami_raw \
  --schema-migration sislami_migracao \
  --truncate
```

Exemplo sem Docker com host/porta/senha:

```bash
python3 script/import_sislami.py \
  --input-dir import/SISLAME_IGACI \
  --db-host 127.0.0.1 \
  --db-port 5432 \
  --db-user ieducar \
  --db-name ieducar \
  --db-password ieducar \
  --truncate
```

Exemplo com Docker (opcional):

```bash
python3 script/import_sislami.py --truncate --use-docker
```

## Validacao rapida

```sql
SELECT count(*) FROM sislami_raw.tb_aluno;
SELECT count(*) FROM sislami_migracao.aluno;
SELECT count(*) FROM sislami_migracao.instituicao;
SELECT count(*) FROM sislami_migracao.aluno_instituicao;
```

## Carga final em `pmieducar.*`

Depois de executar o `import_sislami.py`, rode:

```bash
psql \
  -v ON_ERROR_STOP=1 \
  -U ieducar \
  -d ieducar \
  -v user_id_cad=1 \
  -v ano_letivo=2026 \
  -f script/migrar_sislami_para_pmieducar.sql
```

Ou com orquestrador unico:

```bash
script/migracao_sislami_completa.sh \
  1 \
  2026 \
  127.0.0.1 \
  5432 \
  ieducar \
  ieducar \
  ieducar_sislami
```

### Observacoes importantes

- `user_id_cad` deve ser um usuario valido de cadastro no seu banco.
- O script tenta criar o banco informado (`DB_NAME`) caso nao exista.
- O 8o parametro opcional permite trocar o banco administrativo usado para checagem/criacao (padrao: `postgres`).
- A carga de matricula e basica (aluno x escola); **nao** enturma (`matricula_turma`).
- O script e idempotente pelas tabelas `mapa_*`, entao novas execucoes nao duplicam o que ja foi mapeado.

## Proximo passo recomendado

Com o schema `sislami_migracao` pronto, criar scripts de carga para tabelas finais do i-Educar (`pmieducar.*`) com regras especificas da rede (de/para de escola, curso, serie, turma, situacoes e historicos).
