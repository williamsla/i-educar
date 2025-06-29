<?php

namespace App\Http\Controllers;
// namespace App\Http\Controllers\Api;

use App_Model_MatriculaSituacao;
use Illuminate\Support\Facades\DB;
use App\Models\LegacySchool;
use App\Models\LegacySchoolClass;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use SimpleXMLElement;
use ZipArchive;
use PDO;
use DateTime;

class ExportacaoXmlController extends Controller
{
    const SITUACOES_APROVADO = [
        App_Model_MatriculaSituacao::APROVADO,
        App_Model_MatriculaSituacao::APROVADO_SEM_EXAME,
        App_Model_MatriculaSituacao::APROVADO_APOS_EXAME,
        App_Model_MatriculaSituacao::APROVADO_COM_DEPENDENCIA,
        App_Model_MatriculaSituacao::APROVADO_PELO_CONSELHO,
    ];

    private $alerts = [];

    public function index()
    {
        return view('exportar-xml');
    }

    
    public function exportar(Request $request)
    {
        $modelo = $request->input('modelo');
        $ano = $request->input('ano');
        $mes = $request->input('mes');

        $this->alerts = [];

        if (!in_array($modelo, ['sagres', 'siap']) || !$ano || !$mes) {
            return back()->withErrors('Preencha todos os campos corretamente.');
        }

        if ($modelo === 'sagres') {
            $result = $this->exportarModeloSAGRES($ano, $mes);
        } else {
            $result = $this->exportarModeloSIAP($ano, $mes);
        }

        // if (!empty($this->alerts)) {
        //     $this->showAlert(implode('\n', $this->alerts));            
        // }

        return $result;
    }

