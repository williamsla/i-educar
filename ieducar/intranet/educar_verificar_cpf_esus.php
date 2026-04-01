<?php

use App\Http\Controllers\VerificarCpfEsusExportController;
use App\Process;
use App\Services\EsusPdfCpfService;
use Illuminate\Support\Facades\Gate;

return new class extends clsCadastro
{
    public $pessoa_logada;

    /** @var array|null Resultado da verificação (cpfs_extraidos, ano_letivo, cpfs_nao_cadastrados, erro?) */
    public $resultadoVerificacao;

    /** Ano letivo para checagem de matrícula (padrão: ano atual). */
    public $ano_letivo;

    public function Formular()
    {
        $this->title = 'Verificar CPFs - Relatório eSUS';
        $this->processoAp = Process::CONFIGURATIONS_TOOLS;
    }

    public function Inicializar()
    {
        $obj_permissoes = new clsPermissoes;

        if (! Gate::allows('view', Process::CONFIGURATIONS_TOOLS)) {
            $this->simpleRedirect(url: '/intranet/index.php');

            return false;
        }

        $this->breadcrumb(currentPage: 'Verificar CPFs - Relatório eSUS', breadcrumbs: [
            url(path: 'intranet/educar_configuracoes_index.php') => 'Configurações',
        ]);

        $this->url_cancelar = 'educar_configuracoes_index.php';
        $this->nome_url_cancelar = 'Voltar';

        return 'Verificar';
    }

    public function Processar()
    {
        $this->tipoacao = $_POST['tipoacao'] ?? null;

        if ($this->tipoacao === 'Verificar') {
            foreach ($_POST as $variavel => $valor) {
                if (property_exists($this, $variavel)) {
                    $this->$variavel = $valor;
                }
            }
            foreach ($_FILES as $variavel => $valor) {
                if (property_exists($this, $variavel)) {
                    $this->$variavel = $valor;
                }
            }
            $this->resultadoVerificacao = $this->executarVerificacao();
            $this->setFlashMessage();
            $this->Formular();

            return;
        }

        parent::Processar();
    }

    public function Gerar()
    {
        $this->form_enctype = ' enctype=\'multipart/form-data\'';
        $this->botao_enviar = false;

        $anoValor = ($this->ano_letivo !== null && $this->ano_letivo !== '') ? (string) $this->ano_letivo : (string) date('Y');
        $this->campoNumero(
            nome: 'ano_letivo',
            campo: 'Ano letivo',
            valor: $anoValor,
            tamanhovisivel: 6,
            tamanhomaximo: 4,
            obrigatorio: true,
            descricao: 'Ano em que a matrícula deve existir (ex.: '.date('Y').').'
        );

        $this->campoArquivo(
            nome: 'arquivo_pdf',
            campo: 'Arquivo PDF (relatório eSUS - Acompanhamento de cidadãos vinculados)',
            valor: '',
            tamanho: 50,
            descricao: 'Envie um PDF com CPFs no formato XXX.XXX.XXX-XX. Tamanho máximo: 20 MB.'
        );

        $this->array_botao[] = 'Verificar';
        $this->array_botao_url_script[] = "document.getElementById('tipoacao').value='Verificar'; document.getElementById('formcadastro').submit();";
        $this->array_botao_id[] = 'btn_verificar';

        if ($this->resultadoVerificacao !== null) {
            $this->exibirResultado();
        }
    }

    /**
     * Executa a extração de CPFs do PDF e a verificação no cadastro.
     */
    private function executarVerificacao(): array
    {
        $file = $_FILES['arquivo_pdf'] ?? null;

        if (! $file || empty($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
            $this->_mensagem = 'Selecione um arquivo PDF para enviar.';
            $this->sucesso = false;

            return [
                'cpfs_extraidos' => 0,
                'ano_letivo' => (int) ($this->ano_letivo ?? date('Y')),
                'cpfs_nao_cadastrados' => [],
                'erro' => 'Nenhum arquivo enviado.',
            ];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $this->_mensagem = 'O arquivo deve ser do tipo PDF.';
            $this->sucesso = false;

            return [
                'cpfs_extraidos' => 0,
                'ano_letivo' => (int) ($this->ano_letivo ?? date('Y')),
                'cpfs_nao_cadastrados' => [],
                'erro' => 'Formato inválido. Use apenas arquivos PDF.',
            ];
        }

        $maxSize = 20 * 1024 * 1024; // 20 MB
        if ($file['size'] > $maxSize) {
            $this->_mensagem = 'O arquivo não pode ter mais de 20 MB.';
            $this->sucesso = false;

            return [
                'cpfs_extraidos' => 0,
                'ano_letivo' => (int) ($this->ano_letivo ?? date('Y')),
                'cpfs_nao_cadastrados' => [],
                'erro' => 'Arquivo muito grande.',
            ];
        }

        $anoLetivo = (int) ($this->ano_letivo ?? date('Y'));
        $anoMax = (int) date('Y') + 2;
        if ($anoLetivo < 1990 || $anoLetivo > $anoMax) {
            VerificarCpfEsusExportController::limparExportacao();
            $this->_mensagem = "Informe um ano letivo válido (entre 1990 e {$anoMax}).";
            $this->sucesso = false;

            return [
                'cpfs_extraidos' => 0,
                'ano_letivo' => $anoLetivo,
                'cpfs_nao_cadastrados' => [],
                'erro' => 'Ano letivo inválido.',
            ];
        }

        $service = app(EsusPdfCpfService::class);
        $resultado = $service->processarPdf($file['tmp_name'], $anoLetivo);

        if (! empty($resultado['erro'])) {
            VerificarCpfEsusExportController::limparExportacao();
            $this->_mensagem = 'Erro ao processar o PDF: ' . $resultado['erro'];
            $this->sucesso = false;
        } else {
            $this->sucesso = true;
            $n = count($resultado['cpfs_nao_cadastrados']);
            $ano = (int) ($resultado['ano_letivo'] ?? $anoLetivo);
            if ($n === 0) {
                VerificarCpfEsusExportController::limparExportacao();
                $this->_mensagem = sprintf(
                    'Foram encontrados %d CPF(s) no PDF. Todos possuem matrícula ativa em %d.',
                    $resultado['cpfs_extraidos'],
                    $ano
                );
            } else {
                VerificarCpfEsusExportController::armazenarParaExportacao(
                    (int) $resultado['cpfs_extraidos'],
                    $ano,
                    $resultado['cpfs_nao_cadastrados']
                );
                $this->_mensagem = sprintf(
                    'Foram encontrados %d CPF(s) no PDF. %d não possuem matrícula ativa em %d. Veja o resumo e use Exportar relatório em PDF para a lista completa.',
                    $resultado['cpfs_extraidos'],
                    $n,
                    $ano
                );
            }
        }

        return $resultado;
    }

    /**
     * Resumo do resultado abaixo do botão Verificar (sem listar pessoas na tela).
     */
    private function exibirResultado(): void
    {
        $resultado = $this->resultadoVerificacao;

        if ($resultado === null) {
            return;
        }

        if (! empty($resultado['erro'])) {
            $this->addHtml('<tr><td class="formmdtd" colspan="2"><span class="form" style="color: #c00;"><strong>Resumo:</strong> ' . htmlspecialchars($resultado['erro']) . '</span></td></tr>');

            return;
        }

        $ano = (int) ($resultado['ano_letivo'] ?? date('Y'));
        $total = (int) ($resultado['cpfs_extraidos'] ?? 0);
        $n = count($resultado['cpfs_nao_cadastrados'] ?? []);

        if ($n === 0) {
            $texto = sprintf(
                '<strong>Resumo:</strong> %d CPF(s) lidos do PDF. Ano letivo <strong>%d</strong>. Todos possuem matrícula ativa neste ano.',
                $total,
                $ano
            );
        } else {
            $texto = sprintf(
                '<strong>Resumo:<br/>Ano letivo <strong>%d</strong>. <br/></strong> %d CPF(s) lidos do PDF. <br/><strong>%d</strong> sem matrícula ativa (aluno e matrícula ativos).',
                $ano,
                $total,                
                $n
            );
        }

        $html = '<tr><td class="formmdtd" colspan="2"><span class="form">' . $texto . '</span></td></tr>';

        if ($n > 0) {
            $exportUrl = url('/relatorios/verificar-cpf-esus/exportar');
            $html .= '<tr><td class="formmdtd" colspan="2"><span class="form"><a href="' . htmlspecialchars($exportUrl) . '" target="_blank" rel="noopener" class="decorated"><strong>Exportar relatório em PDF</strong></a> — abre somente CPF, nome e data de nascimento para imprimir ou salvar como PDF.</span></td></tr>';
        }

        $this->addHtml($html);
    }
};
