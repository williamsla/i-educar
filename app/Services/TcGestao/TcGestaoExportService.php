<?php

namespace App\Services\TcGestao;

use App\Services\Siap\SiapAddressHelper;
use App\Services\Siap\SiapCodeMappers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class TcGestaoExportService
{
    private array $alerts = [];

    private const MESES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public function export(int $ano, int $mes, bool $somenteAlunosComInep = false): array
    {
        $this->alerts = [];
        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim = date('Y-m-t', strtotime($inicio));

        $arquivos = [
            'Escola.csv' => $this->csvEscola($ano),
            'Aluno.csv' => $this->csvAluno($ano, $inicio, $fim, $somenteAlunosComInep),
            'Turma.csv' => $this->csvTurma($ano),
            'TurmaAluno.csv' => $this->csvTurmaAluno($ano, $inicio, $fim, $somenteAlunosComInep),
            'ProfissionalEducacao.csv' => $this->csvProfissional($ano),
            'VinculoProfissionalEducacao.csv' => $this->csvVinculo($ano),
            'TurmaProfissional.csv' => $this->csvTurmaProfissional($ano),
            'ProfissionalFalta.csv' => $this->csvFaltas($ano, $mes, $inicio, $fim),
        ];

        return $this->compactar($arquivos, $ano, $mes);
    }

    public function getAlerts(): array
    {
        return $this->alerts;
    }

    private function csvEscola(int $ano): string
    {
        $headers = [
            'EscolaId', 'INEP', 'NomeEscola', 'Localizacao', 'LocalizacaoDiferenciada',
            'EnderecoEscola', 'CEP', 'SituacaoEscola', 'ParceriaPoderPublico',
        ];

        $escolas = DB::table('pmieducar.escola as e')
            ->join('pmieducar.escola_ano_letivo as eal', 'eal.ref_cod_escola', '=', 'e.cod_escola')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->leftJoin('cadastro.pessoa as p', 'p.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
            ->where('e.ativo', 1)
            ->where('eal.ativo', 1)
            ->where('eal.ano', $ano)
            ->whereNotNull('inep.cod_escola_inep')
            ->select(
                'e.cod_escola',
                'inep.cod_escola_inep as inep',
                DB::raw('COALESCE(p.nome, j.fantasia, e.sigla) as nome_escola'),
                'e.zona_localizacao',
                'e.localizacao_diferenciada',
                'e.situacao_funcionamento',
                'e.conveniada_com_poder_publico',
                'e.poder_publico_parceria_convenio'
            )
            ->distinct()
            ->get();

        $rows = [];
        foreach ($escolas as $escola) {
            $endereco = $this->montarEndereco((int) $escola->cod_escola);
            $rows[] = [
                (string) $escola->cod_escola,
                (string) $escola->inep,
                mb_substr((string) $escola->nome_escola, 0, 255),
                SiapCodeMappers::localizacao($escola->zona_localizacao),
                SiapCodeMappers::localizacaoDiferenciada($escola->localizacao_diferenciada),
                mb_substr($endereco['texto'] ?: 'NÃO INFORMADO', 0, 255),
                SiapCodeMappers::cep($endereco['cep'] ?? null),
                SiapCodeMappers::situacaoEscola($escola->situacao_funcionamento),
                SiapCodeMappers::parceriaPoderPublico(
                    $escola->poder_publico_parceria_convenio ?? $escola->conveniada_com_poder_publico
                ),
            ];
        }

        if (empty($rows)) {
            $this->alerts[] = '[Escola] Nenhuma escola com INEP encontrada.';
        }

        return TcGestaoCsvWriter::toString($headers, $rows);
    }

    private function csvAluno(int $ano, string $inicio, string $fim, bool $somenteAlunosComInep = false): string
    {
        $headers = [
            'AlunoId', 'Identificacao', 'CPF', 'Nome', 'DataNascimento', 'NomeMae', 'NomePai',
            'Sexo', 'CorRaca', 'NecessitaEducacaoEspecial', 'CEP', 'ZonaResidencia',
            'LocalizacaoDiferenciada', 'TransporteEscolarPublico',
        ];

        // Todos com matrícula/enturmação ativa no ano (mês não restringe o cadastro de alunos).
        $query = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 'a.ref_idpes')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'a.ref_idpes')
            ->leftJoin('modules.educacenso_cod_aluno as inep', 'inep.cod_aluno', '=', 'a.cod_aluno')
            ->leftJoin('cadastro.fisica_raca as fr', 'fr.ref_idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.pessoa as mae', 'mae.idpes', '=', 'f.idpes_mae')
            ->leftJoin('cadastro.pessoa as pai', 'pai.idpes', '=', 'f.idpes_pai')
            ->where('m.ano', $ano)
            ->where('m.ativo', 1)
            ->where('mt.ativo', 1)
            ->where('a.ativo', 1);

        if ($somenteAlunosComInep) {
            $query->whereNotNull('inep.cod_aluno_inep');
        }

        $alunos = $query
            ->select(
                'a.cod_aluno',
                'inep.cod_aluno_inep as aluno_inep',
                'f.cpf',
                'p.nome',
                'f.data_nasc',
                'mae.nome as nome_mae',
                'pai.nome as nome_pai',
                'f.sexo',
                'fr.ref_cod_raca as cor_raca',
                'f.zona_localizacao_censo',
                'f.localizacao_diferenciada',
                'a.tipo_transporte',
                'a.veiculo_transporte_escolar',
                'f.idpes'
            )
            ->distinct()
            ->orderBy('a.cod_aluno')
            ->get()
            ->unique('cod_aluno')
            ->values();

        $deficiencias = DB::table('cadastro.fisica_deficiencia')
            ->select('ref_idpes')
            ->distinct()
            ->pluck('ref_idpes')
            ->flip();

        $ceps = SiapAddressHelper::carregarCeps($alunos->pluck('idpes')->unique()->filter()->all());

        $rows = [];
        $omitidosCpf = 0;
        $semInep = 0;

        foreach ($alunos as $aluno) {
            $temInep = !empty($aluno->aluno_inep);
            $identificacao = $temInep ? (string) $aluno->aluno_inep : '';
            if (!$temInep) {
                $semInep++;
            }

            $cpf = SiapCodeMappers::cpf($aluno->cpf);
            // TC Gestão: exporta mesmo sem CPF (campo vazio), para não perder o aluno.
            if ($cpf === '') {
                $omitidosCpf++;
            }

            $temDeficiencia = isset($deficiencias[$aluno->idpes]);
            $transporte = ((int) ($aluno->tipo_transporte ?? 0) > 0)
                || !empty($aluno->veiculo_transporte_escolar);

            $rows[] = [
                (string) $aluno->cod_aluno,
                $identificacao,
                $cpf,
                mb_substr((string) $aluno->nome, 0, 255),
                $aluno->data_nasc ? date('Y-m-d', strtotime($aluno->data_nasc)) : '1900-01-01',
                mb_substr((string) ($aluno->nome_mae ?: 'NÃO INFORMADO'), 0, 255),
                mb_substr((string) ($aluno->nome_pai ?: 'NÃO INFORMADO'), 0, 255),
                SiapCodeMappers::sexo($aluno->sexo),
                SiapCodeMappers::corRaca($aluno->cor_raca),
                SiapCodeMappers::simNao($temDeficiencia),
                SiapCodeMappers::cep($ceps[$aluno->idpes] ?? null),
                SiapCodeMappers::localizacao($aluno->zona_localizacao_censo),
                SiapCodeMappers::localizacaoDiferenciada($aluno->localizacao_diferenciada),
                SiapCodeMappers::simNao($transporte),
            ];
        }

        $this->alerts[] = "[Aluno] Candidatos (matrícula ativa no ano): {$alunos->count()}.";
        $this->alerts[] = '[Aluno] Exportados: ' . count($rows) . '.';
        if ($omitidosCpf > 0) {
            $this->alerts[] = "[Aluno] Exportados sem CPF válido (campo vazio): {$omitidosCpf}.";
        }
        if ($somenteAlunosComInep) {
            $this->alerts[] = '[Aluno] Filtro ativo: somente alunos com código INEP.';
        } elseif ($semInep > 0) {
            $this->alerts[] = "[Aluno] Exportados sem INEP (Identificacao em branco): {$semInep}.";
        }

        return TcGestaoCsvWriter::toString($headers, $rows);
    }

    private function csvTurma(int $ano): string
    {
        $headers = [
            'Codigo', 'INEP', 'Etapa', 'Modalidade', 'Turno', 'CargaHoraria',
            'DataInicioAnoLetivo', 'DataFimAnoLetivo', 'ReferenciaAnoLetivo',
        ];
        $cargaDefault = (string) config('siap.defaults.carga_horaria_turma', '20');

        $turmas = DB::table('pmieducar.turma as t')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
            ->leftJoin('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
            ->where('t.ano', $ano)
            ->where('t.ativo', 1)
            ->select(
                't.cod_turma',
                't.ref_ref_cod_escola as cod_escola',
                'inep.cod_escola_inep as inep',
                't.etapa_educacenso',
                'c.modalidade_curso',
                't.turma_turno_id',
                't.hora_inicial',
                't.hora_final',
                't.ano'
            )
            ->get();

        $rows = [];
        foreach ($turmas as $turma) {
            $periodo = $this->periodoLetivo((int) $turma->cod_turma, (int) $turma->cod_escola, $ano);
            $carga = $this->calcularCargaHoraria($turma->hora_inicial, $turma->hora_final, $cargaDefault);

            $rows[] = [
                (string) $turma->cod_turma,
                (string) $turma->inep,
                SiapCodeMappers::etapa($turma->etapa_educacenso ? (int) $turma->etapa_educacenso : null),
                SiapCodeMappers::modalidade($turma->modalidade_curso ? (int) $turma->modalidade_curso : null),
                SiapCodeMappers::turno($turma->turma_turno_id ? (int) $turma->turma_turno_id : null),
                substr($carga, 0, 2),
                $periodo['inicio'],
                $periodo['fim'],
                (string) $turma->ano,
            ];
        }

        return TcGestaoCsvWriter::toString($headers, $rows);
    }

    private function csvTurmaAluno(int $ano, string $inicio, string $fim, bool $somenteAlunosComInep = false): string
    {
        $headers = ['AlunoId', 'CodigoTurma', 'INEP', 'Identificacao'];

        // Enturmações do ano; Identificacao preferencialmente INEP do aluno.
        $query = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.matricula_turma as mt', 'mt.ref_cod_matricula', '=', 'm.cod_matricula')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'mt.ref_cod_turma')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->leftJoin('modules.educacenso_cod_aluno as ain', 'ain.cod_aluno', '=', 'a.cod_aluno')
            ->where('m.ano', $ano)
            ->where('m.ativo', 1)
            ->where('mt.ativo', 1)
            ->where('t.ativo', 1)
            ->where('a.ativo', 1);

        if ($somenteAlunosComInep) {
            $query->whereNotNull('ain.cod_aluno_inep');
        }

        $vinculos = $query
            ->select(
                'a.cod_aluno',
                't.cod_turma',
                'inep.cod_escola_inep as inep',
                'ain.cod_aluno_inep as aluno_inep'
            )
            ->distinct()
            ->get();

        $rows = [];
        $ja = [];
        $semInep = 0;

        foreach ($vinculos as $v) {
            $identificacao = !empty($v->aluno_inep) ? (string) $v->aluno_inep : '';
            if ($identificacao === '') {
                $semInep++;
            }

            $chave = $v->cod_turma . '|' . $v->cod_aluno . '|' . $identificacao;
            if (isset($ja[$chave])) {
                continue;
            }
            $ja[$chave] = true;

            $rows[] = [
                (string) $v->cod_aluno,
                (string) $v->cod_turma,
                (string) $v->inep,
                $identificacao,
            ];
        }

        $this->alerts[] = '[TurmaAluno] Exportados: ' . count($rows) . '.';
        if ($somenteAlunosComInep) {
            $this->alerts[] = '[TurmaAluno] Filtro ativo: somente alunos com código INEP.';
        } elseif ($semInep > 0) {
            $this->alerts[] = "[TurmaAluno] Sem INEP (Identificacao em branco): {$semInep}.";
        }

        return TcGestaoCsvWriter::toString($headers, $rows);
    }

    private function csvProfissional(int $ano): string
    {
        $headers = [
            'ProfissionalEducacaoId', 'CPF', 'Nome', 'DataNascimento', 'NomeMae', 'NomePai',
            'Sexo', 'CorRaca', 'CEP', 'ZonaResidencia', 'LocalizacaoDiferenciada', 'Escolaridade',
        ];

        $profissionais = DB::table('pmieducar.servidor as s')
            ->join('pmieducar.servidor_alocacao as sa', 'sa.ref_cod_servidor', '=', 's.cod_servidor')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 's.cod_servidor')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
            ->leftJoin('cadastro.fisica_raca as fr', 'fr.ref_idpes', '=', 'f.idpes')
            ->leftJoin('cadastro.pessoa as mae', 'mae.idpes', '=', 'f.idpes_mae')
            ->leftJoin('cadastro.pessoa as pai', 'pai.idpes', '=', 'f.idpes_pai')
            ->where('s.ativo', 1)
            ->where('sa.ativo', 1)
            ->where('sa.ano', $ano)
            ->whereNotNull('f.cpf')
            ->select(
                's.cod_servidor',
                'f.cpf',
                'p.nome',
                'f.data_nasc',
                'mae.nome as nome_mae',
                'pai.nome as nome_pai',
                'f.sexo',
                'fr.ref_cod_raca as cor_raca',
                'f.zona_localizacao_censo',
                'f.localizacao_diferenciada',
                's.ref_idesco',
                'f.idpes'
            )
            ->distinct()
            ->get();

        $ceps = SiapAddressHelper::carregarCeps($profissionais->pluck('idpes')->unique()->filter()->all());
        $ja = [];
        $rows = [];

        foreach ($profissionais as $prof) {
            $cpf = SiapCodeMappers::cpf($prof->cpf);
            if ($cpf === '' || isset($ja[$cpf])) {
                continue;
            }
            $ja[$cpf] = true;

            $rows[] = [
                (string) $prof->cod_servidor,
                $cpf,
                mb_substr((string) $prof->nome, 0, 255),
                $prof->data_nasc ? date('Y-m-d', strtotime($prof->data_nasc)) : '1900-01-01',
                mb_substr((string) ($prof->nome_mae ?: 'NÃO INFORMADO'), 0, 255),
                mb_substr((string) ($prof->nome_pai ?: 'NÃO INFORMADO'), 0, 255),
                SiapCodeMappers::sexo($prof->sexo),
                SiapCodeMappers::corRaca($prof->cor_raca),
                SiapCodeMappers::cep($ceps[$prof->idpes] ?? null),
                SiapCodeMappers::localizacao($prof->zona_localizacao_censo),
                SiapCodeMappers::localizacaoDiferenciada($prof->localizacao_diferenciada),
                SiapCodeMappers::escolaridade($prof->ref_idesco ? (int) $prof->ref_idesco : null),
            ];
        }

        return TcGestaoCsvWriter::toString($headers, $rows);
    }

    private function csvVinculo(int $ano): string
    {
        $headers = ['INEP', 'CPF', 'Matricula', 'CargaHoraria', 'Funcao', 'TipoVinculo', 'DataInicio'];

        $vinculos = DB::table('pmieducar.servidor_alocacao as sa')
            ->join('pmieducar.servidor as s', 's.cod_servidor', '=', 'sa.ref_cod_servidor')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'sa.ref_cod_escola')
            ->leftJoin('pmieducar.servidor_funcao as sf', 'sf.cod_servidor_funcao', '=', 'sa.ref_cod_servidor_funcao')
            ->leftJoin('pmieducar.funcao as fn', 'fn.cod_funcao', '=', 'sf.ref_cod_funcao')
            ->leftJoin('portal.funcionario as func', 'func.ref_cod_pessoa_fj', '=', 's.cod_servidor')
            ->where('sa.ativo', 1)
            ->where('s.ativo', 1)
            ->where('sa.ano', $ano)
            ->whereNotNull('f.cpf')
            ->select(
                'inep.cod_escola_inep as inep',
                'f.cpf',
                'func.matricula',
                'sa.carga_horaria',
                's.carga_horaria as carga_servidor',
                'fn.professor',
                'sa.data_admissao',
                's.cod_servidor'
            )
            ->distinct()
            ->get();

        $funcoesProfessor = DB::table('modules.professor_turma')
            ->where('ano', $ano)
            ->select('servidor_id', 'funcao_exercida', 'tipo_vinculo')
            ->get()
            ->groupBy('servidor_id');

        $ja = [];
        $rows = [];

        foreach ($vinculos as $vinculo) {
            $cpf = SiapCodeMappers::cpf($vinculo->cpf);
            if ($cpf === '') {
                continue;
            }
            $chave = $vinculo->inep . '|' . $cpf;
            if (isset($ja[$chave])) {
                continue;
            }
            $ja[$chave] = true;

            $profTurma = $funcoesProfessor->get($vinculo->cod_servidor)?->first();
            $carga = (int) ($vinculo->carga_horaria ?: $vinculo->carga_servidor ?: 20);
            $dataInicio = $vinculo->data_admissao
                ? date('Y-m-d', strtotime($vinculo->data_admissao))
                : sprintf('%04d-01-01', $ano);

            $rows[] = [
                (string) $vinculo->inep,
                $cpf,
                (string) ($vinculo->matricula ?? ''),
                (string) min(99, max(1, $carga)),
                SiapCodeMappers::funcao(
                    $profTurma->funcao_exercida ?? null,
                    (bool) ($vinculo->professor ?? $profTurma)
                ),
                SiapCodeMappers::tipoVinculo($profTurma->tipo_vinculo ?? 1),
                $dataInicio,
            ];
        }

        return TcGestaoCsvWriter::toString($headers, $rows);
    }

    private function csvTurmaProfissional(int $ano): string
    {
        $headers = ['ProfissionalEducacaoId', 'CodigoTurma', 'INEP', 'CPF', 'Matricula'];

        $vinculos = DB::table('modules.professor_turma as pt')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'pt.turma_id')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'pt.servidor_id')
            ->leftJoin('portal.funcionario as func', 'func.ref_cod_pessoa_fj', '=', 'pt.servidor_id')
            ->where('pt.ano', $ano)
            ->where('t.ativo', 1)
            ->whereNotNull('f.cpf')
            ->select(
                'pt.servidor_id as cod_servidor',
                't.cod_turma',
                'inep.cod_escola_inep as inep',
                'f.cpf',
                'func.matricula'
            )
            ->distinct()
            ->get();

        if ($vinculos->isEmpty()) {
            $vinculos = DB::table('pmieducar.quadro_horario as qh')
                ->join('pmieducar.quadro_horario_horarios as qhh', 'qhh.ref_cod_quadro_horario', '=', 'qh.cod_quadro_horario')
                ->join('pmieducar.turma as t', 't.cod_turma', '=', 'qh.ref_cod_turma')
                ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 't.ref_ref_cod_escola')
                ->join('cadastro.fisica as f', 'f.idpes', '=', 'qhh.ref_cod_servidor')
                ->leftJoin('portal.funcionario as func', 'func.ref_cod_pessoa_fj', '=', 'qhh.ref_cod_servidor')
                ->where('t.ano', $ano)
                ->where('t.ativo', 1)
                ->where('qh.ativo', 1)
                ->whereNotNull('f.cpf')
                ->select(
                    'qhh.ref_cod_servidor as cod_servidor',
                    't.cod_turma',
                    'inep.cod_escola_inep as inep',
                    'f.cpf',
                    'func.matricula'
                )
                ->distinct()
                ->get();
        }

        $ja = [];
        $rows = [];
        foreach ($vinculos as $v) {
            $cpf = SiapCodeMappers::cpf($v->cpf);
            if ($cpf === '') {
                continue;
            }
            $chave = $v->cod_turma . '|' . $cpf;
            if (isset($ja[$chave])) {
                continue;
            }
            $ja[$chave] = true;

            $rows[] = [
                (string) $v->cod_servidor,
                (string) $v->cod_turma,
                (string) $v->inep,
                $cpf,
                (string) ($v->matricula ?? ''),
            ];
        }

        return TcGestaoCsvWriter::toString($headers, $rows);
    }

    private function csvFaltas(int $ano, int $mes, string $inicio, string $fim): string
    {
        $headers = [
            'Competência',
            'Vínculo Profissional',
            'INEP',
            'CPF',
            'Matricula',
            'Quantidade de Faltas Justificadas',
            'Quantidade de Faltas Injustificadas',
            'Quantidade de Licenças Médicas',
            'Quantidade de Licenças Maternidade/Paternidade',
            'Quantidade de Abonos',
            'Quantidade de Outras Faltas',
        ];

        $competencia = (self::MESES[$mes] ?? (string) $mes) . '/' . $ano;
        $rows = [];

        try {
            DB::table('pmieducar.falta_atraso')->limit(1)->exists();
        } catch (\Throwable $e) {
            $this->alerts[] = '[ProfissionalFalta] Tabela de faltas não encontrada.';

            return TcGestaoCsvWriter::toString($headers, $rows, ';');
        }

        $alocacoes = DB::table('pmieducar.servidor_alocacao as sa')
            ->join('pmieducar.servidor as s', 's.cod_servidor', '=', 'sa.ref_cod_servidor')
            ->join('cadastro.pessoa as p', 'p.idpes', '=', 's.cod_servidor')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
            ->join('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'sa.ref_cod_escola')
            ->leftJoin('portal.funcionario as func', 'func.ref_cod_pessoa_fj', '=', 's.cod_servidor')
            ->where('sa.ativo', 1)
            ->where('s.ativo', 1)
            ->where('sa.ano', $ano)
            ->whereNotNull('f.cpf')
            ->select(
                's.cod_servidor',
                'p.nome',
                'inep.cod_escola_inep as inep',
                'f.cpf',
                'func.matricula'
            )
            ->distinct()
            ->get();

        foreach ($alocacoes as $alocacao) {
            $cpf = SiapCodeMappers::cpf($alocacao->cpf);
            if ($cpf === '') {
                continue;
            }

            $faltas = DB::table('pmieducar.falta_atraso')
                ->where('ref_cod_servidor', $alocacao->cod_servidor)
                ->whereBetween('data_falta_atraso', [$inicio, $fim])
                ->selectRaw('SUM(CASE WHEN justificada = 0 THEN 1 ELSE 0 END) as justificadas')
                ->selectRaw('SUM(CASE WHEN justificada <> 0 THEN 1 ELSE 0 END) as injustificadas')
                ->first();

            $licencaMedica = 0;
            $licencaMaternidade = 0;

            try {
                $afastamentos = DB::table('pmieducar.servidor_afastamento as af')
                    ->leftJoin('pmieducar.motivo_afastamento as ma', 'ma.cod_motivo_afastamento', '=', 'af.ref_cod_motivo_afastamento')
                    ->where('af.ref_cod_servidor', $alocacao->cod_servidor)
                    ->where(function ($q) use ($inicio) {
                        $q->whereDate('af.data_retorno', '>=', $inicio)->orWhereNull('af.data_retorno');
                    })
                    ->whereDate('af.data_saida', '<=', $fim)
                    ->select('af.data_saida', 'af.data_retorno', 'ma.nm_motivo')
                    ->get();

                foreach ($afastamentos as $af) {
                    $dias = $this->diasNoMes($af->data_saida, $af->data_retorno, $inicio, $fim);
                    $motivo = mb_strtolower((string) ($af->nm_motivo ?? ''));
                    if (str_contains($motivo, 'matern') || str_contains($motivo, 'patern')) {
                        $licencaMaternidade += $dias;
                    } elseif (str_contains($motivo, 'médic') || str_contains($motivo, 'medic') || str_contains($motivo, 'saúde') || str_contains($motivo, 'saude')) {
                        $licencaMedica += $dias;
                    }
                }
            } catch (\Throwable $e) {
                // opcional
            }

            $justificadas = (int) ($faltas->justificadas ?? 0);
            $injustificadas = (int) ($faltas->injustificadas ?? 0);
            if ($justificadas + $injustificadas + $licencaMedica + $licencaMaternidade === 0) {
                continue;
            }

            $inep = SiapCodeMappers::apenasDigitos((string) $alocacao->inep);
            $matricula = (string) ($alocacao->matricula ?? '');
            $vinculoLabel = $alocacao->cod_servidor . ' - ' . mb_strtoupper((string) $alocacao->nome);

            $rows[] = [
                $competencia,
                $vinculoLabel,
                $this->formatarInep($inep),
                $cpf,
                $matricula !== '' ? $this->formatarMatricula($matricula) : '',
                (string) min(999, $justificadas),
                (string) min(999, $injustificadas),
                (string) min(999, $licencaMedica),
                (string) min(999, $licencaMaternidade),
                '0',
                '0',
            ];
        }

        return TcGestaoCsvWriter::toString($headers, $rows, ';');
    }

    private function formatarInep(string $inep): string
    {
        $d = SiapCodeMappers::apenasDigitos($inep);
        if (strlen($d) === 8) {
            return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3);
        }

        return $d;
    }

    private function formatarMatricula(string $matricula): string
    {
        $d = SiapCodeMappers::apenasDigitos($matricula);
        if (strlen($d) >= 9) {
            return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5);
        }

        return $matricula;
    }

    private function calcularCargaHoraria($horaInicial, $horaFinal, string $default): string
    {
        if (!$horaInicial || !$horaFinal) {
            return $default;
        }

        try {
            $ini = strtotime((string) $horaInicial);
            $fim = strtotime((string) $horaFinal);
            if ($fim <= $ini) {
                return $default;
            }
            $horasDia = (int) round(($fim - $ini) / 3600);

            return (string) max(1, min(99, $horasDia * 5));
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function periodoLetivo(int $codTurma, int $codEscola, int $ano): array
    {
        $turmaModulo = DB::table('pmieducar.turma_modulo')
            ->where('ref_cod_turma', $codTurma)
            ->selectRaw('MIN(data_inicio) as inicio, MAX(data_fim) as fim')
            ->first();

        if ($turmaModulo && $turmaModulo->inicio && $turmaModulo->fim) {
            return [
                'inicio' => date('Y-m-d', strtotime($turmaModulo->inicio)),
                'fim' => date('Y-m-d', strtotime($turmaModulo->fim)),
            ];
        }

        $anoModulo = DB::table('pmieducar.ano_letivo_modulo')
            ->where('ref_ano', $ano)
            ->where('ref_ref_cod_escola', $codEscola)
            ->selectRaw('MIN(data_inicio) as inicio, MAX(data_fim) as fim')
            ->first();

        return [
            'inicio' => $anoModulo && $anoModulo->inicio
                ? date('Y-m-d', strtotime($anoModulo->inicio))
                : sprintf('%04d-02-01', $ano),
            'fim' => $anoModulo && $anoModulo->fim
                ? date('Y-m-d', strtotime($anoModulo->fim))
                : sprintf('%04d-12-20', $ano),
        ];
    }

    private function diasNoMes($saida, $retorno, string $inicioMes, string $fimMes): int
    {
        $ini = max(strtotime($inicioMes), strtotime((string) $saida));
        $fim = min(strtotime($fimMes), $retorno ? strtotime((string) $retorno) : strtotime($fimMes));
        if ($fim < $ini) {
            return 0;
        }

        return (int) floor(($fim - $ini) / 86400) + 1;
    }

    private function montarEndereco(int $codEscola): array
    {
        try {
            $place = DB::table('pmieducar.escola as e')
                ->join('cadastro.pessoa as p', 'p.idpes', '=', 'e.ref_idpes')
                ->leftJoin('cadastro.pessoa_has_place as php', 'php.person_id', '=', 'p.idpes')
                ->leftJoin('addresses.places as pl', 'pl.id', '=', 'php.place_id')
                ->where('e.cod_escola', $codEscola)
                ->select(
                    'pl.address', 'pl.number', 'pl.complement', 'pl.neighborhood',
                    'pl.postal_code', 'pl.city', 'pl.state_abbreviation'
                )
                ->first();
        } catch (\Throwable $e) {
            $place = null;
        }

        if (!$place || empty($place->address)) {
            try {
                $place = DB::table('pmieducar.escola as e')
                    ->leftJoin('cadastro.endereco_pessoa as ep', 'ep.idpes', '=', 'e.ref_idpes')
                    ->leftJoin('public.logradouro as l', 'l.idlog', '=', 'ep.idlog')
                    ->leftJoin('public.bairro as b', 'b.idbai', '=', 'ep.idbai')
                    ->leftJoin('public.municipio as m', 'm.idmun', '=', 'b.idmun')
                    ->where('e.cod_escola', $codEscola)
                    ->select(
                        'l.nome as address',
                        'ep.numero as number',
                        'ep.complemento as complement',
                        'b.nome as neighborhood',
                        'ep.cep as postal_code',
                        'm.nome as city',
                        'm.sigla_uf as state_abbreviation'
                    )
                    ->first();
            } catch (\Throwable $e) {
                $place = null;
            }
        }

        if (!$place) {
            return ['texto' => '', 'cep' => ''];
        }

        $partes = array_filter([
            trim(implode(', ', array_filter([
                $place->address ?? null,
                isset($place->number) && $place->number !== null && $place->number !== '' ? (string) $place->number : null,
                $place->complement ?? null,
            ]))),
            $place->neighborhood ?? null,
            trim(($place->city ?? '') . (!empty($place->state_abbreviation) ? ' - ' . $place->state_abbreviation : '')),
        ]);

        return [
            'texto' => implode(' - ', $partes),
            'cep' => $place->postal_code ?? '',
        ];
    }

    private function compactar(array $arquivos, int $ano, int $mes): array
    {
        $dir = 'exportacoes/tc-gestao';
        Storage::disk('public')->makeDirectory($dir);

        $baseName = sprintf('TC_Gestao_Publica_%04d_%02d', $ano, $mes);
        $zipRelative = $dir . '/' . $baseName . '.zip';
        $txtRelative = $dir . '/' . $baseName . '_avisos.txt';
        $zipPath = Storage::disk('public')->path($zipRelative);

        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o ZIP da exportação TC Gestão Pública.');
        }

        foreach ($arquivos as $nome => $conteudo) {
            $csvRelative = $dir . '/' . $nome;
            Storage::disk('public')->put($csvRelative, $conteudo);
            $zip->addFile(Storage::disk('public')->path($csvRelative), $nome);
        }

        $zip->close();

        Storage::disk('public')->put(
            $txtRelative,
            empty($this->alerts) ? 'Exportação TC Gestão Pública concluída sem avisos.' : implode(PHP_EOL, $this->alerts)
        );

        return [
            'zipUrl' => asset(Storage::disk('public')->url($zipRelative)),
            'txtUrl' => asset(Storage::disk('public')->url($txtRelative)),
            'alerts' => $this->alerts,
        ];
    }
}