    private function exportarModeloSAGRES($ano, $mes)
    {
        $data = new DateTime("$ano-$mes-01");
        $ultimo_dia_mes = $data->format('t');

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><edu:educacao xmlns:edu="http://www.tce.se.gov.br/sagres2025/xml/sagresEdu"/>');

        $prestacao = $xml->addChild('edu:PrestacaoContas', null, $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:codigoUnidGestora', '001301', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:nomeUnidGestora', 'Secretaria de Educação e Cultura', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:cpfResponsavel', '49902806520', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:cpfGestor', '00576785539', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:anoReferencia', $ano, $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:mesReferencia', $mes, $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:versaoXml', '0', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:diaInicPresContas', '1', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:diaFinaPresContas', $ultimo_dia_mes, $xml->getNamespaces()['edu']);

        $escolas = $this->getEscolas($ano);
        if ($escolas->isEmpty()) {
            return response()->json(['erro' => 'Nenhuma escola encontrada.'], 404);
        } 

        foreach ($escolas as $escola) {
            $this->alerts[] = 'ESCOLA: ' . $escola->sigla . ' - INEP: ' . $escola->inep_escola;
            
            $xmlEscola = $xml->addChild('edu:escola', null, $xml->getNamespaces()['edu']);
            $xmlEscola->addChild('edu:idEscola', $escola->inep_escola, $xml->getNamespaces()['edu']);

            $turmas = $this->getTurmas($ano, $escola->cod_escola);

            $mapCursoTurno = [];
          
            foreach ($turmas as $turma) {
                if ($this->existeMatriculasPorTurma($turma->cod_turma) === false) {
                    continue; // Se não houver matrículas, pula para a próxima turma
                }

                $turmaPeriodo = $this->getTurmaPeriodo($turma->cod_turma);

                $xmlTurma = $xmlEscola->addChild('edu:turma', null, $xml->getNamespaces()['edu']);
                $xmlTurma->addChild('edu:periodo', $turmaPeriodo->periodo, $xml->getNamespaces()['edu']);
                $xmlTurma->addChild('edu:descricao', $turma->nm_turma, $xml->getNamespaces()['edu']);
                $xmlTurma->addChild('edu:turno', $turma->turno, $xml->getNamespaces()['edu']);

                $series = $turma->multiseriada == 1 ? $this->getSeriesTurmaMulti($turma->cod_turma) : $this->getSeriesTurmaNormal($turma->cod_turma);
                            
                foreach ($series as $serie) {
                    if ($this->existeMatriculasPorTurmaESerie($turma->cod_turma, $serie->cod_serie, $ano, $mes) === false) {
                        continue; // Se não houver matrículas, pula para a próxima série
                    }
                    $xmlSerie = $xmlTurma->addChild('edu:serie', null, $xml->getNamespaces()['edu']);
                    $xmlSerie->addChild('edu:idSerie', $serie->idSerie, $xml->getNamespaces()['edu']);
                    
                    $curso_sigla = $this->getCursoSigla($serie->idSerie);
                    $this->adicionarUnico($mapCursoTurno, $turma->turno, $curso_sigla);
                    
                    $matriculas = $this->getMatriculasPorTurmaESerie($turma->cod_turma, $serie->cod_serie, $ano, $mes);
                    foreach ($matriculas as $matricula) {
                        $xmlMatricula = $xmlSerie->addChild('edu:matricula', null, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:numero', $matricula->cod_matricula, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:data_matricula', $matricula->data_matricula, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:numero_faltas', $matricula->faltas ?? 0, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:aprovado', in_array($matricula->aprovado, self::SITUACOES_APROVADO) ? 'true' : 'false', $xml->getNamespaces()['edu']);
                        
                        $xmlAluno = $xmlMatricula->addChild('edu:aluno', null, $xml->getNamespaces()['edu']);
                        
                        if (!empty($matricula->cpf)) {
                            $xmlAluno->addChild('edu:cpfAluno', $this->getCpfNumbers($matricula->cpf), $xml->getNamespaces()['edu']);
                        }
                        
                        if (!empty($matricula->data_nascimento)) {
                            $xmlAluno->addChild('edu:data_nascimento', $matricula->data_nascimento, $xml->getNamespaces()['edu']);
                        } else {
                            $this->alerts[] = '     - Aluno ' . $matricula->nome . ' não possui data de nascimento cadastrada.';
                        }

                        $xmlAluno->addChild('edu:nome', $matricula->nome, $xml->getNamespaces()['edu']);
                        $xmlAluno->addChild('edu:pcd', $matricula->pcd > 0 ? '1' : '0', $xml->getNamespaces()['edu']);
                        
                        $sexo_as_num = $matricula->sexo == 'M' ? 1 : ($matricula->sexo == 'F' ? 2 : 3);
                        $xmlAluno->addChild('edu:sexo', $sexo_as_num, $xml->getNamespaces()['edu']);
                        
                        if (empty($matricula->cpf)) {
                            $xmlAluno->addChild('edu:justSemCpf', 3, $xml->getNamespaces()['edu']);
                        }
                    }
                }

                $horarios = $this->getHorarios($turma->cod_turma);
                if ($horarios->isEmpty()) {
                    $horarios = $this->getHorariosExterno($turma->cod_turma);
                    
                    if ($horarios->isEmpty()) {
                        $this->alerts[] = '     - Nenhum horário encontrado para a turma ' . $turma->nm_turma;
                        $horarios = $this->getHorarioAleatorioDaTurmaAux($turma->cod_turma); //distribuindo um horario aleatoriamente para o professor
                        $horarios = $horarios ? reset($horarios) : [];
                    }
                }
                
                foreach ($horarios as $horario) {
                    if ($horario->duracao < 1) {
                        // $this->alerts[] = '     - Duração do horário muito pequeno para a turma ' . $turma->nm_turma . ' no dia ' . $horario->dia_semana;
                        continue;
                    }
                    $xmlHorario = $xmlTurma->addChild('edu:horario', null, $xml->getNamespaces()['edu']);
                    
                    $xmlHorario->addChild('edu:dia_semana', $horario->dia_semana, $xml->getNamespaces()['edu']);
                    $xmlHorario->addChild('edu:duracao', $horario->duracao, $xml->getNamespaces()['edu']);
                    $xmlHorario->addChild('edu:hora_inicio', $horario->hora_inicial, $xml->getNamespaces()['edu']);
                    $xmlHorario->addChild('edu:disciplina', $horario->disciplina, $xml->getNamespaces()['edu']);
                    $xmlHorario->addChild('edu:cpfProfessor', $this->getCpfNumbers($horario->cpf_professor), $xml->getNamespaces()['edu']);
                }

                $xmlTurma->addChild('edu:multiseriada', $turma->multiseriada == 1 ? 'true' : 'false', $xml->getNamespaces()['edu']);
            } // fim do bloco turma

            $diretor = $this->getDiretor($escola->inep_escola);
            if ($diretor === null || !isset($diretor->cpf)) {
                $this->alerts[] = 'Diretor da escola INEP ' . $escola->inep_escola . ' não possui CPF cadastrado.';
            } else {
                $xmlDiretor = $xmlEscola->addChild('edu:diretor', null, $xml->getNamespaces()['edu']);
                $xmlDiretor->addChild('edu:cpfDiretor', $this->getCpfNumbers($diretor->cpf), $xml->getNamespaces()['edu']);
                $xmlDiretor->addChild('edu:nrAto', 00, $xml->getNamespaces()['edu']);
            }
          
            foreach ($mapCursoTurno as $turno => $valores) {
                
                foreach ($valores as $valor) {
                    $cardapios = $this->getCardapios($escola->inep_escola, $valor, $turno);
                    
                    foreach ($cardapios as $c) {
                        $xmlCardapio = $xmlEscola->addChild('edu:cardapio', null, $xml->getNamespaces()['edu']);
                        $xmlCardapio->addChild('edu:data', $c['data'], $xml->getNamespaces()['edu']);
                        $xmlCardapio->addChild('edu:turno', $c['turno'], $xml->getNamespaces()['edu']);
                        $xmlCardapio->addChild('edu:descricao_merenda', $c['descricao'], $xml->getNamespaces()['edu']);
                        $xmlCardapio->addChild('edu:ajustado', 0, $xml->getNamespaces()['edu']);
                    }
                }
            }
            
        } //fim do bloco escola

        $servidores = $this->getServidores($ano);
        foreach ($servidores as $serv) {
            if ($serv->funcao == 'Diretor') {
                continue; // Diretor já foi adicionado anteriormente em campo específico
            }

            $xmlProfissional = $xml->addChild('edu:profissional', null, $xml->getNamespaces()['edu']);
            if (!isset($serv->cpf)) {
                $this->alerts[] = '     - Servidor na função ' . $serv->funcao . ' não possui CPF cadastrado.';
            } else {
                $xmlProfissional->addChild('edu:cpfProfissional', $this->getCpfNumbers($serv->cpf), $xml->getNamespaces()['edu']);
            }
            
            $xmlProfissional->addChild('edu:especialidade', $serv->funcao, $xml->getNamespaces()['edu']);
            $xmlProfissional->addChild('edu:idEscola', $serv->inep_escola, $xml->getNamespaces()['edu']);
            $xmlProfissional->addChild('edu:fundeb', 1, $xml->getNamespaces()['edu']);
        }
        
        return $this->compactarEEnviar($xml, 'Educacao');
    }

    private function exportarModeloSIAP($ano, $mes)
    {
        // Exemplo: XML mais simples
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><relatorio/>');

        foreach (Escola::with('turmas')->get() as $escola) {
            $xmlEscola = $xml->addChild('escola');
            $xmlEscola->addChild('nome', $escola->nome);
            foreach ($escola->turmas as $turma) {
                $xmlTurma = $xmlEscola->addChild('turma');
                $xmlTurma->addChild('nome', $turma->nome);
            }
        }

        return $this->compactarEEnviar($xml, 'modeloB');
    }

    private function getCursoSigla($sigla_serie){
        if (str_contains($sigla_serie, "FUN")) {
            return 'FUN';
        } elseif (str_contains($sigla_serie, 'EJA')) {
            return 'EJA';
        } else {
            return $sigla_serie;
        }
    }

    function adicionarUnico(&$mapa, $chave, $valor) {
        if (!isset($mapa[$chave])) {
            $mapa[$chave] = [];
        }

        if (!in_array($valor, $mapa[$chave], true)) {
            $mapa[$chave][] = $valor;
        }
    }


    private function getEscolas($ano)
    {
        $query = DB::table('escola')
                ->join('pmieducar.escola_ano_letivo', 'escola.cod_escola', '=', 'pmieducar.escola_ano_letivo.ref_cod_escola')
                ->leftJoin('modules.educacenso_cod_escola', 'escola.cod_escola', '=', 'modules.educacenso_cod_escola.cod_escola')
                ->select(
                    'escola.cod_escola',
                    'escola.sigla',
                    'modules.educacenso_cod_escola.cod_escola_inep as inep_escola'
                )
                ->where('escola.ativo', '=', '1')
                ->where('pmieducar.escola_ano_letivo.ativo', '=', '1')
                ->where('pmieducar.escola_ano_letivo.ano', '=', $ano);
                
        return $query->get();
    }

    private function getTurmas($ano, $codEscola)
    {
        return DB::table('pmieducar.turma')
            ->select(
                'turma.cod_turma',
                'turma.nm_turma',
                'turma.turma_turno_id AS turno',
                'turma.ano',
                'turma.multiseriada'
            )
            ->where('turma.ano', '=', $ano)
            ->where('turma.ref_ref_cod_escola', '=', $codEscola)
            ->where('turma.ativo', '=', 1)
            ->get();
    }

    /** 
     * Retorna:
     * 0 → Anual (> 8 meses)
     * 1 → Semestral (1º semestre)
     * 2 → Semestral (2º semestre)
    */
    public function getTurmaPeriodo($cod_turma)
    {
        return DB::table('pmieducar.turma as t')
                ->join('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
                ->leftJoin('pmieducar.turma_modulo as tm', 'tm.ref_cod_turma', '=', 't.cod_turma')
                ->leftJoin('pmieducar.ano_letivo_modulo as alm', function ($join) {
                    $join->on('alm.ref_ano', '=', 't.ano')
                        ->on('alm.ref_ref_cod_escola', '=', 't.ref_ref_cod_escola');
                })
                ->where('t.cod_turma', $cod_turma)
                ->select(DB::raw("
                    CASE
                        WHEN (
                            DATE_PART('month', AGE(
                                MAX(CASE WHEN c.padrao_ano_escolar = 0 THEN tm.data_fim ELSE alm.data_fim END),
                                MIN(CASE WHEN c.padrao_ano_escolar = 0 THEN tm.data_inicio ELSE alm.data_inicio END)
                            )) + DATE_PART('year', AGE(
                                MAX(CASE WHEN c.padrao_ano_escolar = 0 THEN tm.data_fim ELSE alm.data_fim END),
                                MIN(CASE WHEN c.padrao_ano_escolar = 0 THEN tm.data_inicio ELSE alm.data_inicio END)
                            )) * 12
                        ) > 8 THEN 0

                        WHEN DATE_PART('month', MIN(
                            CASE WHEN c.padrao_ano_escolar = 0 THEN tm.data_inicio ELSE alm.data_inicio END
                        )) BETWEEN 1 AND 6 THEN 1

                        ELSE 2
                    END as periodo
                "))
                ->first();
    }

    private function getSeriesTurmaMulti($cod_turma)
    {
        return DB::table('pmieducar.serie')
            ->join('turma_serie', 'turma_serie.serie_id', '=', 'serie.cod_serie')
            ->join('turma', 'turma.cod_turma', '=', 'turma_serie.turma_id')
            ->select(
                'serie.cod_serie',
                'serie.nm_serie',
                'serie.descricao as idSerie',
                'turma_serie.turma_id'
            )
            ->where('turma_serie.turma_id', '=', $cod_turma)
            ->where('turma.ativo', '=', 1)
            ->where('serie.ativo', '=', 1)
            ->get();
    }

    private function getSeriesTurmaNormal($cod_turma)
    {
        return DB::table('pmieducar.serie')
            ->join('turma', 'turma.ref_ref_cod_serie', '=', 'serie.cod_serie')
            ->select(
                'serie.cod_serie',
                'serie.nm_serie',
                'serie.descricao as idSerie'
            )
            ->where('turma.cod_turma', '=', $cod_turma)
            ->where('turma.ativo', '=', 1)
            ->where('serie.ativo', '=', 1)
            ->get();
    }

    private function existeMatriculasPorTurma($cod_turma)
    {
        return DB::table('pmieducar.matricula')
            ->join('pmieducar.matricula_turma', 'matricula_turma.ref_cod_matricula', '=', 'matricula.cod_matricula')
            ->join('pmieducar.aluno', 'aluno.cod_aluno', '=', 'matricula.ref_cod_aluno')
            ->join('cadastro.pessoa', 'pessoa.idpes', '=', 'aluno.ref_idpes')
            ->join('cadastro.fisica', 'fisica.idpes', '=', 'aluno.ref_idpes')
            ->select(
                'matricula.cod_matricula'
            )
            ->where('matricula_turma.ref_cod_turma', '=', $cod_turma)
            ->where('matricula.ativo', '=', 1)
            ->where('matricula_turma.ativo', '=', 1)
            ->where('aluno.ativo', '=', 1)
            ->where('fisica.ativo', '=', 1)
            ->exists();
    }

    private function queryMatriculasPorTurmaESerie($cod_turma, $cod_serie, $ano, $mes)
    {
        return DB::table('pmieducar.matricula')
            ->join('pmieducar.matricula_turma', 'matricula_turma.ref_cod_matricula', '=', 'matricula.cod_matricula')
            ->join('pmieducar.aluno', 'aluno.cod_aluno', '=', 'matricula.ref_cod_aluno')
            ->join('cadastro.pessoa', 'pessoa.idpes', '=', 'aluno.ref_idpes')
            ->join('cadastro.fisica', 'fisica.idpes', '=', 'aluno.ref_idpes')
            ->select(
                'matricula.cod_matricula',
                'matricula.ref_cod_aluno',
                DB::raw('matricula.data_matricula::date AS data_matricula'),
                DB::raw('relatorio.get_total_faltas(matricula.cod_matricula) as faltas'),
                'matricula.aprovado',
                'pessoa.nome',
                DB::raw('public.formata_cpf(fisica.cpf) AS cpf'),
                DB::raw('fisica.data_nasc::date AS data_nascimento'),
                'fisica.sexo'     
            )
            ->selectSub(function ($query) {
                $query->from('cadastro.fisica_deficiencia')
                    ->selectRaw('count(fisica_deficiencia.ref_cod_deficiencia)')
                    ->whereColumn('fisica_deficiencia.ref_idpes', 'fisica.idpes')
                    ->limit(1);
            }, 'pcd')
            ->where('matricula_turma.ref_cod_turma', '=', $cod_turma)
            ->where('matricula.ref_ref_cod_serie', '=', $cod_serie)
            ->whereRaw(
                    "matricula.data_matricula <= (DATE_TRUNC('month', make_date(?, ?, 1)) + INTERVAL '1 month - 1 day')::date",
                    [$ano, $mes]
            )
            ->where('matricula.ativo', '=', 1)
            ->where('matricula_turma.ativo', '=', 1)
            ->where('aluno.ativo', '=', 1)
            ->where('fisica.ativo', '=', 1);
    }

    private function existeMatriculasPorTurmaESerie($cod_turma, $cod_serie, $ano, $mes)
    {
        $query = $this->queryMatriculasPorTurmaESerie($cod_turma, $cod_serie, $ano, $mes);

        return $query->exists();
    }

    private function getMatriculasPorTurmaESerie($cod_turma, $cod_serie, $ano, $mes)
    {
        $query = $this->queryMatriculasPorTurmaESerie($cod_turma, $cod_serie, $ano, $mes);

        return $query->get();
    }

    private function getHorarios($cod_turma)
    {
        $query = DB::table('quadro_horario_horarios as qhh')
                    ->join('quadro_horario as qh', 'qh.cod_quadro_horario', '=', 'qhh.ref_cod_quadro_horario')
                    ->join('modules.componente_curricular as cc', 'cc.id', '=', 'qhh.ref_cod_disciplina')
                    ->join('cadastro.pessoa as p', 'p.idpes', '=', 'qhh.ref_servidor')
                    ->join('cadastro.fisica as f', 'f.idpes', '=', 'p.idpes')
                    ->select([
                        'qhh.dia_semana',
                        'qhh.hora_inicial',
                        'cc.nome as disciplina',
                        DB::raw("public.formata_cpf(f.cpf) as cpf_professor"),
                        DB::raw('ROUND(EXTRACT(EPOCH from (qhh.hora_final - qhh.hora_inicial))/3600) as duracao')
                    ])
                    ->where('qh.ref_cod_turma', $cod_turma)
                    ->where('qh.ativo', '1')
                    ->where('qhh.ativo', '1')
                    ->orderBy('qhh.dia_semana')
                    ->orderBy('qhh.hora_inicial');
        
        $result = $query->get();
        
        // i-educar considera domingo como 1 e o sagres considera domingo como 7
        $diaSemanaMap = [
            '1'    => 7,
            '2'   => 1,
            '3' => 2,
            '4'  => 3,
            '5'    => 4,
            '6'  => 5,
            '7'    => 6
        ];

        $dadosFormatados = [];

        foreach ($result as $item) {
            $novo = new \stdClass();
            
            // Copiar campos existentes que deseja manter
            $novo->duracao  = $item->duracao ?? null;
            $novo->disciplina  = $item->disciplina ?? null;
            $novo->hora_inicial  = $item->hora_inicial ?? null;
            
            $novo->dia_semana = $diaSemanaMap[$item->dia_semana] ?? null;

            $novo->cpf_professor = $this->getCpfNumbers($item->cpf_professor) ?? null;

            $dadosFormatados[] = $novo;
        }

        return collect($dadosFormatados);
    }

    private function getHorariosExterno($cod_turma) {
        $pdo = new PDO("pgsql:host=diario.caninde.ensino.site;port=2345;dbname=idiario;sslmode=require", 'idiario', 'GSVvE18C1g18e6');

        $stmt = $pdo->prepare("SELECT lblw.weekday as dia_semana_nome, lbl.lesson_number as aula, d.description as disciplina, u.cpf as cpf_professor , 1 as duracao, lb.period as turno
                                FROM public.lessons_board_lesson_weekdays lblw
                                inner join public.lessons_board_lessons lbl on lbl.id = lblw.lessons_board_lesson_id
                                inner join  public.lessons_boards AS lb on lb.id = lbl.lessons_board_id
                                inner join public.classrooms_grades cg on cg.id = lb.classrooms_grade_id
                                inner join public.classrooms c on c.id = cg.classroom_id
                                inner join public.teacher_discipline_classrooms tdc on tdc.id = lblw.teacher_discipline_classroom_id
                                inner join users u on u.assumed_teacher_id = tdc.teacher_id
                                inner join disciplines d  on d.id = tdc.discipline_id
                                WHERE c.api_code = ?");
        
        $stmt->execute([(string) $cod_turma]);

        $result = $stmt->fetchAll(PDO::FETCH_OBJ);

        $diaSemanaMap = [
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
            'sunday'    => 7,
        ];
        $aulaManhaMap = [
            '1'    => '07:00:00',
            '2'   => '07:50:00',
            '3' => '08:40:00',
            '4'  => '09:50:00',
            '5'    => '10:40:00',
        ];

        $aulaTardeMap = [
            '1'    => '13:00:00',
            '2'   => '13:50:00',
            '3' => '14:40:00',
            '4'  => '15:40:00',
            '5'    => '16:30:00',
        ];

        $dadosFormatados = [];

        foreach ($result as $item) {
            $novo = new \stdClass();
            
            // Copiar campos existentes que deseja manter
            $novo->dia_semana_nome = $item->dia_semana_nome ?? null;
            $novo->turno          = $item->turno ?? null;
            $novo->aula           = $item->aula ?? null;
            $novo->cpf_professor  = isset($item->cpf_professor) ? $this->getCpfNumbers($item->cpf_professor) : null;
            $novo->duracao  = $item->duracao ?? null;
            $novo->disciplina  = $item->disciplina ?? null;

            
            $novo->dia_semana = $diaSemanaMap[$item->dia_semana_nome] ?? null;

            // Atributo novo: hora_inicio
            if ($item->turno == 1) {
                $novo->hora_inicial = $aulaManhaMap[(string) $item->aula] ?? null;
            } elseif ($item->turno == 2) {
                $novo->hora_inicial = $aulaTardeMap[(string) $item->aula] ?? null;
            } else {
                $novo->hora_inicial = null;
            }

            $novo->cpf_professor = $this->getCpfNumbers($item->cpf_professor);

            $dadosFormatados[] = $novo;
        }

        return collect($dadosFormatados);

    }

    private function getHorarioAleatorioDaTurmaAux($cod_turma){
        $query = DB::table('modules.professor_turma_disciplina AS ptd')
                    ->join('modules.professor_turma as pt', 'pt.id', '=', 'ptd.professor_turma_id')
                    ->join('pmieducar.servidor as s', 's.cod_servidor', '=', 'pt.servidor_id')
                    ->join('modules.componente_curricular as cc', 'cc.id', '=', 'ptd.componente_curricular_id')
                    ->join('cadastro.fisica as f', 'f.idpes', '=', 's.cod_servidor')
                    ->select([
                        DB::raw('1 as dia_semana'),
                        DB::raw("'07:30:00' as hora_inicial"),
                        'cc.nome as disciplina',
                        DB::raw("public.formata_cpf(f.cpf) as cpf_professor"),
                        DB::raw('1 as duracao')
                    ])
                    ->where('pt.turma_id', $cod_turma)
                    ->where('s.ativo', '1');
        
        return $query->get();
    }

    private function getDiretor($inep_escola)
    {
        return DB::table('pmieducar.escola')
            ->join('pmieducar.servidor', 'pmieducar.servidor.cod_servidor', '=', 'pmieducar.escola.ref_idpes_gestor')
            ->join('cadastro.pessoa', 'cadastro.pessoa.idpes', '=', 'pmieducar.servidor.cod_servidor')
            ->join('cadastro.fisica', 'cadastro.fisica.idpes', '=', 'cadastro.pessoa.idpes')
            ->join('modules.educacenso_cod_escola', 'modules.educacenso_cod_escola.cod_escola', '=', 'pmieducar.escola.cod_escola')
            ->select(DB::raw('public.formata_cpf(cadastro.fisica.cpf) as cpf'), 'cadastro.pessoa.nome')
            ->where('modules.educacenso_cod_escola.cod_escola_inep', $inep_escola)
            ->where('pmieducar.servidor.ativo', '=', 1)
            ->where('pmieducar.escola.ativo', '=', 1)          
            ->first();
    }    

    private function getCardapios($inep_escola, $curso_sigla, $turno) {
        $path = storage_path('app/cardapios.csv');

        $cardapios = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ','); // Lê o cabeçalho
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $cardapios[] = array_combine($header, $data);
            }
            fclose($handle);
        }

        $filter_escola = array_filter($cardapios, function($cardapio) use ($inep_escola) {
            return $cardapio['inep'] == $inep_escola;
        });


        if (!empty($filter_escola)) {
            $cardapios = $filter_escola;
        }
        $cardapios = array_filter($cardapios, function($cardapio) use ($curso_sigla, $turno) {
            return $cardapio['curso'] == $curso_sigla && $cardapio['turno'] == $turno;
        });
        
        return $cardapios;
    }

    private function getServidores($ano)
    {        
        return DB::table('pmieducar.servidor')
            ->join('pmieducar.servidor_alocacao', 'pmieducar.servidor_alocacao.ref_cod_servidor', '=', 'pmieducar.servidor.cod_servidor')
            ->join('pmieducar.servidor_funcao', 'pmieducar.servidor_funcao.cod_servidor_funcao', '=', 'pmieducar.servidor_alocacao.ref_cod_servidor_funcao')
            ->join('pmieducar.funcao', 'pmieducar.funcao.cod_funcao', '=', 'pmieducar.servidor_funcao.ref_cod_funcao')
            ->join('cadastro.pessoa', 'cadastro.pessoa.idpes', '=', 'pmieducar.servidor.cod_servidor')
            ->join('cadastro.fisica', 'cadastro.fisica.idpes', '=', 'cadastro.pessoa.idpes')
            ->join('pmieducar.escola_ano_letivo', 'pmieducar.escola_ano_letivo.ref_cod_escola', '=', 'pmieducar.servidor_alocacao.ref_cod_escola')
            ->join('modules.educacenso_cod_escola', 'modules.educacenso_cod_escola.cod_escola', '=', 'pmieducar.servidor_alocacao.ref_cod_escola')
            ->select(
                'pmieducar.servidor.cod_servidor',
                DB::raw('public.formata_cpf(cadastro.fisica.cpf) as cpf'),
                'cadastro.pessoa.nome',
                'pmieducar.funcao.nm_funcao as funcao',
                'modules.educacenso_cod_escola.cod_escola_inep as inep_escola'
            )
            ->where('pmieducar.servidor.ativo', '=', 1)
            ->where('pmieducar.escola_ano_letivo.andamento', '=', 1)
            ->where('pmieducar.servidor_alocacao.ativo', '=', 1)
            ->where('pmieducar.servidor_alocacao.ano', '=', $ano)
            ->where('pmieducar.funcao.professor', '=', 0)
            ->get();
    }

    private function compactarEEnviar(SimpleXMLElement $xml, string $modelo)
    {
        $filenameBase = 'exportacoes/' . $modelo;
        $filenameXml = $filenameBase . '.xml';
        $filenameZip = $filenameBase . '.zip';
        $filenameTxt = $filenameBase . '.txt';

        // Transformar para DOMDocument para aplicar indentação
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Carrega o XML do SimpleXMLElement no DOMDocument
        $dom->loadXML($xml->asXML());

        // Salva no disco 'public'
        Storage::disk('public')->put($filenameXml, $dom->saveXML());

        $zip = new ZipArchive;
        $zipPath = Storage::disk('public')->path($filenameZip);
        $xmlPath = Storage::disk('public')->path($filenameXml);

        if (!file_exists($xmlPath)) {
            return response()->json(['erro' => 'Arquivo XML não encontrado em ' . $xmlPath], 500);
        }

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($xmlPath, basename($filenameXml));
            $zip->close();
        } else {
            return response()->json(['erro' => 'Erro ao criar ZIP.'], 500);
        }
        
        // Salva o TXT com avisos
        Storage::disk('public')->put($filenameTxt, implode(PHP_EOL, $this->alerts));
        
        $zipUrl = Storage::disk('public')->url($filenameZip);
        $txtUrl = Storage::disk('public')->url($filenameTxt);

        // Retorna uma view com os links
        return view('exportar-xml-result', [
            'zipUrl' => asset($zipUrl),
            'txtUrl' => asset($txtUrl),
        ]);
    }

    private function getCpfNumbers($cpf) {
        return preg_replace('/\D/', '', $cpf);
    }

    function showAlert($mensagem) {
        echo "<script>alert('".addslashes($mensagem)."');</script>";
    }

}
