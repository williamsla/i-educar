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

class ExportacaoXmlController extends Controller
{
    const SITUACOES_APROVADO = [
        App_Model_MatriculaSituacao::APROVADO,
        App_Model_MatriculaSituacao::APROVADO_SEM_EXAME,
        App_Model_MatriculaSituacao::APROVADO_APOS_EXAME,
        App_Model_MatriculaSituacao::APROVADO_COM_DEPENDENCIA,
        App_Model_MatriculaSituacao::APROVADO_PELO_CONSELHO,
    ];

    public function index()
    {
        return view('exportar-xml');
    }

    
    public function exportar(Request $request)
    {
        $modelo = $request->input('modelo');
        $ano = $request->input('ano');
        $mes = $request->input('mes');

        if (!in_array($modelo, ['sagres', 'siap']) || !$ano || !$mes) {
            return back()->withErrors('Preencha todos os campos corretamente.');
        }

        if ($modelo === 'sagres') {
            return $this->exportarModeloSAGRES($ano, $mes);
        } else {
            return $this->exportarModeloSIAP($ano, $mes);
        }
    }

    private function exportarModeloSAGRES($ano, $mes)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><edu:educacao xmlns:edu="http://www.tce.se.gov.br/sagres2025/xml/sagresEdu"/>');

        $prestacao = $xml->addChild('edu:PrestacaoContas', null, $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:codigoUnidGestora', '009999', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:nomeUnidGestora', 'Prefeitura Municipal de Narnia', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:cpfResponsavel', '12345678900', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:cpfGestor', '12345678900', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:anoReferencia', $ano, $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:mesReferencia', $mes, $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:versaoXml', '0', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:diaInicPresContas', '1', $xml->getNamespaces()['edu']);
        $prestacao->addChild('edu:diaFinaPresContas', '31', $xml->getNamespaces()['edu']);

        $escolas = $this->getEscolas();
        if ($escolas->isEmpty()) {
            return response()->json(['erro' => 'Nenhuma escola encontrada.'], 404);
        } 

