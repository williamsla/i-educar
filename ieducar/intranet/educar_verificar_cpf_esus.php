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

    /** Quando marcado, não inclui na lista quem veio só com CNS no arquivo (sem CPF). */
    public $esus_excluir_sem_cpf_somente_cns;

    /** Origem da importação: esus | cadastro_cidadao */
    public $tipo_fonte;

    public function Formular()
    {
        $this->title = 'Verificar CPFs - Relatório eSUS / Cadastro cidadão';
        $this->processoAp = Process::CONFIGURATIONS_TOOLS;
    }

    public function Inicializar()
    {
        $obj_permissoes = new clsPermissoes;

        if (! Gate::allows('view', Process::CONFIGURATIONS_TOOLS)) {
            $this->simpleRedirect(url: '/intranet/index.php');

            return false;
        }

        $this->breadcrumb(currentPage: 'Verificar CPFs - Relatório eSUS / Cadastro cidadão', breadcrumbs: [
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

        $tipoFonte = $this->normalizarTipoFonte($this->tipo_fonte ?? null);

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

        $this->campoLista(
            nome: 'tipo_fonte',
            campo: 'Origem da importação',
            valor: [
                'esus' => 'eSUS (PDF ou CSV)',
                'cadastro_cidadao' => 'Cadastro cidadão (XLSX)',
            ],
            default: $tipoFonte,
            acao: '',
            duplo: false,
            descricao: 'Escolha se o arquivo veio do relatório eSUS ou da Relação de Cadastro do Cidadão.',
            complemento: '',
            desabilitado: false,
            obrigatorio: true
        );

        if ($tipoFonte === 'cadastro_cidadao') {
            $this->campoArquivo(
                nome: 'arquivo_pdf',
                campo: 'Arquivo XLSX (Relação de Cadastro do Cidadão)',
                valor: '',
                tamanho: 50,
                descricao: 'Envie a planilha XLSX no modelo Relação de Cadastro do Cidadão (colunas CPF/CNS, Nome, Data de nascimento, etc.). Tamanho máximo: 20 MB.'
            );
        } else {
            $this->campoArquivo(
                nome: 'arquivo_pdf',
                campo: 'Arquivo PDF/CSV (relatório eSUS - Acompanhamento de cidadãos vinculados)',
                valor: '',
                tamanho: 50,
                descricao: 'Envie um arquivo PDF ou CSV com os dados do eSUS. Tamanho máximo: 20 MB.'
            );
        }

        $this->campoCheck(
            nome: 'esus_excluir_sem_cpf_somente_cns',
            campo: 'Excluir da análise registros sem CPF no arquivo (somente cartão CNS)',
            valor: $this->esus_excluir_sem_cpf_somente_cns ?? '',
            desc: 'Marcado: não entram na lista nem no PDF exportado as linhas em que só há CNS (sem CPF na planilha/PDF). Desmarcado: inclui todos os identificadores.'
        );

        $this->array_botao[] = 'Verificar';
        $this->array_botao_url_script[] = "window.verificarCpfEsusEnviar();";
        $this->array_botao_id[] = 'btn_verificar';

        $this->addHtml($this->htmlScriptAtualizarRotuloArquivoPorTipoFonte());
        $this->addHtml($this->htmlScriptLoadingProcessamento());

        if ($this->resultadoVerificacao !== null) {
            $this->exibirResultado();
        }
    }

    /**
     * Atualiza rótulo/descrição do arquivo ao trocar a origem (sem recarregar a página).
     */
    private function htmlScriptAtualizarRotuloArquivoPorTipoFonte(): string
    {
        $esusCampo = 'Arquivo PDF/CSV (relatório eSUS - Acompanhamento de cidadãos vinculados)';
        $esusDesc = 'Envie um arquivo PDF ou CSV com os dados do eSUS. Tamanho máximo: 20 MB.';
        $cidadaoCampo = 'Arquivo XLSX (Relação de Cadastro do Cidadão)';
        $cidadaoDesc = 'Envie a planilha XLSX no modelo Relação de Cadastro do Cidadão (colunas CPF/CNS, Nome, Data de nascimento, etc.). Tamanho máximo: 20 MB.';

        $js = <<<'JS'
<script type="text/javascript">
(function () {
  var select = document.getElementById('tipo_fonte');
  if (!select) { return; }
  var labels = {
    esus: { campo: %esusCampo%, desc: %esusDesc% },
    cadastro_cidadao: { campo: %cidadaoCampo%, desc: %cidadaoDesc% }
  };
  function aplicar() {
    var cfg = labels[select.value] || labels.esus;
    var fileInput = document.getElementById('arquivo_pdf');
    if (!fileInput) { return; }
    var row = fileInput.closest('tr');
    if (!row) { return; }
    var labelCell = row.querySelector('td.formlttd, td.formmdtd');
    if (labelCell) {
      var strong = labelCell.querySelector('span.form');
      if (strong) {
        strong.innerHTML = cfg.campo;
      } else {
        labelCell.textContent = cfg.campo;
      }
    }
    var hint = row.querySelector('span.form_desc, small, .form_descricao');
    if (hint) {
      hint.textContent = cfg.desc;
    } else {
      var valueCell = fileInput.closest('td');
      if (valueCell) {
        var existing = valueCell.querySelector('[data-tipo-fonte-desc]');
        if (!existing) {
          existing = document.createElement('div');
          existing.setAttribute('data-tipo-fonte-desc', '1');
          existing.style.marginTop = '4px';
          existing.style.fontSize = '11px';
          valueCell.appendChild(existing);
        }
        existing.textContent = cfg.desc;
      }
    }
  }
  select.addEventListener('change', aplicar);
  aplicar();
})();
</script>
JS;

        return strtr($js, [
            '%esusCampo%' => json_encode($esusCampo, JSON_UNESCAPED_UNICODE),
            '%esusDesc%' => json_encode($esusDesc, JSON_UNESCAPED_UNICODE),
            '%cidadaoCampo%' => json_encode($cidadaoCampo, JSON_UNESCAPED_UNICODE),
            '%cidadaoDesc%' => json_encode($cidadaoDesc, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Overlay de loading enquanto o servidor processa o arquivo enviado.
     */
    private function htmlScriptLoadingProcessamento(): string
    {
        return <<<'HTML'
<style type="text/css">
#verificar-cpf-esus-loading {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: none;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.55);
}
#verificar-cpf-esus-loading.is-visible {
  display: flex;
}
#verificar-cpf-esus-loading .box {
  background: #fff;
  padding: 28px 36px;
  border-radius: 8px;
  text-align: center;
  min-width: 260px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
  font-family: Arial, Helvetica, sans-serif;
}
#verificar-cpf-esus-loading .spinner {
  width: 36px;
  height: 36px;
  margin: 0 auto 14px;
  border: 3px solid #dde3ea;
  border-top-color: #2f6fed;
  border-radius: 50%;
  animation: verificar-cpf-esus-spin 0.8s linear infinite;
}
#verificar-cpf-esus-loading .titulo {
  font-size: 15px;
  font-weight: bold;
  color: #222;
  margin-bottom: 6px;
}
#verificar-cpf-esus-loading .subtitulo {
  font-size: 12px;
  color: #666;
  line-height: 1.35;
}
@keyframes verificar-cpf-esus-spin {
  to { transform: rotate(360deg); }
}
</style>
<div id="verificar-cpf-esus-loading" aria-live="polite" aria-busy="true" role="status">
  <div class="box">
    <div class="spinner" aria-hidden="true"></div>
    <div class="titulo">Processando arquivo…</div>
    <div class="subtitulo">Aguarde. Arquivos grandes podem levar alguns minutos.</div>
  </div>
</div>
<script type="text/javascript">
window.verificarCpfEsusEnviar = function () {
  var overlay = document.getElementById('verificar-cpf-esus-loading');
  var form = document.getElementById('formcadastro');
  var tipoacao = document.getElementById('tipoacao');
  var btn = document.getElementById('btn_verificar');
  if (!form || !tipoacao) {
    return;
  }
  if (overlay) {
    overlay.classList.add('is-visible');
  }
  if (btn) {
    btn.disabled = true;
    btn.style.opacity = '0.6';
    btn.style.cursor = 'wait';
  }
  tipoacao.value = 'Verificar';
  // Garante que o overlay pinte na tela antes do submit bloquear a UI.
  window.setTimeout(function () {
    form.submit();
  }, 50);
};
</script>
HTML;
    }

    /**
     * Executa a extração de CPFs do arquivo (PDF/CSV/XLSX) e a verificação no cadastro.
     */
    private function executarVerificacao(): array
    {
        // Planilhas grandes (milhares de linhas) + cruzamento com o banco ultrapassam o default de 30s.
        set_time_limit(seconds: 0);
        ini_set(option: 'memory_limit', value: '512M');

        $tipoFonte = $this->normalizarTipoFonte($this->tipo_fonte ?? null);
        $this->tipo_fonte = $tipoFonte;

        $file = $_FILES['arquivo_pdf'] ?? null;

        if (! $file || empty($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
            $this->_mensagem = $tipoFonte === 'cadastro_cidadao'
                ? 'Selecione um arquivo XLSX para enviar.'
                : 'Selecione um arquivo PDF ou CSV para enviar.';
            $this->sucesso = false;

            return [
                'cpfs_extraidos' => 0,
                'ano_letivo' => (int) ($this->ano_letivo ?? date('Y')),
                'cpfs_nao_cadastrados' => [],
                'erro' => 'Nenhum arquivo enviado.',
            ];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extensoesOk = $tipoFonte === 'cadastro_cidadao' ? ['xlsx'] : ['pdf', 'csv'];
        if (! in_array($ext, $extensoesOk, true)) {
            $this->_mensagem = $tipoFonte === 'cadastro_cidadao'
                ? 'Para Cadastro cidadão o arquivo deve ser do tipo XLSX.'
                : 'Para eSUS o arquivo deve ser do tipo PDF ou CSV.';
            $this->sucesso = false;

            return [
                'cpfs_extraidos' => 0,
                'ano_letivo' => (int) ($this->ano_letivo ?? date('Y')),
                'cpfs_nao_cadastrados' => [],
                'erro' => 'Formato inválido para a origem selecionada.',
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

        $excluirSomenteCnsSemCpf = ! empty($this->esus_excluir_sem_cpf_somente_cns);

        $service = app(EsusPdfCpfService::class);
        if ($tipoFonte === 'cadastro_cidadao') {
            $resultado = $service->processarXlsxCadastroCidadao($file['tmp_name'], $anoLetivo, $excluirSomenteCnsSemCpf);
        } elseif ($ext === 'csv') {
            $resultado = $service->processarCsv($file['tmp_name'], $anoLetivo, $excluirSomenteCnsSemCpf);
        } else {
            $resultado = $service->processarPdf($file['tmp_name'], $anoLetivo, $excluirSomenteCnsSemCpf);
        }

        if (! empty($resultado['erro'])) {
            VerificarCpfEsusExportController::limparExportacao();
            $this->_mensagem = 'Erro ao processar o arquivo: ' . $resultado['erro'];
            $this->sucesso = false;
        } else {
            $this->sucesso = true;
            $n = count($resultado['cpfs_nao_cadastrados']);
            $ano = (int) ($resultado['ano_letivo'] ?? $anoLetivo);
            if ($n === 0) {
                VerificarCpfEsusExportController::limparExportacao();
                $this->_mensagem = sprintf(
                    'Foram encontrados %d CPF(s) no arquivo. Todos possuem matrícula ativa em %d.',
                    $resultado['cpfs_extraidos'],
                    $ano
                );
            } else {
                VerificarCpfEsusExportController::armazenarParaExportacao(
                    (int) $resultado['cpfs_extraidos'],
                    $ano,
                    $resultado['cpfs_nao_cadastrados'],
                    (bool) ($resultado['excluir_sem_cpf_somente_cns'] ?? false)
                );
                $this->_mensagem = sprintf(
                    'Foram encontrados %d CPF(s) no arquivo. %d não possuem matrícula ativa em %d. Veja o resumo e use Exportar relatório em PDF para a lista completa.',
                    $resultado['cpfs_extraidos'],
                    $n,
                    $ano
                );
            }
        }

        return $resultado;
    }

    private function normalizarTipoFonte(mixed $valor): string
    {
        return $valor === 'cadastro_cidadao' ? 'cadastro_cidadao' : 'esus';
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
                '<strong>Resumo:</strong> %d CPF(s) lidos do arquivo. Ano letivo <strong>%d</strong>. Todos possuem matrícula ativa neste ano.',
                $total,
                $ano
            );
        } else {
            $texto = sprintf(
                '<strong>Resumo:<br/>Ano letivo <strong>%d</strong>. <br/></strong> %d CPF(s) lidos do arquivo. <br/><strong>%d</strong> sem matrícula ativa (aluno e matrícula ativos).',
                $ano,
                $total,
                $n
            );
        }

        $html = '<tr><td class="formmdtd" colspan="2"><span class="form">' . $texto . '</span></td></tr>';

        if ($n > 0) {
            $exportUrl = url('/relatorios/verificar-cpf-esus/exportar');
            $html .= '<tr><td class="formmdtd" colspan="2"><span class="form"><a href="' . htmlspecialchars($exportUrl) . '" target="_blank" rel="noopener" class="decorated"><strong>Exportar relatório em PDF</strong></a> — CPF/CNS, nome, nascimento, endereço (sem CEP), último atendimento de saúde, data da última matrícula ou transferência no cadastro escolar.</span></td></tr>';
        }

        $this->addHtml($html);
    }
};
