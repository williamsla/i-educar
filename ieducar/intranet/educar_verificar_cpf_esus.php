<?php

use App\Http\Controllers\VerificarCpfEsusExportController;
use App\Jobs\VerificarCpfEsusProcessJob;
use App\Process;
use App\Services\EsusPdfCpfService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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
        // Intercepta AJAX antes do MakeAll (menu/HTML), quando o LegacyController chama Formular().
        $this->processarAjaxSeNecessario();

        $this->title = 'Verificar CPFs - Relatório eSUS / Cadastro cidadão';
        $this->processoAp = Process::CONFIGURATIONS_TOOLS;
    }

    public function Inicializar()
    {
        $this->processarAjaxSeNecessario();

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

        $this->processarAjaxSeNecessario();

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

    private function processarAjaxSeNecessario(): void
    {
        $ajax = (string) (
            request()->input('ajax')
            ?? $_GET['ajax']
            ?? $_POST['ajax']
            ?? ''
        );
        $tipoacao = (string) ($_POST['tipoacao'] ?? request()->input('tipoacao') ?? '');

        if ($ajax === 'status') {
            $this->responderAjaxStatus();
        }

        if ($ajax === 'enfileirar' || $tipoacao === 'EnfileirarAsync') {
            $this->responderAjaxEnfileirar();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function responderJson(array $data, int $status = 200): never
    {
        throw new HttpResponseException(response()->json($data, $status));
    }

    private function responderAjaxEnfileirar(): never
    {
        if (! Gate::allows('view', Process::CONFIGURATIONS_TOOLS)) {
            $this->responderJson(['message' => 'Sem permissão.'], 403);
        }

        $tipoFonte = $this->normalizarTipoFonte($_POST['tipo_fonte'] ?? null);
        $extensoesOk = $tipoFonte === 'cadastro_cidadao' ? ['xlsx'] : ['pdf', 'csv'];
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? date('Y'));
        $anoMax = (int) date('Y') + 2;
        if ($anoLetivo < 1990 || $anoLetivo > $anoMax) {
            $this->responderJson(['message' => "Informe um ano letivo válido (entre 1990 e {$anoMax})."], 422);
        }

        $file = $_FILES['arquivo_pdf'] ?? null;
        if (! $file || empty($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
            $this->responderJson(['message' => 'Selecione um arquivo para enviar.'], 422);
        }

        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (! in_array($ext, $extensoesOk, true)) {
            $this->responderJson([
                'message' => $tipoFonte === 'cadastro_cidadao'
                    ? 'Para Cadastro cidadão o arquivo deve ser do tipo XLSX.'
                    : 'Para eSUS o arquivo deve ser do tipo PDF ou CSV.',
            ], 422);
        }

        $maxSize = 20 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxSize) {
            $this->responderJson(['message' => 'O arquivo não pode ter mais de 20 MB.'], 422);
        }

        $token = (string) Str::uuid();
        $dir = storage_path('app/temp/verificar-cpf-esus');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->responderJson(['message' => 'Não foi possível preparar o armazenamento temporário.'], 500);
        }

        $storagePath = 'temp/verificar-cpf-esus/'.$token.'.'.$ext;
        $absolutePath = storage_path('app/'.$storagePath);
        if (! @move_uploaded_file($file['tmp_name'], $absolutePath)) {
            $this->responderJson(['message' => 'Falha ao salvar o arquivo enviado.'], 500);
        }

        if (! Auth::check()) {
            $this->responderJson(['message' => 'Sessão expirada. Atualize a página e faça login novamente.'], 401);
        }

        $userId = (int) Auth::id();
        $payload = [
            'status' => 'queued',
            'sucesso' => null,
            'mensagem' => 'Arquivo enfileirado. Aguarde o processamento…',
            'token' => $token,
            'user_id' => $userId,
            'resultado' => null,
            'atualizado_em' => now()->toIso8601String(),
        ];
        Cache::put(VerificarCpfEsusProcessJob::cacheKey($token), $payload, now()->addHours(2));

        try {
            VerificarCpfEsusProcessJob::dispatch(
                $token,
                DB::getDefaultConnection(),
                $storagePath,
                $tipoFonte,
                $ext,
                $anoLetivo,
                ! empty($_POST['esus_excluir_sem_cpf_somente_cns']),
                $userId,
            );
        } catch (\Throwable $e) {
            $this->responderJson([
                'message' => 'Não foi possível enfileirar o processamento: '.$e->getMessage(),
            ], 500);
        }

        $this->responderJson([
            'token' => $token,
            'status' => 'queued',
            'message' => $payload['mensagem'],
        ]);
    }

    private function responderAjaxStatus(): never
    {
        if (! Auth::check()) {
            $this->responderJson(['message' => 'Sessão expirada. Atualize a página e faça login novamente.'], 401);
        }

        if (! Gate::allows('view', Process::CONFIGURATIONS_TOOLS)) {
            $this->responderJson(['message' => 'Sem permissão.'], 403);
        }

        $token = (string) (request()->query('token') ?? $_GET['token'] ?? '');
        if ($token === '' || ! preg_match('/^[a-f0-9\-]{36}$/i', $token)) {
            $this->responderJson([
                'message' => 'Token inválido ou ausente na consulta de status.',
            ], 422);
        }

        $payload = Cache::get(VerificarCpfEsusProcessJob::cacheKey($token));
        if (! is_array($payload)) {
            $this->responderJson([
                'message' => 'Processamento não encontrado ou expirado. Envie o arquivo novamente.',
            ], 404);
        }

        $userId = (int) Auth::id();
        $payloadUserId = (int) ($payload['user_id'] ?? 0);
        // Aceita user_id 0 (quando Auth falhou no enqueue) e o usuário atual.
        if ($payloadUserId > 0 && $payloadUserId !== $userId) {
            $this->responderJson(['message' => 'Processamento não pertence ao usuário logado.'], 403);
        }

        if (($payload['status'] ?? '') === 'done') {
            $resultado = is_array($payload['resultado'] ?? null) ? $payload['resultado'] : [];
            $itens = $resultado['cpfs_nao_cadastrados'] ?? [];
            if (is_array($itens) && $itens !== []) {
                VerificarCpfEsusExportController::armazenarParaExportacao(
                    (int) ($resultado['cpfs_extraidos'] ?? 0),
                    (int) ($resultado['ano_letivo'] ?? date('Y')),
                    $itens,
                    (bool) ($resultado['excluir_sem_cpf_somente_cns'] ?? false)
                );
            } else {
                VerificarCpfEsusExportController::limparExportacao();
            }
        }

        $this->responderJson([
            'token' => $token,
            'status' => $payload['status'] ?? 'unknown',
            'sucesso' => $payload['sucesso'] ?? null,
            'mensagem' => $payload['mensagem'] ?? '',
            'message' => $payload['mensagem'] ?? '',
            'resultado' => $payload['resultado'] ?? null,
            'export_url' => url('/relatorios/verificar-cpf-esus/exportar'),
        ]);
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
        $this->addHtml($this->htmlScriptLoadingProcessamentoComUrls());

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
     * Overlay + envio assíncrono (fila) para evitar 504 no gateway.
     */
    private function htmlScriptLoadingProcessamentoComUrls(): string
    {
        $html = <<<'HTML'
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
    <div class="titulo" id="verificar-cpf-esus-loading-titulo">Enviando arquivo…</div>
    <div class="subtitulo" id="verificar-cpf-esus-loading-sub">Aguarde. O processamento ocorre em segundo plano e pode levar alguns minutos.</div>
  </div>
</div>
<div id="verificar-cpf-esus-resultado-async" style="margin-top:12px;"></div>
<script type="text/javascript">
(function () {
  var pageUrl = window.location.pathname;
  var pollTimer = null;

  function setLoading(visible, titulo, subtitulo) {
    var overlay = document.getElementById('verificar-cpf-esus-loading');
    var btn = document.getElementById('btn_verificar');
    if (overlay) {
      overlay.classList.toggle('is-visible', !!visible);
    }
    var t = document.getElementById('verificar-cpf-esus-loading-titulo');
    var s = document.getElementById('verificar-cpf-esus-loading-sub');
    if (t && titulo) { t.textContent = titulo; }
    if (s && subtitulo) { s.textContent = subtitulo; }
    if (btn) {
      btn.disabled = !!visible;
      btn.style.opacity = visible ? '0.6' : '';
      btn.style.cursor = visible ? 'wait' : '';
    }
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function parseJsonResponse(res) {
    return res.text().then(function (text) {
      var data = null;
      try {
        data = text ? JSON.parse(text) : {};
      } catch (e) {
        var hint = 'Resposta inválida do servidor (HTTP ' + res.status + ').';
        if (res.status === 401 || res.status === 403) {
          hint = 'Sessão expirada ou sem permissão. Atualize a página e tente novamente.';
        } else if (res.status === 404) {
          hint = 'Endpoint não encontrado. Atualize a página (Ctrl+F5) e tente novamente.';
        } else if (res.status === 413) {
          hint = 'Arquivo muito grande para o servidor (limite do proxy).';
        } else if (res.status >= 500) {
          hint = 'Erro interno no servidor ao processar o arquivo.';
        } else if (text && text.indexOf('<html') !== -1) {
          hint = 'O servidor retornou HTML em vez de JSON. Atualize a página e tente novamente.';
        }
        throw new Error(hint);
      }
      return { ok: res.ok, status: res.status, data: data || {} };
    });
  }

  function mostrarFlash(mensagem, sucesso) {
    var form = document.getElementById('formcadastro');
    if (!form) { return; }
    var existente = document.getElementById('verificar-cpf-esus-flash');
    if (existente) { existente.remove(); }
    var box = document.createElement('div');
    box.id = 'verificar-cpf-esus-flash';
    box.style.margin = '10px 0';
    box.style.padding = '10px 12px';
    box.style.border = '1px solid ' + (sucesso ? '#9ccc9c' : '#e0a0a0');
    box.style.background = sucesso ? '#eef9ee' : '#fff0f0';
    box.style.color = sucesso ? '#1b5e20' : '#8b0000';
    box.innerHTML = '<strong>' + escapeHtml(mensagem) + '</strong>';
    form.parentNode.insertBefore(box, form);
  }

  function renderResultado(payload) {
    var host = document.getElementById('verificar-cpf-esus-resultado-async');
    if (!host) { return; }
    var resultado = payload.resultado || {};
    if (resultado.erro) {
      host.innerHTML = '<div class="form" style="color:#c00;"><strong>Resumo:</strong> '
        + escapeHtml(resultado.erro) + '</div>';
      return;
    }
    var ano = parseInt(resultado.ano_letivo || new Date().getFullYear(), 10);
    var total = parseInt(resultado.cpfs_extraidos || 0, 10);
    var n = Array.isArray(resultado.cpfs_nao_cadastrados) ? resultado.cpfs_nao_cadastrados.length : 0;
    var texto;
    if (n === 0) {
      texto = '<strong>Resumo:</strong> ' + total + ' CPF(s) lidos do arquivo. Ano letivo <strong>'
        + ano + '</strong>. Todos possuem matrícula ativa neste ano.';
    } else {
      texto = '<strong>Resumo:<br/>Ano letivo <strong>' + ano + '</strong>. <br/></strong> '
        + total + ' CPF(s) lidos do arquivo. <br/><strong>' + n
        + '</strong> sem matrícula ativa (aluno e matrícula ativos).';
    }
    var html = '<div class="form">' + texto + '</div>';
    if (n > 0 && payload.export_url) {
      html += '<div class="form" style="margin-top:8px;"><a href="'
        + escapeHtml(payload.export_url)
        + '" target="_blank" rel="noopener" class="decorated"><strong>Exportar relatório em PDF</strong></a>'
        + ' — CPF/CNS, nome, nascimento, endereço (sem CEP), último atendimento de saúde, data da última matrícula ou transferência no cadastro escolar.</div>';
    }
    host.innerHTML = html;
  }

  function limparPoll() {
    if (pollTimer) {
      window.clearTimeout(pollTimer);
      pollTimer = null;
    }
  }

  function pollStatus(token) {
    var statusUrl = pageUrl + (pageUrl.indexOf('?') >= 0 ? '&' : '?') + 'ajax=status&token=' + encodeURIComponent(token);
    fetch(statusUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(parseJsonResponse).then(function (result) {
      if (!result.ok) {
        var erroMsg = (result.data && (result.data.message || result.data.mensagem))
          || ('Falha ao consultar status (HTTP ' + result.status + ').');
        throw new Error(erroMsg);
      }
      var status = result.data.status;
      if (status === 'queued' || status === 'processing') {
        setLoading(
          true,
          status === 'queued' ? 'Na fila…' : 'Processando arquivo…',
          (result.data.mensagem || result.data.message || 'Aguarde. Arquivos grandes podem levar alguns minutos.')
        );
        pollTimer = window.setTimeout(function () { pollStatus(token); }, 2000);
        return;
      }
      limparPoll();
      setLoading(false);
      mostrarFlash(result.data.mensagem || result.data.message || 'Processamento finalizado.', !!result.data.sucesso);
      renderResultado(result.data);
    }).catch(function (err) {
      limparPoll();
      setLoading(false);
      mostrarFlash(err.message || 'Erro ao consultar o processamento.', false);
    });
  }

  window.verificarCpfEsusEnviar = function () {
    var form = document.getElementById('formcadastro');
    var fileInput = document.getElementById('arquivo_pdf');
    if (!form || !fileInput) { return; }
    if (!fileInput.files || !fileInput.files.length) {
      mostrarFlash('Selecione um arquivo para enviar.', false);
      return;
    }

    limparPoll();
    setLoading(true, 'Enviando arquivo…', 'O processamento continuará em segundo plano.');

    var fd = new FormData();
    fd.append('tipoacao', 'EnfileirarAsync');
    fd.append('ajax', 'enfileirar');
    fd.append('arquivo_pdf', fileInput.files[0]);
    var ano = document.getElementById('ano_letivo');
    var tipo = document.getElementById('tipo_fonte');
    var excluir = document.getElementById('esus_excluir_sem_cpf_somente_cns');
    if (ano) { fd.append('ano_letivo', ano.value); }
    if (tipo) { fd.append('tipo_fonte', tipo.value); }
    if (excluir && excluir.checked) { fd.append('esus_excluir_sem_cpf_somente_cns', '1'); }

    fetch(pageUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    }).then(parseJsonResponse).then(function (result) {
      if (!result.ok) {
        var msg = (result.data && (result.data.message || result.data.mensagem)) || 'Não foi possível iniciar o processamento.';
        if (result.data && result.data.errors) {
          var first = Object.values(result.data.errors)[0];
          if (first && first[0]) { msg = first[0]; }
        }
        throw new Error(msg);
      }
      if (!result.data.token) {
        throw new Error('Token de processamento não retornado.');
      }
      setLoading(true, 'Na fila…', result.data.message || 'Aguarde o processamento…');
      pollStatus(result.data.token);
    }).catch(function (err) {
      limparPoll();
      setLoading(false);
      mostrarFlash(err.message || 'Erro ao enviar o arquivo.', false);
    });
  };
})();
</script>
HTML;

        return $html;
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
