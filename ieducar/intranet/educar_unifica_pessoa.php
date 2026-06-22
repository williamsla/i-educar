<?php

use App\Models\Individual;
use App\Models\LegacyUser;
use App\Models\LegacyUserSchool;
use App\Models\LogUnification;
use App\Services\ValidationDataService;
use iEducar\Modules\Unification\PersonLogUnification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

return new class extends clsCadastro
{
    public $pessoas;

    public $pessoa_logada;

    public $tabela_pessoas = [];

    public $pessoa_duplicada;

    public $pagina_atual = 1;
    public $itens_por_pagina = 10;

    public function Formular()
    {
        $this->titulo = 'i-Educar - Unificação de pessoas';
        $this->processoAp = '9998878';

        $this->breadcrumb(currentPage: 'Unificação de pessoas', breadcrumbs: [
            url('intranet/educar_index.php') => 'Escola',
        ]);
    }

    public function Inicializar()
    {
        $retorno = 'Novo';

        if (isset($_GET['pagina'])) {
            $this->pagina_atual = (int) $_GET['pagina'];
        }

        $obj_permissoes = new clsPermissoes;
        $obj_permissoes->permissao_cadastra(
            int_processo_ap: 9998878,
            int_idpes_usuario: $this->pessoa_logada,
            int_soma_nivel_acesso: 7,
            str_pagina_redirecionar: 'index.php'
        );

        return $retorno;
    }

    public function Gerar()
    {
        $duplicatas = $this->buscarPossiveisDuplicatas();

        echo "
        <style>
            /* ===== ESTILOS DO FORMULÁRIO SUPERIOR ===== */
            .formulario-superior {
                margin-bottom: 30px;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
            }
            .campo-pesquisa-container {
                max-width: 100%;
            }
            .campo-pesquisa-container .campoRotulo {
                font-weight: bold;
                margin-bottom: 5px;
            }
            .campo-pesquisa-container input[type='text'] {
                width: 100%;
                max-width: 600px;
                padding: 8px 12px;
                border: 2px solid #ccc;
                border-radius: 4px;
                font-size: 14px;
                transition: border-color 0.3s;
            }
            .campo-pesquisa-container input[type='text']:focus {
                border-color: #4CAF50;
                outline: none;
                box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
            }
            .botoes-acoes {
                margin-top: 15px;
                margin-bottom: 10px;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
            }
            .btn-carregar-dados {
                background: #1976D2;
                color: white;
                border: none;
                padding: 10px 25px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
                transition: background-color 0.3s;
            }
            .btn-carregar-dados:hover:not(:disabled) {
                background: #1565C0;
            }
            .btn-carregar-dados:disabled {
                background: #90CAF9;
                cursor: not-allowed;
            }
            .btn-adicionar-mais {
                background: #4CAF50;
                color: white;
                border: none;
                padding: 10px 25px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
                transition: background-color 0.3s;
            }
            .btn-adicionar-mais:hover {
                background: #388E3C;
            }
            .btn-duplicatas {
                background: #FF6F00;
                color: white;
                border: none;
                padding: 10px 25px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
                transition: background-color 0.3s;
            }
            .btn-duplicatas:hover {
                background: #E65100;
            }
            .texto-ajuda {
                font-size: 12px;
                color: #666;
                font-style: italic;
                margin-left: 10px;
            }
            .separador-pesquisa {
                border-bottom: 2px solid #e0e0e0;
                margin: 20px 0;
            }
            #tabela_pessoas {
                width: 100%;
                border-collapse: collapse;
            }
            #tabela_pessoas td {
                padding: 8px;
                vertical-align: middle;
            }
            #tabela_pessoas .campo_texto {
                width: 100%;
                max-width: 500px;
            }

            /* ===== ESTILOS DOS ACORDEONS - CONTORNO CINZA CLARO ===== */
            .accordion-container {
                margin-bottom: 20px;
                margin-top: 30px;
            }
            .accordion-item {
                border: 1px solid #d0d0d0;
                margin-bottom: 10px;
                border-radius: 8px;
                overflow: hidden;
                background-color: #fff;
            }
            .accordion-item.aviso-misto {
                border-color: #d0d0d0;
            }
            .accordion-header {
                background-color: #f5f5f5;
                padding: 15px;
                cursor: pointer;
                font-weight: bold;
                font-size: 16px;
                transition: background-color 0.3s;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .accordion-header:hover {
                background-color: #e8e8e8;
            }
            .accordion-header .titulo {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .accordion-header .badge {
                background-color: #4CAF50;
                color: white;
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 12px;
            }
            .accordion-header .icone {
                font-size: 20px;
                transition: transform 0.3s;
            }
            .accordion-header .icone.rotacionado {
                transform: rotate(90deg);
            }
            .accordion-content {
                display: none;
                padding: 20px;
                border-top: 1px solid #d0d0d0;
                background-color: #fff;
            }
            .accordion-content.ativo {
                display: block;
            }
            .tabela-grupo {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 13px;
            }
            .tabela-grupo th, .tabela-grupo td {
                border: 1px solid #d0d0d0;
                padding: 8px 10px;
                text-align: left;
                vertical-align: middle;
            }
            .tabela-grupo th {
                background-color: #e8e8e8;
                color: #333;
                font-weight: bold;
            }
            .tabela-grupo tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .tabela-grupo tr:hover {
                background-color: #f0f7f0;
            }
            .btn-visualizar {
                color: black;
                border: 1px solid #ccc;
                padding: 4px 10px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
                background: white;
            }
            .btn-visualizar:hover {
                background-color: #e0e0e0;
            }
            .link_remove {
                color: #ff4444;
                cursor: pointer;
                text-decoration: underline;
                font-weight: bold;
            }
            .link_remove:hover {
                color: #cc0000;
            }
            .confirmacao-grupo {
                margin-top: 15px;
                padding: 15px;
                background-color: #f9f9f9;
                border-radius: 5px;
                border-left: 4px solid #4CAF50;
            }
            .btn-unificar-grupo {
                background-color: darkblue;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                margin-top: 10px;
                font-size: 14px;
                font-weight: bold;
            }
            .btn-unificar-grupo:disabled {
                background-color: #90CAF9;
                cursor: not-allowed;
            }
            .btn-unificar-grupo:hover:not(:disabled) {
                background-color: #1a237e;
            }
            .radio-principal {
                transform: scale(1.2);
                margin: 0;
                cursor: pointer;
            }
            .paginacao {
                display: flex;
                justify-content: center;
                gap: 10px;
                margin: 20px 0;
                flex-wrap: wrap;
            }
            .paginacao a, .paginacao span {
                padding: 8px 15px;
                border: 1px solid #ddd;
                text-decoration: none;
                color: #333;
                border-radius: 4px;
                transition: all 0.3s;
            }
            .paginacao a:hover {
                background-color: #4CAF50;
                color: white;
                border-color: #4CAF50;
            }
            .paginacao .pagina-ativa {
                background-color: #4CAF50;
                color: white;
                border-color: #4CAF50;
                font-weight: bold;
            }
            .titulo-duplicatas {
                font-size: 18px;
                font-weight: bold;
                margin: 20px 0 15px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid #4CAF50;
                color: #333;
            }
            
            /* ===== BADGES ===== */
            .badge-aluno {
                background-color: #1976D2;
                color: white;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
                display: inline-block;
            }
            .badge-responsavel {
                background-color: #7B1FA2;
                color: white;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
                display: inline-block;
            }
            .badge-outro {
                background-color: #757575;
                color: white;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
                display: inline-block;
            }
            .badge-aviso-misto {
                background-color: #FF6F00;
                color: white;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: bold;
                display: inline-block;
                animation: pulsar 1.5s infinite;
            }
            @keyframes pulsar {
                0%   { opacity: 1; }
                50%  { opacity: 0.65; }
                100% { opacity: 1; }
            }
            .badge-aviso-aluno {
                background-color: #E65100;
                color: white;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: bold;
                display: inline-block;
            }

            /* ===== AVISOS ===== */
            .aviso-misto-box {
                background-color: #FFF3E0;
                border: 2px solid #FF9800;
                border-radius: 6px;
                padding: 12px 16px;
                margin-bottom: 16px;
                font-size: 13px;
                color: #5D4037;
                line-height: 1.6;
            }
            .aviso-misto-box strong {
                color: #E65100;
                font-size: 14px;
            }
            .aviso-seguro-box {
                background-color: #E8F5E9;
                border: 1px solid #4CAF50;
                border-radius: 6px;
                padding: 10px 16px;
                margin-bottom: 16px;
                font-size: 13px;
                color: #2E7D32;
            }
            .aviso-aluno-primeiro-box {
                background-color: #FFF8E1;
                border: 2px solid #F57F17;
                border-left: 6px solid #E65100;
                border-radius: 6px;
                padding: 16px;
                margin-bottom: 16px;
                font-size: 13px;
                color: #4E342E;
                display: flex;
                gap: 14px;
                align-items: flex-start;
            }
            .aviso-aluno-icone {
                font-size: 32px;
                line-height: 1;
                flex-shrink: 0;
            }
            .aviso-aluno-texto {
                line-height: 1.6;
            }
            .btn-ir-unifica-aluno {
                display: inline-block;
                margin-top: 10px;
                background-color: #E65100;
                color: white !important;
                padding: 8px 18px;
                border-radius: 5px;
                text-decoration: none !important;
                font-weight: bold;
                font-size: 13px;
                transition: background-color 0.2s;
            }
            .btn-ir-unifica-aluno:hover {
                background-color: #BF360C;
            }

            /* ===== AUTOCOMPLETE ===== */
            .ui-autocomplete {
                max-height: 200px;
                overflow-y: auto;
                overflow-x: hidden;
                background: white;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                z-index: 1000;
            }
            .ui-autocomplete .ui-menu-item {
                padding: 8px 12px;
                cursor: pointer;
                border-bottom: 1px solid #f0f0f0;
            }
            .ui-autocomplete .ui-menu-item:hover {
                background: #e8f5e9;
            }
            .ui-autocomplete .ui-state-focus {
                background: #c8e6c9;
                border: none;
            }

            /* ===== ESTILOS DA TABELA DE UNIFICAÇÃO ===== */
            .tableDetalheLinhaSeparador {
                border-bottom: 2px solid #4CAF50;
                padding: 5px 0;
            }
            #tabela_pessoas_unificadas {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            #tabela_pessoas_unificadas th {
                background: #4CAF50;
                color: white;
                padding: 10px;
                text-align: left;
                font-size: 13px;
            }
            #tabela_pessoas_unificadas td {
                padding: 8px 10px;
                border-bottom: 1px solid #e0e0e0;
                font-size: 13px;
            }
            #tabela_pessoas_unificadas tr:hover {
                background: #f5f5f5;
            }
            #tabela_pessoas_unificadas .tr_title td {
                background: #e8f5e9;
                font-weight: bold;
            }
            .check_principal {
                transform: scale(1.2);
                cursor: pointer;
            }
            .btn-green {
                background: #4CAF50 !important;
            }
            .btn-disabled {
                background: #cccccc !important;
                cursor: not-allowed !important;
            }
            #tr_confirma_dados_unificacao {
                background: #f9f9f9;
            }
            #tr_confirma_dados_unificacao td {
                padding: 15px;
            }
            .linhaBotoes td {
                padding: 15px 0;
            }
            .linhaBotoes .botaolistagem {
                margin-right: 10px;
                background: #4CAF50;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
            }
            .linhaBotoes .botaolistagem:hover {
                background: #388E3C;
            }
            .unifica_pessoa_h2 {
                color: #333;
                font-size: 16px;
                font-weight: normal;
                margin: 15px 0;
                padding: 10px;
                background: #fff3e0;
                border-left: 4px solid #ff9800;
            }
            #tr_observacoes td {
                padding: 15px;
                background: #fff8e1;
                border: 1px solid #ffcc02;
            }
            #recarregar_lista {
                color: #1976D2;
                cursor: pointer;
                text-decoration: underline;
            }
            #recarregar_lista:hover {
                color: #0D47A1;
            }
            .lista_pessoas_unificadas_hr .tableDetalheLinhaSeparador {
                border-bottom: 2px solid #ff9800;
            }

            @media (max-width: 768px) {
                .tabela-grupo {
                    font-size: 12px;
                }
                .tabela-grupo th, .tabela-grupo td {
                    padding: 5px;
                }
                #tabela_pessoas_unificadas {
                    font-size: 12px;
                }
                #tabela_pessoas_unificadas th,
                #tabela_pessoas_unificadas td {
                    padding: 5px;
                }
            }
        </style>
        ";

        echo "
        <script>
        function toggleAccordion(grupoId) {
            var content = document.getElementById('accordion-content-' + grupoId);
            var icone = document.getElementById('icone-' + grupoId);

            if (content.classList.contains('ativo')) {
                content.classList.remove('ativo');
                icone.classList.remove('rotacionado');
            } else {
                content.classList.add('ativo');
                icone.classList.add('rotacionado');
            }
        }

        function visualizarDadosPessoa(idpes, nomePessoa) {
            var url = '/intranet/atendidos_det.php?cod_pessoa=' + idpes;
            window.open(url, '_blank');
        }

        function confirmaAnaliseDoGrupo(grupoId) {
            var checked = document.getElementById('check_confirma_grupo_' + grupoId).checked;
            var radioSelecionado = document.querySelector('input[name=\"principal_' + grupoId + '\"]:checked');
            var btnUnificar = document.getElementById('btn_unificar_grupo_' + grupoId);
            var checkEl = document.getElementById('check_confirma_grupo_' + grupoId);

            if (checkEl.disabled) {
                btnUnificar.disabled = true;
                return;
            }

            if (radioSelecionado && checked) {
                btnUnificar.disabled = false;
            } else {
                btnUnificar.disabled = true;
            }
        }

        function removerPessoaDoGrupo(grupoId, idpes) {
            var row = document.getElementById('row_' + grupoId + '_' + idpes);
            var totalPessoas = document.querySelectorAll('#' + grupoId + ' .linha_listagem_grupo').length;

            if (totalPessoas <= 2) {
                if (confirm('É necessário ao menos 2 pessoas para a unificação. Este grupo será removido. Deseja prosseguir?')) {
                    var grupo = document.getElementById(grupoId);
                    grupo.style.transition = 'opacity 0.4s';
                    grupo.style.opacity = '0';
                    setTimeout(function() { grupo.remove(); }, 400);
                }
                return;
            }

            row.style.transition = 'opacity 0.4s';
            row.style.opacity = '0';
            setTimeout(function() {
                row.remove();
                var checkConfirm = document.getElementById('check_confirma_grupo_' + grupoId);
                if (checkConfirm) checkConfirm.checked = false;
                var btnUnificar = document.getElementById('btn_unificar_grupo_' + grupoId);
                if (btnUnificar) btnUnificar.disabled = true;
            }, 400);
        }

        function removerGrupo(grupoId) {
            if (confirm('Deseja remover este grupo de unificação?')) {
                var grupo = document.getElementById(grupoId);
                grupo.style.transition = 'opacity 0.4s';
                grupo.style.opacity = '0';
                setTimeout(function() { grupo.remove(); }, 400);
            }
        }

        function unificarGrupo(grupoId) {
            var pessoaPrincipal = document.querySelector('input[name=\"principal_' + grupoId + '\"]:checked');

            if (!pessoaPrincipal) {
                alert('Selecione uma pessoa principal.');
                return;
            }

            var pessoaPrincipalValor = pessoaPrincipal.value;
            var pessoasIds = [];
            var temAluno = false;
            var temResponsavel = false;

            document.querySelectorAll('#' + grupoId + ' .linha_listagem_grupo').forEach(function(row) {
                var idpes = row.getAttribute('data-idpes');
                var tipo  = row.getAttribute('data-tipo');
                if (idpes) {
                    pessoasIds.push(parseInt(idpes));
                    if (tipo === 'aluno')       temAluno = true;
                    if (tipo === 'responsavel') temResponsavel = true;
                }
            });

            var mensagemAviso = '';
            if (temAluno && temResponsavel) {
                mensagemAviso =
                    '⚠️ ATENÇÃO! Este grupo contém ALUNOS e RESPONSÁVEIS.\\n' +
                    'Unificar pode causar vínculos incorretos, históricos misturados\\n' +
                    'e problemas no sistema de matrículas.\\n' +
                    'Verifique cuidadosamente antes de prosseguir!\\n\\n';
            }

            var msgConfirmacao =
                mensagemAviso +
                'Confirmar unificação de ' + pessoasIds.length + ' pessoas deste grupo?\\n' +
                'Pessoa principal: ID ' + pessoaPrincipalValor + '\\n\\n' +
                'Esta ação não poderá ser desfeita!';

            if (confirm(msgConfirmacao)) {
                var dados = [{ idpes: parseInt(pessoaPrincipalValor), pessoa_principal: true }];

                pessoasIds.forEach(function(id) {
                    if (id != pessoaPrincipalValor) {
                        dados.push({ idpes: id, pessoa_principal: false });
                    }
                });

                var formData = document.createElement('form');
                formData.method = 'post';
                formData.action = 'educar_unifica_pessoa.php';

                var acao = document.createElement('input');
                acao.type  = 'hidden';
                acao.name  = 'tipoacao';
                acao.value = 'Novo';
                formData.appendChild(acao);

                var hiddenField = document.createElement('input');
                hiddenField.type  = 'hidden';
                hiddenField.name  = 'pessoas';
                hiddenField.value = JSON.stringify(dados);
                formData.appendChild(hiddenField);

                document.body.appendChild(formData);
                formData.submit();
            }
        }

        function mudarPagina(pagina) {
            var url = new URL(window.location.href);
            url.searchParams.set('pagina', pagina);
            window.location.href = url.toString();
        }
        </script>
        ";

        // ========== FORMULÁRIO SUPERIOR ==========
        echo '<div class="formulario-superior">';
        echo '<div class="campo-pesquisa-container">';
        
        $this->acao_enviar = 'carregaDadosPessoas()';
        $this->campoTabelaInicio(nome: 'tabela_pessoas', arr_campos: ['Pessoa duplicada', 'Campo Pessoa duplicada'], arr_valores: $this->tabela_pessoas);
        $this->campoRotulo(nome: 'pessoa_label', campo: '', valor: 'Pessoa física a ser unificada <span class="campo_obrigatorio">*</span>');
        $this->campoTexto(nome: 'pessoa_duplicada', campo: 'Pessoa duplicada', valor: $this->pessoa_duplicada, tamanhovisivel: 50, tamanhomaximo: 255, expressao: true, duplo: false);
        $this->campoTabelaFim();
        
        echo '</div>';
        echo '</div>';

        echo '<div class="separador-pesquisa"></div>';

        // ========== LISTAGEM DE DUPLICATAS ==========
        if (!empty($duplicatas) && count($duplicatas) > 0) {
            echo '<div class="titulo-duplicatas">📋 Grupos de possíveis duplicatas encontrados</div>';

            $totalGrupos  = count($duplicatas);
            $totalPaginas = ceil($totalGrupos / $this->itens_por_pagina);

            if ($this->pagina_atual < 1) $this->pagina_atual = 1;
            if ($this->pagina_atual > $totalPaginas) $this->pagina_atual = $totalPaginas;

            $inicio        = ($this->pagina_atual - 1) * $this->itens_por_pagina;
            $gruposPagina  = array_slice($duplicatas, $inicio, $this->itens_por_pagina);

            echo '<div id="todos-grupos" class="accordion-container">';

            foreach ($gruposPagina as $indice => $grupo) {
                $this->gerarCardGrupoAcordeon($grupo, $inicio + $indice);
            }

            echo '</div>';

            if ($totalPaginas > 1) {
                echo '<div class="paginacao">';
                if ($this->pagina_atual > 1) {
                    echo '<a href="#" onclick="mudarPagina(1); return false;">« Primeira</a>';
                    echo '<a href="#" onclick="mudarPagina(' . ($this->pagina_atual - 1) . '); return false;">‹ Anterior</a>';
                }

                $paginaInicio = max(1, $this->pagina_atual - 2);
                $paginaFim    = min($totalPaginas, $this->pagina_atual + 2);
                for ($i = $paginaInicio; $i <= $paginaFim; $i++) {
                    if ($i == $this->pagina_atual) {
                        echo '<span class="pagina-ativa">' . $i . '</span>';
                    } else {
                        echo '<a href="#" onclick="mudarPagina(' . $i . '); return false;">' . $i . '</a>';
                    }
                }

                if ($this->pagina_atual < $totalPaginas) {
                    echo '<a href="#" onclick="mudarPagina(' . ($this->pagina_atual + 1) . '); return false;">Próxima ›</a>';
                    echo '<a href="#" onclick="mudarPagina(' . $totalPaginas . '); return false;">Última »</a>';
                }
                echo '</div>';

                echo '<div style="text-align: center; margin: 10px 0 20px 0; color: #666;">';
                echo 'Total de grupos: ' . $totalGrupos . ' | Página ' . $this->pagina_atual . ' de ' . $totalPaginas;
                echo '</div>';
            }
        } else {
            echo "<div class='accordion-container'><p>✅ Nenhuma duplicata encontrada no sistema.</p></div>";
        }

        $styles  = ['/vendor/legacy/Cadastro/Assets/Stylesheets/UnificaPessoa.css'];
        $scripts = ['/vendor/legacy/Portabilis/Assets/Javascripts/ClientApi.js'];
        Portabilis_View_Helper_Application::loadStylesheet(viewInstance: $this, files: $styles);
        Portabilis_View_Helper_Application::loadJavascript(viewInstance: $this, files: $scripts);
    }

    private function gerarCardGrupoAcordeon($grupo, $indice)
    {
        $grupoId       = 'grupo_' . $indice;
        $primeiraPessoa = $grupo[0];
        $nomeGrupo     = $primeiraPessoa['nome'];
        $dataNasc      = $primeiraPessoa['data_nascimento'];
        $quantidade    = count($grupo);

        // Detectar se o grupo tem tipos mistos
        $tipos = array_unique(array_column($grupo, 'tipo'));
        $temAluno       = in_array('aluno', $tipos);
        $temResponsavel = in_array('responsavel', $tipos);
        $ehMisto        = $temAluno && $temResponsavel;

        $classeItemMisto  = $ehMisto ? ' aviso-misto' : '';
        
        // Contar quantos são alunos no grupo
        $qtdAlunos = count(array_filter($grupo, fn($p) => $p['tipo'] === 'aluno'));
        $temMaisDeUmAluno = $qtdAlunos > 1;

        if ($temMaisDeUmAluno) {
            $classeItemMisto = ' aviso-misto';
            $badgeAvisoHeader = '<span class="badge-aviso-aluno">⛔ Unifique os alunos primeiro</span>';
        } elseif ($ehMisto) {
            $badgeAvisoHeader = '<span class="badge-aviso-misto">⚠️ Tipos mistos</span>';
        } else {
            $badgeAvisoHeader = '';
        }

        echo "
        <div id='{$grupoId}' class='accordion-item{$classeItemMisto}'>
            <div class='accordion-header' onclick='toggleAccordion(\"{$grupoId}\")'>
                <div class='titulo'>
                    <span class='icone' id='icone-{$grupoId}'>▶</span>
                    <span>📋 Grupo " . ($indice + 1) . ": <strong>{$nomeGrupo}</strong> — Nascimento: {$dataNasc}</span>
                    <span class='badge'>{$quantidade} pessoas</span>
                    {$badgeAvisoHeader}
                </div>
            </div>
            <div id='accordion-content-{$grupoId}' class='accordion-content'>
        ";

        // Avisos dentro do conteúdo
        if ($temAluno && $temMaisDeUmAluno) {
            echo "
                <div class='aviso-aluno-primeiro-box'>
                    <div class='aviso-aluno-icone'>🎓</div>
                    <div class='aviso-aluno-texto'>
                        <strong>⛔ Unificação de pessoas bloqueada — unifique os ALUNOS primeiro!</strong><br><br>
                        Este grupo possui <strong>{$qtdAlunos} pessoas com vínculo de Aluno</strong>.
                        O sistema i-Educar exige que a unificação de alunos seja feita <strong>antes</strong>
                        da unificação de pessoas físicas.<br><br>
                        <a href='/intranet/educar_unifica_aluno.php' target='_blank' class='btn-ir-unifica-aluno'>
                            🔗 IR PARA UNIFICAÇÃO DE ALUNOS AGORA
                        </a>
                        <span style='display:block; margin-top:10px; font-size:12px; color:#7f4000;'>
                            Após concluir a unificação de alunos, recarregue esta página.
                        </span>
                    </div>
                </div>
            ";
        } elseif ($ehMisto) {
            echo "
                <div class='aviso-misto-box'>
                    <strong>⚠️ Atenção: este grupo contém ALUNOS e RESPONSÁVEIS!</strong><br>
                    Verifique cuidadosamente cada registro antes de prosseguir.
                </div>
            ";
        } elseif ($temAluno) {
            echo "
                <div class='aviso-seguro-box'>
                    ✅ Este grupo contém apenas ALUNOS. A unificação é considerada segura.
                </div>
            ";
        } elseif ($temResponsavel) {
            echo "
                <div class='aviso-seguro-box'>
                    ✅ Este grupo contém apenas RESPONSÁVEIS. A unificação é considerada segura.
                </div>
            ";
        } else {
            echo "
                <div class='aviso-seguro-box'>
                    ✅ Este grupo não possui vínculo específico. A unificação é considerada segura.
                </div>
            ";
        }

        echo "
                <table class='tabela-grupo'>
                    <thead>
                        <tr>
                            <th style='width:60px; text-align:center;'>Principal</th>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Data Nascimento</th>
                            <th>CPF</th>
                            <th>RG</th>
                            <th>Nome da Mãe</th>
                            <th>Visualizar</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
        ";

        $primeiro = true;
        foreach ($grupo as $pessoa) {
            $nomeEscapado = addslashes($pessoa['nome']);
            $tipo         = $pessoa['tipo'] ?? 'outro';
            $checked      = $primeiro ? 'checked' : '';

            switch ($tipo) {
                case 'aluno':
                    $tipoHtml = '<span class="badge-aluno">🎓 Aluno</span>';
                    break;
                case 'responsavel':
                    $tipoHtml = '<span class="badge-responsavel">👤 Responsável</span>';
                    break;
                default:
                    $tipoHtml = '<span class="badge-outro">📋 Outro</span>';
            }

            echo "
                <tr id='row_{$grupoId}_{$pessoa['idpes']}' class='linha_listagem_grupo' data-idpes='{$pessoa['idpes']}' data-tipo='{$tipo}'>
                    <td style='text-align:center;'>
                        <input type='radio' class='radio-principal' name='principal_{$grupoId}' value='{$pessoa['idpes']}' {$checked} onchange='confirmaAnaliseDoGrupo(\"{$grupoId}\")'>
                    </td>
                    <td><a target='_blank' href='/intranet/atendidos_det.php?cod_pessoa={$pessoa['idpes']}'>{$pessoa['idpes']}</a></td>
                    <td>{$pessoa['nome']}</td>
                    <td>{$tipoHtml}</td>
                    <td>{$pessoa['data_nascimento']}</td>
                    <td>{$pessoa['cpf']}</td>
                    <td>{$pessoa['rg']}</td>
                    <td>{$pessoa['nome_mae']}</td>
                    <td><button class='btn-visualizar' onclick='visualizarDadosPessoa({$pessoa['idpes']}, \"{$nomeEscapado}\")'>👁️ Visualizar</button></td>
                    <td><a class='link_remove' onclick='removerPessoaDoGrupo(\"{$grupoId}\", {$pessoa['idpes']})'><b><u>EXCLUIR</u></b></a></td>
                </tr>
            ";
            $primeiro = false;
        }

        echo "
                    </tbody>
                </table>
                <div class='confirmacao-grupo'>
                    <input type='checkbox' id='check_confirma_grupo_{$grupoId}' onchange='confirmaAnaliseDoGrupo(\"{$grupoId}\")' " . ($temMaisDeUmAluno ? "disabled title='Realize a unificação de alunos primeiro'" : "") . ">
                    <label for='check_confirma_grupo_{$grupoId}' style='" . ($temMaisDeUmAluno ? "color:#999; cursor:not-allowed;" : "") . "'>
                        Confirmo a análise de que os cadastros referem-se a mesma pessoa.
                    </label>
                    <br><br>
                    <button id='btn_unificar_grupo_{$grupoId}' class='btn-unificar-grupo' onclick='unificarGrupo(\"{$grupoId}\")' disabled " . ($temMaisDeUmAluno ? "title='Bloqueado: unifique os alunos primeiro'" : "") . ">
                        " . ($temMaisDeUmAluno ? "⛔ Bloqueado — unifique os alunos primeiro" : "🔄 Unificar este grupo ({$quantidade} pessoas)") . "
                    </button>
                    " . ($temMaisDeUmAluno ? "<br><small style='color:#E65100; margin-top:6px; display:inline-block;'>👆 Use o botão 'IR PARA UNIFICAÇÃO DE ALUNOS AGORA'.</small>" : "") . "
                </div>
            </div>
        </div>
        ";
    }

    /**
     * Busca possíveis duplicatas com limite para evitar lentidão
     */
    private function buscarPossiveisDuplicatas()
    {
        $db = new clsBanco();

        // null  = administrador (sem restrição, comportamento original)
        // []    = usuário sem nenhuma escola vinculada (não vê nenhuma duplicata)
        // array = usuário vinculado a escola(s) específica(s)
        $escolasPermitidas = $this->getEscolasPermitidasUsuario();

        $origemPessoasPermitidas = $this->montaOrigemPessoasPermitidas($escolasPermitidas);

        // Usuário restrito sem nenhuma escola vinculada: não há duplicatas a mostrar
        if ($origemPessoasPermitidas === null) {
            return [];
        }

        // Primeiro, buscar apenas os nomes e datas que têm duplicatas (limitado a 500 grupos)
        $sqlGrupos = "
            SELECT 
                p.nome,
                f.data_nasc,
                COUNT(*) as total
            FROM cadastro.pessoa p
            INNER JOIN cadastro.fisica f ON f.idpes = p.idpes
            WHERE f.idpes IN (
                {$origemPessoasPermitidas}
            )
            GROUP BY p.nome, f.data_nasc
            HAVING COUNT(*) > 1
            ORDER BY COUNT(*) DESC, p.nome
            LIMIT 500
        ";

        $db->Consulta($sqlGrupos);
        
        $nomesDatas = [];
        while ($db->ProximoRegistro()) {
            $row = $db->Tupla();
            $nomesDatas[] = "('" . addslashes($row['nome']) . "', '" . addslashes($row['data_nasc']) . "')";
        }

        if (empty($nomesDatas)) {
            return [];
        }

        // Buscar os detalhes das pessoas que estão nos grupos encontrados
        $sqlPessoas = "
            SELECT
                f.idpes,
                p.nome,
                COALESCE(to_char(f.data_nasc, 'dd/mm/yyyy'), '') AS data_nascimento,
                COALESCE(f.cpf::varchar, 'Não consta') AS cpf,
                COALESCE(d.rg, 'Não consta') AS rg,
                COALESCE(f.nome_mae, 'Não consta') AS nome_mae,
                CASE
                    WHEN EXISTS (SELECT 1 FROM pmieducar.aluno WHERE ref_idpes = f.idpes AND ativo = 1) 
                        AND EXISTS (SELECT 1 FROM pmieducar.servidor WHERE cod_servidor = f.idpes AND ativo = 1) 
                    THEN 'aluno_responsavel'
                    WHEN EXISTS (SELECT 1 FROM pmieducar.aluno WHERE ref_idpes = f.idpes AND ativo = 1) 
                    THEN 'aluno'
                    WHEN EXISTS (SELECT 1 FROM pmieducar.servidor WHERE cod_servidor = f.idpes AND ativo = 1) 
                    THEN 'responsavel'
                    ELSE 'outro'
                END AS tipo
            FROM cadastro.fisica f
            INNER JOIN cadastro.pessoa p ON p.idpes = f.idpes
            LEFT JOIN cadastro.documento d ON d.idpes = f.idpes
            WHERE (p.nome, f.data_nasc) IN (" . implode(', ', $nomesDatas) . ")
              AND f.idpes IN ({$origemPessoasPermitidas})
            ORDER BY p.nome, f.data_nasc, f.idpes
        ";

        $db->Consulta($sqlPessoas);

        $pessoas = [];
        while ($db->ProximoRegistro()) {
            $row = $db->Tupla();
            $pessoas[] = [
                'idpes'           => (int) $row['idpes'],
                'nome'            => $row['nome'],
                'data_nascimento' => $row['data_nascimento'],
                'cpf'             => $row['cpf'],
                'rg'              => $row['rg'],
                'nome_mae'        => $row['nome_mae'],
                'tipo'            => $row['tipo'],
            ];
        }

        if (empty($pessoas)) {
            return [];
        }

        // Agrupar por nome e data de nascimento
        $grupos = [];
        foreach ($pessoas as $pessoa) {
            $chave = $pessoa['nome'] . '|' . $pessoa['data_nascimento'];
            $grupos[$chave][] = $pessoa;
        }

        $grupos = array_filter($grupos, fn($g) => count($g) > 1);

        return array_values($grupos);
    }

    // ------------------------------------------------------------------
    //  Escopo de escola do usuário logado
    //  (mesmo critério já usado no Resumo Rápido da Home / educar_index.php
    //  e replicado em educar_unifica_aluno.php)
    // ------------------------------------------------------------------
    private function getUsuarioLogado()
    {
        try {
            if (Auth::check()) {
                return Auth::user();
            }

            $userId = $_SESSION['cod_usuario'] ?? null;

            return $userId ? LegacyUser::find($userId) : null;
        } catch (\Exception $e) {
            error_log('Erro ao obter usuário logado: ' . $e->getMessage());
            return null;
        }
    }

    private function usuarioEhAdmin($usuario): bool
    {
        if (!$usuario) {
            return false;
        }

        try {
            if (isset($usuario->nivel) && $usuario->nivel == 1) {
                return true;
            }

            // Sem nenhuma escola vinculada na tabela de usuários x escola = admin geral
            return LegacyUserSchool::where('ref_cod_usuario', $usuario->cod_usuario)->count() === 0;
        } catch (\Exception $e) {
            error_log('Erro ao verificar se usuário é admin: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return array<int>|null  null = sem restrição (admin); array = cod_escola permitidos
     */
    private function getEscolasPermitidasUsuario(): ?array
    {
        $usuario = $this->getUsuarioLogado();

        if ($this->usuarioEhAdmin($usuario)) {
            return null;
        }

        if (!$usuario) {
            return [];
        }

        try {
            return LegacyUserSchool::where('ref_cod_usuario', $usuario->cod_usuario)
                ->pluck('ref_cod_escola')
                ->map(static fn ($id) => (int) $id)
                ->toArray();
        } catch (\Exception $e) {
            error_log('Erro ao obter escolas do usuário: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Monta a subquery (apenas o corpo, sem o "SELECT ... IN (" ao redor) que define
     * o universo de pessoas (idpes) que o usuário logado está autorizado a ver nesta tela.
     *
     * - admin (null): comportamento original — todo aluno ativo + todo servidor ativo do sistema.
     * - usuário restrito a escola(s): por enquanto, somente o universo de ALUNOS matriculados
     *   ativamente em uma das escolas permitidas, via pmieducar.matricula.ref_ref_cod_escola.
     *   A branch de pmieducar.servidor é deliberadamente omitida para usuários restritos: não há,
     *   nos arquivos disponíveis, uma coluna ou tabela confirmada que ligue servidor a uma escola
     *   específica. Incluir servidor sem essa garantia poderia vazar duplicatas de outras escolas.
     *   Se houver essa relação (coluna direta em pmieducar.servidor ou tabela de lotação), pode-se
     *   refinar este método para também restringir servidores por escola.
     *
     * @return string|null  string = subquery pronta para uso em "... IN ( {subquery} )";
     *                       null   = usuário restrito sem nenhuma escola vinculada (nenhuma pessoa permitida)
     */
    private function montaOrigemPessoasPermitidas(?array $escolasPermitidas): ?string
    {
        if ($escolasPermitidas === null) {
            return "
                SELECT DISTINCT ref_idpes FROM pmieducar.aluno WHERE ativo = 1
                UNION
                SELECT DISTINCT cod_servidor FROM pmieducar.servidor WHERE ativo = 1
            ";
        }

        if (empty($escolasPermitidas)) {
            return null;
        }

        $ids = implode(',', array_map('intval', $escolasPermitidas));

        return "
            SELECT DISTINCT a.ref_idpes
            FROM pmieducar.aluno a
            INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
            WHERE a.ativo = 1
              AND m.ativo = 1
              AND m.ref_ref_cod_escola IN ({$ids})
        ";
    }

    private function validaDadosDaUnificacao($pessoa)
    {
        foreach ($pessoa as $item) {
            if (!array_key_exists(key: 'idpes', array: $item)) {
                return false;
            }
            if (!array_key_exists(key: 'pessoa_principal', array: $item)) {
                return false;
            }
        }

        return true;
    }

    private function buscaIdesDasPessoasParaUnificar($pessoas)
    {
        return array_map(
            callback: static fn ($item) => (int) $item['idpes'],
            array: array_filter(
                array: $pessoas,
                callback: static fn ($p) => $p['pessoa_principal'] === false
            )
        );
    }

    private function buscaPessoaPrincipal($pessoas)
    {
        $pessoas = array_values(array_filter(
            array: $pessoas,
            callback: static fn ($p) => $p['pessoa_principal'] === true
        ));

        return current($pessoas)['idpes'];
    }

    private function createLog($mainId, $duplicatesId, $createdBy)
    {
        $log               = new LogUnification;
        $log->type         = PersonLogUnification::getType();
        $log->main_id      = $mainId;
        $log->duplicates_id   = json_encode(array_values($duplicatesId));
        $log->created_by   = $createdBy;
        $log->updated_by   = $createdBy;
        $log->duplicates_name = json_encode($this->getNamesOfUnifiedPeople($duplicatesId));
        $log->save();

        return $log->id;
    }

    private function getNamesOfUnifiedPeople($duplicatesId)
    {
        $names = [];
        foreach ($duplicatesId as $personId) {
            $names[] = Individual::findOrFail($personId)->real_name;
        }

        return $names;
    }

    public function makeExtra()
    {
        return file_get_contents(__DIR__ . '/scripts/extra/educar-unifica-pessoa.js');
    }
};