<?php

namespace App\Http\Controllers;

// use App\Models\Escola;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use SimpleXMLElement;
use ZipArchive;

class ExportacaoXmlController extends Controller
{
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

        // Simulação de conteúdo da escola
        $escolas = Escola::with('turmas.serie', 'turmas.matriculas.aluno')->get();

        foreach ($escolas as $escola) {
            $xmlEscola = $xml->addChild('edu:escola', null, $xml->getNamespaces()['edu']);
            $xmlEscola->addChild('edu:idEscola', $escola->id_inep, $xml->getNamespaces()['edu']);

            foreach ($escola->turmas as $turma) {
                $xmlTurma = $xmlEscola->addChild('edu:turma', null, $xml->getNamespaces()['edu']);
                $xmlTurma->addChild('edu:periodo', $turma->periodo, $xml->getNamespaces()['edu']);
                $xmlTurma->addChild('edu:descricao', $turma->nome, $xml->getNamespaces()['edu']);
                $xmlTurma->addChild('edu:turno', $turma->turno, $xml->getNamespaces()['edu']);

                foreach ($turma->serie as $serie) {
                    $xmlSerie = $xmlTurma->addChild('edu:serie', null, $xml->getNamespaces()['edu']);
                    $xmlSerie->addChild('edu:idSerie', $serie->codigo, $xml->getNamespaces()['edu']);

                    foreach ($turma->matriculas as $matricula) {
                        $aluno = $matricula->aluno;
                        $xmlMatricula = $xmlSerie->addChild('edu:matricula', null, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:numero', $matricula->numero, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:data_matricula', $matricula->data_matricula, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:numero_faltas', $matricula->faltas ?? 0, $xml->getNamespaces()['edu']);
                        $xmlMatricula->addChild('edu:aprovado', $matricula->aprovado ? 'true' : 'false', $xml->getNamespaces()['edu']);

                        $xmlAluno = $xmlMatricula->addChild('edu:aluno', null, $xml->getNamespaces()['edu']);
                        if (!empty($aluno->cpf)) {
                            $xmlAluno->addChild('edu:cpfAluno', $aluno->cpf, $xml->getNamespaces()['edu']);
                        } else {
                            $xmlAluno->addChild('edu:justSemCpf', $aluno->justificativa_sem_cpf, $xml->getNamespaces()['edu']);
                        }
                        $xmlAluno->addChild('edu:data_nascimento', $aluno->data_nascimento, $xml->getNamespaces()['edu']);
                        $xmlAluno->addChild('edu:nome', $aluno->nome, $xml->getNamespaces()['edu']);
                        $xmlAluno->addChild('edu:pcd', $aluno->pcd ? '1' : '0', $xml->getNamespaces()['edu']);
                        $xmlAluno->addChild('edu:sexo', $aluno->sexo, $xml->getNamespaces()['edu']);
                    }
                }

                $xmlTurma->addChild('edu:multiseriada', $turma->multiseriada ? 'true' : 'false', $xml->getNamespaces()['edu']);
            }
        }

        return $this->compactarEEnviar($xml, 'modeloA');
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

    private function compactarEEnviar(SimpleXMLElement $xml, string $modelo)
    {
        $filenameBase = 'exportacoes/' . $modelo . '_' . now()->format('Ymd_His');
        $filenameXml = $filenameBase . '.xml';
        $filenameZip = $filenameBase . '.zip';

        Storage::put($filenameXml, $xml->asXML());

        $zip = new ZipArchive;
        $zipPath = storage_path('app/' . $filenameZip);
        $xmlPath = storage_path('app/' . $filenameXml);

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($xmlPath, basename($filenameXml));
            $zip->close();
        } else {
            return response()->json(['erro' => 'Erro ao criar ZIP.'], 500);
        }

        // Storage::delete($filenameXml); // se quiser remover o XML após zipar
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