        foreach ($escolas as $escola) {
            $xmlEscola = $xml->addChild('edu:escola', null, $xml->getNamespaces()['edu']);
            $xmlEscola->addChild('edu:idEscola', $escola->inep_escola, $xml->getNamespaces()['edu']);

            $turmas = $this->getTurmas($ano, $escola->cod_escola);
          
            foreach ($turmas as $turma) {
                $turmaPeriodo = $this->getTurmaPeriodo($turma->cod_turma);

                $xmlTurma = $xmlEscola->addChild('edu:turma', null, $xml->getNamespaces()['edu']);
                $xmlTurma->addChild('edu:periodo', $turmaPeriodo->periodo, $xml->getNamespaces()['edu']);
                $xmlTurma->addChild('edu:descricao', $turma->nm_turma, $xml->getNamespaces()['edu']);
                $xmlTurma->addChild('edu:turno', $turma->turno, $xml->getNamespaces()['edu']);

                $series = $this->getSeries($turma->cod_turma);
                            
                foreach ($series as $serie) {
                    $xmlSerie = $xmlTurma->addChild('edu:serie', null, $xml->getNamespaces()['edu']);
                    $xmlSerie->addChild('edu:idSerie', $serie->idSerie, $xml->getNamespaces()['edu']);
                    
                    $matriculas = $this->getMatriculasPorTurmaESerie($turma->cod_turma, $serie->cod_serie);
                    foreach ($matriculas as $matricula) {
                        $xmlMatricula = $xmlSerie->addChild('edu:matricula', null, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:numero', $matricula->cod_matricula, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:data_matricula', $matricula->data_matricula, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:numero_faltas', $matricula->faltas ?? 0, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:aprovado', in_array($matricula->aprovado, self::SITUACOES_APROVADO) ? 'true' : 'false', $xml->getNamespaces()['edu']);
                        
                        $xmlAluno = $xmlMatricula->addChild('edu:aluno', null, $xml->getNamespaces()['edu']);
                        
                        if (!empty($aluno->cpf)) {
                            $xmlAluno->addChild('edu:cpfAluno', $this->getCpfNumbers($matricula->cpf), $xml->getNamespaces()['edu']);
                        }
                        $xmlAluno->addChild('edu:data_nascimento', $matricula->data_nascimento, $xml->getNamespaces()['edu']);
                        $xmlAluno->addChild('edu:nome', $matricula->nome, $xml->getNamespaces()['edu']);
                        $xmlAluno->addChild('edu:pcd', $matricula->pcd > 0 ? '1' : '0', $xml->getNamespaces()['edu']);
                        
                        $sexo_as_num = $matricula->sexo == 'M' ? 1 : ($matricula->sexo == 'F' ? 2 : 3);
                        $xmlAluno->addChild('edu:sexo', $sexo_as_num, $xml->getNamespaces()['edu']);
                        
                        if (empty($aluno->cpf)) {
                            $xmlAluno->addChild('edu:justSemCpf', 3, $xml->getNamespaces()['edu']);
                        }
                    }
                }
                
                $horarios = $this->getHorarios($turma->cod_turma);
                
                foreach ($horarios as $horario) {
                    var_dump($horario);
                    $xmlHorario = $xmlTurma->addChild('edu:horario', null, $xml->getNamespaces()['edu']);
                    
                    $xmlHorario->addChild('edu:dia_semana', $horario->dia_semana, $xml->getNamespaces()['edu']);
                    $xmlHorario->addChild('edu:duracao', $horario->duracao, $xml->getNamespaces()['edu']);
                    $xmlHorario->addChild('edu:hora_inicio', $horario->hora_inicial, $xml->getNamespaces()['edu']);
                    $xmlHorario->addChild('edu:disciplina', $horario->disciplina, $xml->getNamespaces()['edu']);
                    $xmlHorario->addChild('edu:cpfProfessor', $this->getCpfNumbers($horario->cpf_professor), $xml->getNamespaces()['edu']);                    
                }

                $xmlTurma->addChild('edu:multiseriada', $turma->multiseriada == 1 ? 'true' : 'false', $xml->getNamespaces()['edu']);
            }
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

    private function getEscolas()
    {
        return DB::table('escola')
                ->leftJoin('modules.educacenso_cod_escola', 'escola.cod_escola', '=', 'modules.educacenso_cod_escola.cod_escola')
                ->select(
                    'escola.cod_escola',
                    'escola.sigla',
                    'modules.educacenso_cod_escola.cod_escola_inep as inep_escola'
                )
                ->where('escola.ativo', '=', 1)
                ->where('modules.educacenso_cod_escola.cod_escola_inep', '!=', null)
                ->get();
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

    private function getSeries($cod_turma)
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

    private function getMatriculasPorTurmaESerie($cod_turma, $cod_serie)
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
            ->where('matricula.ativo', '=', 1)
            ->where('matricula_turma.ativo', '=', 1)
            ->where('aluno.ativo', '=', 1)
            ->where('fisica.ativo', '=', 1)
            ->get();
    }

    private function getHorarios($cod_turma)
    {
        return DB::table('quadro_horario_horarios as qhh')
                    ->join('quadro_horario as qh', 'qh.cod_quadro_horario', '=', 'qhh.ref_cod_quadro_horario')
                    ->join('modules.componente_curricular as cc', 'cc.id', '=', 'qhh.ref_cod_disciplina')
                    ->join('cadastro.pessoa as p', 'p.idpes', '=', 'qhh.ref_servidor')
                    ->join('cadastro.fisica as f', 'f.idpes', '=', 'p.idpes')
                    ->select([
                        'qhh.dia_semana',
                        'qhh.hora_inicial',
                        'cc.nome as disciplina',
                        DB::raw("public.formata_cpf(f.cpf) as cpf_professor"),
                        DB::raw('COUNT(*) as duracao')
                    ])
                    ->where('qh.ref_cod_turma', $cod_turma)
                    ->groupBy('qhh.dia_semana', 'qhh.hora_inicial', 'cc.nome', 'f.cpf')
                    ->orderBy('qhh.dia_semana')
                    ->orderBy('qhh.hora_inicial')
                    ->get();
    }

    
    private function compactarEEnviar(SimpleXMLElement $xml, string $modelo)
    {
        $filenameBase = 'exportacoes/' . $modelo;
        $filenameXml = $filenameBase . '.xml';
        $filenameZip = $filenameBase . '.zip';

        Storage::put($filenameXml, $xml->asXML());

        $zip = new ZipArchive;
        $zipPath = storage_path('app/' . $filenameZip);
        $xmlPath = storage_path('app/' . $filenameXml);

        var_dump($xmlPath);
        var_dump($filenameXml);
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($xmlPath, basename($filenameXml));
            $zip->close();
        } else {
            return response()->json(['erro' => 'Erro ao criar ZIP.'], 500);
        }

        // Storage::delete($filenameXml); // se quiser remover o XML após zipar
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    private function getCpfNumbers($cpf) {
        return preg_replace('/\D/', '', $cpf);
    }
}
