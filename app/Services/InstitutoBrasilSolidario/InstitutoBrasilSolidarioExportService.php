<?php

namespace App\Services\InstitutoBrasilSolidario;

use App\Models\RegistrationStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class InstitutoBrasilSolidarioExportService
{
    /**
     * @var array<int, true>
     */
    private const FUNCOES_DOCENTES = [
        1 => true,
        5 => true,
        6 => true,
        9 => true,
    ];

    public function __construct(private readonly InstitutoBrasilSolidarioWorkbook $workbook)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function download(array $filters): BinaryFileResponse
    {
        $ano = (int) $filters['ano'];
        $instituicaoId = (int) $filters['institution_id'];
        $dados = $this->coletar(
            $ano,
            $instituicaoId,
            $filters['school_id'] ?? null,
            $filters['school_ids'] ?? null,
            is_array($filters['cursos'] ?? null) ? $filters['cursos'] : []
        );

        if ($dados['escolas'] === []) {
            throw new \RuntimeException('Nenhuma escola encontrada para o ano e a instituição selecionados.');
        }

        $caminho = $this->workbook->salvar($dados['municipio'], $dados['instituicao'], $dados['escolas']);

        return response()
            ->download($caminho, 'instituto_brasil_solidario_' . $ano . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  array<int, int>|null  $schoolIds
     * @param  array<int, string>  $cursos
     * @return array{municipio: string, instituicao: array<string, string>, escolas: array<int, EscolaPlanilha>}
     */
    private function coletar(int $ano, int $instituicaoId, mixed $schoolId, ?array $schoolIds, array $cursos): array
    {
        if ($cursos === []) {
            throw new \RuntimeException('Informe os cursos de pelo menos um grupo.');
        }

        $instituicao = $this->instituicao($instituicaoId);
        $municipio = $this->municipio($instituicao['cidade'], $instituicao['uf']);
        $registros = $this->escolas($ano, $instituicaoId, $schoolId, $schoolIds);

        if ($registros === []) {
            return [
                'municipio' => $municipio,
                'instituicao' => $this->instituicaoPlanilha($instituicao, $municipio),
                'escolas' => [],
            ];
        }

        $ids = array_map(static fn (object $escola): int => (int) $escola->cod_escola, $registros);
        $enderecos = $this->enderecos(array_map(static fn (object $escola): int => (int) $escola->ref_idpes, $registros));
        $contagens = $this->contagens($ano, $instituicaoId, $ids, $cursos);
        $deficiencias = $this->deficiencias($contagens['alunos_idpes'], $contagens['docentes_idpes']);

        $escolas = [];
        $sequencia = 0;

        foreach ($registros as $registro) {
            $escolaId = (int) $registro->cod_escola;
            $endereco = $enderecos[(int) $registro->ref_idpes] ?? null;
            $cidade = $endereco['cidade'] ?? $instituicao['cidade'];
            $uf = $endereco['uf'] ?? $instituicao['uf'];

            $escolas[] = new EscolaPlanilha(
                sequencia: $sequencia,
                nome: (string) $registro->nome,
                inep: trim((string) ($registro->inep ?? '')),
                rede: $this->rede($registro->dependencia_administrativa !== null ? (int) $registro->dependencia_administrativa : null),
                zona: $this->zona($registro->zona_localizacao !== null ? (int) $registro->zona_localizacao : null),
                municipio: $this->municipio($cidade, $uf),
                redesSociais: '',
                contato: (string) ($registro->gestor ?? ''),
                telefone: $this->telefone($registro->ddd, $registro->fone),
                email: trim((string) ($registro->email ?: $registro->email_gestor ?: '')),
                docentes: count($contagens['docentes'][$escolaId] ?? []),
                funcionarios: count($contagens['funcionarios'][$escolaId] ?? []),
                defAlunos: $this->contarDeficiencias($contagens['alunos_idpes'][$escolaId] ?? [], $deficiencias),
                defEducadores: $this->contarDeficiencias($contagens['docentes_idpes'][$escolaId] ?? [], $deficiencias),
                turmas: $contagens['turmas'][$escolaId] ?? EtapaBuckets::zeros(),
                alunos: $contagens['alunos'][$escolaId] ?? EtapaBuckets::zeros(),
                educadores: $contagens['educadores'][$escolaId] ?? [
                    'ei' => 0,
                    'ai' => 0,
                    'af' => 0,
                    'em' => 0,
                    'eja' => 0,
                ],
            );
            $sequencia++;
        }

        return [
            'municipio' => $municipio,
            'instituicao' => $this->instituicaoPlanilha($instituicao, $municipio),
            'escolas' => $escolas,
        ];
    }

    /**
     * @param  array<int, int>|null  $schoolIds
     * @return array<int, object>
     */
    private function escolas(int $ano, int $instituicaoId, mixed $schoolId, ?array $schoolIds): array
    {
        $query = DB::table('pmieducar.escola as e')
            ->join('pmieducar.escola_ano_letivo as eal', function ($join) use ($ano) {
                $join->on('eal.ref_cod_escola', '=', 'e.cod_escola')
                    ->where('eal.ano', $ano)
                    ->where('eal.ativo', 1);
            })
            ->leftJoin('modules.educacenso_cod_escola as inep', 'inep.cod_escola', '=', 'e.cod_escola')
            ->leftJoin('cadastro.pessoa as p', 'p.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
            ->leftJoin('cadastro.pessoa as gestor', 'gestor.idpes', '=', 'e.ref_idpes_gestor')
            ->leftJoin('cadastro.fone_pessoa as tel', function ($join) {
                $join->on('tel.idpes', '=', 'e.ref_idpes')
                    ->where('tel.tipo', 1);
            })
            ->where('e.ativo', 1)
            ->where('e.ref_cod_instituicao', $instituicaoId)
            ->select(
                'e.cod_escola',
                'e.ref_idpes',
                'e.dependencia_administrativa',
                'e.zona_localizacao',
                'e.email_gestor',
                'inep.cod_escola_inep as inep',
                'p.email',
                'tel.ddd',
                'tel.fone',
                'gestor.nome as gestor',
                DB::raw('COALESCE(NULLIF(p.nome, \'\'), NULLIF(j.fantasia, \'\'), e.sigla) as nome')
            )
            ->orderBy('nome')
            ->orderBy('e.cod_escola');

        if ($schoolId) {
            if (is_array($schoolIds) && $schoolIds !== [] && !in_array((int) $schoolId, array_map('intval', $schoolIds), true)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('e.cod_escola', (int) $schoolId);
            }
        } elseif (is_array($schoolIds) && $schoolIds !== []) {
            $query->whereIn('e.cod_escola', $schoolIds);
        }

        return $query->get()->all();
    }

    /**
     * @param  array<int, int>  $escolasIds
     * @param  array<int, string>  $cursos
     * @return array{
     *     turmas: array<int, array<string, int>>,
     *     alunos: array<int, array<string, int>>,
     *     educadores: array<int, array{ei: int, ai: int, af: int, em: int, eja: int}>,
     *     docentes: array<int, array<int, true>>,
     *     funcionarios: array<int, array<int, true>>,
     *     alunos_idpes: array<int, array<int, true>>,
     *     docentes_idpes: array<int, array<int, true>>
     * }
     */
    private function contagens(int $ano, int $instituicaoId, array $escolasIds, array $cursos): array
    {
        $turmas = [];
        $alunos = [];
        $educadores = [];
        $docentes = [];
        $funcionarios = [];
        $alunosIdpes = [];
        $docentesIdpes = [];
        $turmasMultisseriadas = [];
        $bucketsPorTurma = [];
        $docentesPorTurma = [];

        foreach ($escolasIds as $escolaId) {
            $turmas[$escolaId] = EtapaBuckets::zeros();
            $alunos[$escolaId] = EtapaBuckets::zeros();
            $educadores[$escolaId] = ['ei' => 0, 'ai' => 0, 'af' => 0, 'em' => 0, 'eja' => 0];
            $docentes[$escolaId] = [];
            $funcionarios[$escolaId] = [];
            $alunosIdpes[$escolaId] = [];
            $docentesIdpes[$escolaId] = [];
        }

        $turmasRows = DB::table('pmieducar.turma as t')
            ->leftJoin('pmieducar.serie as s', 's.cod_serie', '=', 't.ref_ref_cod_serie')
            ->where('t.ativo', 1)
            ->where('t.ano', $ano)
            ->where('t.ref_cod_instituicao', $instituicaoId)
            ->whereIn('t.ref_ref_cod_escola', $escolasIds)
            ->get([
                't.cod_turma',
                't.ref_ref_cod_escola as escola',
                't.etapa_educacenso',
                DB::raw('COALESCE(t.ref_cod_curso, s.ref_cod_curso) as curso_id'),
                's.etapa_curso',
            ]);

        foreach ($turmasRows as $turma) {
            $escolaId = (int) $turma->escola;
            $etapa = $turma->etapa_educacenso !== null ? (int) $turma->etapa_educacenso : null;
            $bucket = $this->bucketDoCurso(
                $turma->curso_id !== null ? (int) $turma->curso_id : null,
                (int) ($turma->etapa_curso ?? 1),
                $etapa,
                $cursos
            );

            if (EtapaBuckets::isMultisseriada($etapa)) {
                $turmasMultisseriadas[(int) $turma->cod_turma] = [
                    'escola' => $escolaId,
                    'fallback' => $bucket,
                ];
                continue;
            }

            if ($bucket === null) {
                continue;
            }

            $turmas[$escolaId][$bucket]++;
            $bucketsPorTurma[(int) $turma->cod_turma][$bucket] = true;
        }

        $matriculas = DB::table('pmieducar.matricula as m')
            ->join('pmieducar.matricula_turma as mt', function ($join) {
                $join->on('mt.ref_cod_matricula', '=', 'm.cod_matricula')
                    ->where('mt.ativo', 1);
            })
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'mt.ref_cod_turma')
            ->join('pmieducar.aluno as a', 'a.cod_aluno', '=', 'm.ref_cod_aluno')
            ->leftJoin('pmieducar.serie as s', 's.cod_serie', '=', 't.ref_ref_cod_serie')
            ->leftJoin('pmieducar.serie as sm', 'sm.cod_serie', '=', 'm.ref_ref_cod_serie')
            ->where('m.ativo', 1)
            ->where('m.ano', $ano)
            ->where('t.ativo', 1)
            ->whereIn('m.ref_ref_cod_escola', $escolasIds)
            ->whereNotIn('m.aprovado', [
                RegistrationStatus::TRANSFERRED,
                RegistrationStatus::RECLASSIFIED,
                RegistrationStatus::ABANDONED,
                RegistrationStatus::PRE_REGISTRATION,
                RegistrationStatus::DECEASED,
            ])
            ->get([
                'm.cod_matricula',
                'm.ref_ref_cod_escola as escola',
                'a.ref_idpes',
                't.cod_turma',
                'mt.etapa_educacenso as etapa_aluno',
                't.etapa_educacenso as etapa_turma',
                DB::raw('COALESCE(m.ref_cod_curso, sm.ref_cod_curso, t.ref_cod_curso, s.ref_cod_curso) as curso_id'),
                DB::raw('COALESCE(sm.etapa_curso, s.etapa_curso, 1) as etapa_curso'),
            ]);

        $matriculasContadas = [];

        foreach ($matriculas as $matricula) {
            $escolaId = (int) $matricula->escola;
            $idpes = (int) $matricula->ref_idpes;

            if ($idpes > 0) {
                $alunosIdpes[$escolaId][$idpes] = true;
            }

            $chave = $escolaId . ':' . (int) $matricula->cod_matricula;

            if (isset($matriculasContadas[$chave])) {
                continue;
            }

            $bucket = $this->bucketDoCurso(
                $matricula->curso_id !== null ? (int) $matricula->curso_id : null,
                (int) $matricula->etapa_curso,
                $matricula->etapa_aluno !== null ? (int) $matricula->etapa_aluno : ($matricula->etapa_turma !== null ? (int) $matricula->etapa_turma : null),
                $cursos
            );

            if ($bucket === null) {
                continue;
            }

            $matriculasContadas[$chave] = true;
            $alunos[$escolaId][$bucket]++;
            $bucketsPorTurma[(int) $matricula->cod_turma][$bucket] = true;
        }

        foreach ($turmasMultisseriadas as $turmaId => $info) {
            $buckets = array_keys($bucketsPorTurma[$turmaId] ?? []);

            if ($buckets === [] && $info['fallback'] !== null) {
                $buckets = [$info['fallback']];
            }

            foreach ($buckets as $bucket) {
                $turmas[$info['escola']][$bucket]++;
                $bucketsPorTurma[$turmaId][$bucket] = true;
            }
        }

        $professores = DB::table('modules.professor_turma as pt')
            ->join('pmieducar.turma as t', 't.cod_turma', '=', 'pt.turma_id')
            ->where('pt.ano', $ano)
            ->where('pt.instituicao_id', $instituicaoId)
            ->where('t.ativo', 1)
            ->whereIn('t.ref_ref_cod_escola', $escolasIds)
            ->get([
                'pt.servidor_id',
                'pt.funcao_exercida',
                'pt.turma_id',
                't.ref_ref_cod_escola as escola',
            ]);

        foreach ($professores as $professor) {
            $escolaId = (int) $professor->escola;
            $servidorId = (int) $professor->servidor_id;
            $funcao = (int) $professor->funcao_exercida;

            if (!isset(self::FUNCOES_DOCENTES[$funcao])) {
                $funcionarios[$escolaId][$servidorId] = true;
                continue;
            }

            $docentes[$escolaId][$servidorId] = true;
            $docentesIdpes[$escolaId][$servidorId] = true;
            $docentesPorTurma[(int) $professor->turma_id][$servidorId] = $escolaId;
        }

        $educadoresPorGrupo = [];

        foreach ($docentesPorTurma as $turmaId => $servidores) {
            foreach ($servidores as $servidorId => $escolaId) {
                $grupos = [];

                foreach (array_keys($bucketsPorTurma[$turmaId] ?? []) as $bucket) {
                    $grupo = EtapaBuckets::GRUPO[$bucket] ?? null;

                    if ($grupo !== null) {
                        $grupos[$grupo] = true;
                    }
                }

                foreach (array_keys($grupos) as $grupo) {
                    $educadoresPorGrupo[$escolaId][$grupo][$servidorId] = true;
                }
            }
        }

        foreach ($educadoresPorGrupo as $escolaId => $grupos) {
            foreach ($grupos as $grupo => $servidores) {
                $educadores[$escolaId][$grupo] = count($servidores);
            }
        }

        $alocacoes = DB::table('pmieducar.servidor_alocacao')
            ->where('ativo', 1)
            ->where('ref_ref_cod_instituicao', $instituicaoId)
            ->whereIn('ref_cod_escola', $escolasIds)
            ->where(function ($query) use ($ano) {
                $query->where('ano', $ano)->orWhereNull('ano');
            })
            ->get(['ref_cod_escola', 'ref_cod_servidor']);

        foreach ($alocacoes as $alocacao) {
            $escolaId = (int) $alocacao->ref_cod_escola;
            $servidorId = (int) $alocacao->ref_cod_servidor;

            if (!isset($docentes[$escolaId][$servidorId])) {
                $funcionarios[$escolaId][$servidorId] = true;
            }
        }

        return [
            'turmas' => $turmas,
            'alunos' => $alunos,
            'educadores' => $educadores,
            'docentes' => $docentes,
            'funcionarios' => $funcionarios,
            'alunos_idpes' => $alunosIdpes,
            'docentes_idpes' => $docentesIdpes,
        ];
    }

    /**
     * @param  array<int, string>  $cursos
     */
    private function bucketDoCurso(?int $cursoId, int $etapaCurso, ?int $etapaEducacenso, array $cursos): ?string
    {
        if ($cursoId === null || !isset($cursos[$cursoId])) {
            return null;
        }

        return EtapaBuckets::colunaDoCurso($cursos[$cursoId], $etapaCurso, $etapaEducacenso);
    }

    /**
     * @param  array<int, array<int, true>>  $alunosIdpes
     * @param  array<int, array<int, true>>  $docentesIdpes
     * @return array<int, array{motora: bool, intelectual: bool, psicossocial: bool, visual: bool, auditiva: bool}>
     */
    private function deficiencias(array $alunosIdpes, array $docentesIdpes): array
    {
        $ids = [];

        foreach ([$alunosIdpes, $docentesIdpes] as $grupos) {
            foreach ($grupos as $pessoas) {
                foreach (array_keys($pessoas) as $idpes) {
                    $ids[(int) $idpes] = true;
                }
            }
        }

        $flags = [];

        foreach (array_chunk(array_keys($ids), 1000) as $lote) {
            $registros = DB::table('cadastro.fisica_deficiencia as fd')
                ->join('cadastro.deficiencia as d', 'd.cod_deficiencia', '=', 'fd.ref_cod_deficiencia')
                ->whereIn('fd.ref_idpes', $lote)
                ->get(['fd.ref_idpes', 'd.deficiencia_educacenso', 'd.transtorno_educacenso']);

            foreach ($registros as $registro) {
                $idpes = (int) $registro->ref_idpes;
                $flags[$idpes] ??= DeficienciaGrupos::vazio();
                DeficienciaGrupos::aplicarCodigo(
                    $registro->deficiencia_educacenso !== null ? (int) $registro->deficiencia_educacenso : null,
                    $flags[$idpes]
                );
                DeficienciaGrupos::aplicarTranstorno(
                    $registro->transtorno_educacenso !== null ? (int) $registro->transtorno_educacenso : null,
                    $flags[$idpes]
                );
            }
        }

        return $flags;
    }

    /**
     * @param  array<int, true>  $pessoas
     * @param  array<int, array{motora: bool, intelectual: bool, psicossocial: bool, visual: bool, auditiva: bool}>  $deficiencias
     * @return array{motora: int, intelectual: int, psicossocial: int, visual: int, auditiva: int}
     */
    private function contarDeficiencias(array $pessoas, array $deficiencias): array
    {
        $totais = [
            'motora' => 0,
            'intelectual' => 0,
            'psicossocial' => 0,
            'visual' => 0,
            'auditiva' => 0,
        ];

        foreach (array_keys($pessoas) as $idpes) {
            $flags = $deficiencias[(int) $idpes] ?? null;

            if ($flags === null) {
                continue;
            }

            foreach ($totais as $grupo => $quantidade) {
                if ($flags[$grupo]) {
                    $totais[$grupo]++;
                }
            }
        }

        return $totais;
    }

    /**
     * @param  array<int, int>  $idpes
     * @return array<int, array{cidade: string, uf: string}>
     */
    private function enderecos(array $idpes): array
    {
        $idpes = array_values(array_unique(array_filter($idpes)));
        $enderecos = [];

        if ($idpes === []) {
            return $enderecos;
        }

        try {
            $modernos = DB::table('cadastro.pessoa_has_place as php')
                ->join('addresses.places as pl', 'pl.id', '=', 'php.place_id')
                ->leftJoin('cities as c', 'c.id', '=', 'pl.city_id')
                ->leftJoin('states as st', 'st.id', '=', 'c.state_id')
                ->whereIn('php.person_id', $idpes)
                ->get(['php.person_id', 'c.name as cidade', 'st.abbreviation as uf']);

            foreach ($modernos as $endereco) {
                $enderecos[(int) $endereco->person_id] = [
                    'cidade' => (string) ($endereco->cidade ?? ''),
                    'uf' => (string) ($endereco->uf ?? ''),
                ];
            }
        } catch (\Throwable) {
        }

        $faltantes = array_values(array_diff($idpes, array_keys($enderecos)));

        if ($faltantes === []) {
            return $enderecos;
        }

        try {
            $legados = DB::table('cadastro.endereco_pessoa as ep')
                ->leftJoin('public.bairro as b', 'b.idbai', '=', 'ep.idbai')
                ->leftJoin('public.municipio as m', 'm.idmun', '=', 'b.idmun')
                ->whereIn('ep.idpes', $faltantes)
                ->get(['ep.idpes', 'm.nome as cidade', 'm.sigla_uf as uf']);

            foreach ($legados as $endereco) {
                $enderecos[(int) $endereco->idpes] = [
                    'cidade' => (string) ($endereco->cidade ?? ''),
                    'uf' => (string) ($endereco->uf ?? ''),
                ];
            }
        } catch (\Throwable) {
        }

        return $enderecos;
    }

    /**
     * @return array{nome: string, cidade: string, uf: string, logradouro: string, numero: string, bairro: string, cep: string, responsavel: string, telefone: string}
     */
    private function instituicao(int $instituicaoId): array
    {
        $registro = DB::table('pmieducar.instituicao')
            ->where('cod_instituicao', $instituicaoId)
            ->first([
                'nm_instituicao',
                'cidade',
                'ref_sigla_uf',
                'logradouro',
                'numero',
                'bairro',
                'cep',
                'nm_responsavel',
                'ddd_telefone',
                'telefone',
            ]);

        if ($registro === null) {
            throw new \RuntimeException('Instituição não encontrada.');
        }

        return [
            'nome' => (string) $registro->nm_instituicao,
            'cidade' => (string) $registro->cidade,
            'uf' => trim((string) $registro->ref_sigla_uf),
            'logradouro' => (string) $registro->logradouro,
            'numero' => $registro->numero !== null ? (string) $registro->numero : '',
            'bairro' => (string) $registro->bairro,
            'cep' => $this->cep($registro->cep),
            'responsavel' => (string) $registro->nm_responsavel,
            'telefone' => $this->telefone($registro->ddd_telefone, $registro->telefone),
        ];
    }

    /**
     * @param  array{nome: string, cidade: string, uf: string, logradouro: string, numero: string, bairro: string, cep: string, responsavel: string, telefone: string}  $instituicao
     * @return array<string, string>
     */
    private function instituicaoPlanilha(array $instituicao, string $municipio): array
    {
        return [
            'razao_social' => $instituicao['nome'],
            'logradouro' => $instituicao['logradouro'],
            'numero' => $instituicao['numero'],
            'bairro' => $instituicao['bairro'],
            'cep' => $instituicao['cep'],
            'municipio' => $municipio,
            'uf' => $instituicao['uf'],
            'responsavel' => $instituicao['responsavel'],
            'telefone' => $instituicao['telefone'],
            'cargo' => 'Secretário(a)',
        ];
    }

    private function municipio(string $cidade, string $uf): string
    {
        $cidade = trim($cidade);
        $uf = strtoupper(trim($uf));

        if ($cidade === '') {
            return $uf;
        }

        $cidade = mb_strtoupper($cidade);

        return $uf !== '' ? $cidade . ', ' . $uf : $cidade;
    }

    private function rede(?int $dependencia): string
    {
        return match ($dependencia) {
            1 => 'FEDERAL',
            2 => 'ESTADUAL',
            4 => 'PRIVADA',
            default => 'MUNICIPAL',
        };
    }

    private function zona(?int $zona): string
    {
        return $zona === 2 ? 'Rural' : 'Urbana';
    }

    private function telefone(mixed $ddd, mixed $fone): string
    {
        $numero = preg_replace('/\D/', '', (string) $fone) ?? '';
        $ddd = preg_replace('/\D/', '', (string) $ddd) ?? '';

        if ($numero === '') {
            return '';
        }

        if (strlen($numero) === 9) {
            $numero = substr($numero, 0, 5) . '-' . substr($numero, 5);
        } elseif (strlen($numero) === 8) {
            $numero = substr($numero, 0, 4) . '-' . substr($numero, 4);
        }

        return $ddd !== '' ? '(' . $ddd . ') ' . $numero : $numero;
    }

    private function cep(mixed $cep): string
    {
        $digitos = preg_replace('/\D/', '', (string) $cep) ?? '';

        if (strlen($digitos) !== 8) {
            return $digitos;
        }

        return substr($digitos, 0, 5) . '-' . substr($digitos, 5);
    }
}
