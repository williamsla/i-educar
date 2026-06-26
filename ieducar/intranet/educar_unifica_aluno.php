<?php
 
use App\Models\LegacyUser;
use App\Models\LegacyUserSchool;
use App\Models\LogUnification;
use App\Services\ValidationDataService;
use iEducar\Modules\Unification\StudentLogUnification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
 
return new class extends clsCadastro
{
    public $pessoa_logada;
 
    public $tabela_alunos = [];
 
    public $aluno_duplicado;
 
    public $alunos;
 
    public $pagina_atual = 1;
    public $itens_por_pagina = 10;
 
    public function Inicializar()
    {
        $this->validaPermissaoDaPagina();
 
        if (isset($_GET['pagina'])) {
            $this->pagina_atual = (int) $_GET['pagina'];
        }
 
        $this->breadcrumb(currentPage: 'Cadastrar unificação', breadcrumbs: [
            url(path: 'intranet/educar_index.php') => 'Escola',
        ]);
 
        return 'Novo';
    }
 
    public function Gerar()
    {
        $duplicatas = $this->buscarPossiveisDuplicatas();
 
        echo "
        <style>
            .accordion-container {
                margin-bottom: 20px;
                margin-top: 30px;
            }
            .accordion-item {
                border: 1px solid #ddd;
                margin-bottom: 10px;
                border-radius: 8px;
                overflow: hidden;
                background-color: #fff;
                transition: box-shadow 0.2s;
            }
            .accordion-item:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            }
            .accordion-header {
                background-color: #f5f5f5;
                padding: 15px;
                cursor: pointer;
                font-weight: bold;
                font-size: 15px;
                transition: background-color 0.3s;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .accordion-header:hover {
                background-color: #e8f5e9;
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
                border-top: 1px solid #ddd;
                background-color: #fff;
            }
            .accordion-content.ativo {
                display: block;
            }
            .btn-remover-grupo {
                background-color: #ff4444;
                color: white;
                border: none;
                padding: 5px 12px;
                border-radius: 4px;
                cursor: pointer;
                margin-left: 10px;
                font-size: 12px;
                white-space: nowrap;
            }
            .btn-remover-grupo:hover { background-color: #cc0000; }
 
            /* Tabela principal do grupo */
            .tabela-grupo {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 16px;
                font-size: 13px;
            }
            .tabela-grupo th, .tabela-grupo td {
                border: 1px solid #ddd;
                padding: 9px 10px;
                text-align: left;
                vertical-align: middle;
            }
            .tabela-grupo th {
                background-color: darkgray;
                color: white;
                font-weight: bold;
            }
            .tabela-grupo tr:nth-child(even) { background-color: #f9f9f9; }
            .tabela-grupo tr:hover { background-color: #f0f7f0; }
 
            /* Linha recomendada (principal sugerido) */
            .linha-recomendada {
                background-color: #e8f5e9 !important;
                border-left: 4px solid #4CAF50;
            }
            .linha-recomendada td:first-child {
                border-left: 4px solid #4CAF50;
            }
 
            /* Badge de recomendação na célula */
            .badge-recomendado {
                display: inline-block;
                background-color: #4CAF50;
                color: white;
                font-size: 10px;
                font-weight: bold;
                padding: 2px 7px;
                border-radius: 10px;
                margin-left: 6px;
                vertical-align: middle;
                white-space: nowrap;
            }
 
            /* Barra de completude */
            .completude-bar-wrap {
                background: #e0e0e0;
                border-radius: 6px;
                height: 8px;
                width: 80px;
                display: inline-block;
                vertical-align: middle;
                margin-right: 5px;
            }
            .completude-bar {
                height: 8px;
                border-radius: 6px;
                background: #4CAF50;
                transition: width 0.4s;
            }
            .completude-bar.media  { background: #FF9800; }
            .completude-bar.baixa  { background: #f44336; }
            .completude-texto {
                font-size: 11px;
                color: #555;
                vertical-align: middle;
            }
 
            /* Botões de ação */
            .btn-visualizar {
                color: black;
                border: none;
                padding: 4px 10px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
            }
            .btn-visualizar:hover { background-color: darkgray; }
            .link_remove {
                color: #ff4444;
                cursor: pointer;
                text-decoration: underline;
                font-size: 13px;
            }
 
            /* Área de confirmação */
            .confirmacao-grupo {
                margin-top: 12px;
                padding: 15px;
                background-color: #f9f9f9;
                border-radius: 5px;
                border-left: 4px solid #4CAF50;
                font-size: 14px;
            }
            .btn-unificar-grupo {
                background-color: darkblue;
                color: white;
                padding: 10px 22px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                margin-top: 10px;
                font-size: 14px;
                font-weight: bold;
            }
            .btn-unificar-grupo:disabled {
                background-color: #cccccc;
                cursor: not-allowed;
                color: darkgray;
            }
            .btn-unificar-grupo:hover:not(:disabled) { background-color: blue; }
            .radio-principal {
                transform: scale(1.2);
                margin: 0;
                cursor: pointer;
            }
 
            /* Paginação */
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
            .formulario-superior {
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 1px solid #ddd;
            }
 
            /* ===== MODAL DE ORIENTAÇÃO ===== */
            .modal-overlay {
                position: fixed;
                top: 0; left: 0;
                width: 100%; height: 100%;
                background: rgba(0,0,0,0.45);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeInOverlay 0.2s;
            }
            @keyframes fadeInOverlay { from { opacity: 0; } to { opacity: 1; } }
            .modal-box {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 0;
                max-width: 920px;
                width: 96%;
                max-height: 88vh;
                overflow-y: auto;
                position: relative;
                box-shadow: 0 4px 24px rgba(0,0,0,0.12);
                animation: slideUp 0.25s;
            }
            @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            .modal-header {
                padding: 20px 24px 16px;
                border-bottom: 1px solid #e8e8e8;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .modal-header-icon {
                font-size: 18px;
                color: #555;
                flex-shrink: 0;
                line-height: 1;
            }
            .modal-header-title {
                font-size: 16px;
                font-weight: 600;
                color: #333;
                line-height: 1.4;
            }
            .modal-body { padding: 20px 24px 16px; }

            .modal-secao-titulo {
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.06em;
                color: #888;
                text-transform: uppercase;
                margin-bottom: 6px;
            }
            .modal-secao-subtitulo {
                font-size: 14px;
                font-weight: 600;
                color: #333;
                margin-bottom: 12px;
            }

            /* Card do aluno recomendado dentro do modal */
            .modal-card-recomendado {
                background: #f1f8e9;
                border: 1px solid #c8e6c9;
                border-radius: 8px;
                padding: 16px 18px 14px;
                margin-bottom: 22px;
            }
            .modal-card-recomendado-topo {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
            }
            .modal-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #c8e6c9;
                color: #2e7d32;
                font-size: 14px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .modal-nome-recomendado {
                font-size: 15px;
                font-weight: 700;
                color: #2e7d32;
                line-height: 1.3;
            }
            .modal-badge-recomendado {
                display: inline-block;
                font-size: 11px;
                font-weight: 600;
                color: #43a047;
                margin-left: 6px;
            }
            .modal-card-detalhes {
                display: flex;
                flex-wrap: wrap;
                gap: 16px 24px;
                font-size: 13px;
                color: #444;
                margin-bottom: 14px;
            }
            .modal-card-detalhes span {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                white-space: nowrap;
            }
            .modal-card-detalhes .icone-detalhe {
                color: #888;
                font-size: 12px;
            }
            .modal-progresso-wrap {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .modal-progresso-barra {
                flex: 1;
                background: #e0e0e0;
                border-radius: 4px;
                height: 6px;
                overflow: hidden;
            }
            .modal-progresso-preenchido {
                height: 100%;
                background: #4caf50;
                border-radius: 4px;
            }
            .modal-progresso-texto {
                font-size: 12px;
                color: #666;
                white-space: nowrap;
            }

            /* Passos de como fazer */
            .modal-passos { margin: 22px 0 0 0; }
            .modal-passos .passo {
                display: flex;
                gap: 12px;
                margin-bottom: 12px;
                align-items: flex-start;
            }
            .modal-passos .passo-num {
                background: #1976d2;
                color: white;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 12px;
                flex-shrink: 0;
                margin-top: 1px;
            }
            .modal-passos .passo-texto {
                font-size: 13px;
                color: #444;
                line-height: 1.55;
                padding-top: 2px;
            }
            .modal-passos .passo-texto strong { color: #333; font-weight: 600; }

            /* Tabela comparativa dentro do modal */
            .modal-tabela-comparativa {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                font-size: 12px;
                margin-top: 0;
                margin-bottom: 4px;
                border: 1px solid #e8e8e8;
                border-radius: 8px;
                overflow: hidden;
            }
            .modal-tabela-comparativa th {
                background: #f5f5f5;
                color: #555;
                padding: 10px 12px;
                text-align: left;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                border-bottom: 1px solid #e8e8e8;
            }
            .modal-tabela-comparativa td {
                border-bottom: 1px solid #f0f0f0;
                padding: 10px 12px;
                vertical-align: middle;
                color: #444;
            }
            .modal-tabela-comparativa tr:last-child td { border-bottom: none; }
            .modal-tabela-comparativa .celula-vazio { color: #bbb; font-size: 12px; }
            .modal-tabela-comparativa .linha-recomendada-modal { background: #f1f8e9 !important; }
            .modal-tabela-comparativa .td-cadastro-cod {
                font-weight: 700;
                color: #2e7d32;
                display: block;
            }
            .modal-tabela-comparativa .td-cadastro-cod.neutro { color: #333; font-weight: 600; }
            .modal-tabela-comparativa .badge-rec-tabela {
                display: block;
                font-size: 11px;
                font-weight: 600;
                color: #43a047;
                margin-top: 2px;
            }
            .modal-tabela-comparativa .celula-qtd {
                color: #333;
                font-weight: 500;
            }
            .modal-tabela-comparativa .celula-qtd .check-qtd { color: #4caf50; margin-right: 3px; }

            .modal-aviso-gemeos {
                background: #fff8e1;
                border: 1px solid #d7ccc8;
                border-radius: 8px;
                padding: 12px 14px;
                font-size: 13px;
                color: #6d4c41;
                margin-top: 18px;
                line-height: 1.5;
            }

            .modal-footer {
                padding: 14px 24px 18px;
                border-top: 1px solid #eee;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
            .btn-modal-ok {
                background: darkblue;
                color: #fff;
                border: 1px solid #ccc;
                padding: 8px 18px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .btn-modal-ok:hover { 
                background: lightblue; 
                color: #000; 
                border: 1px solid #ccc;
            }
            .btn-modal-fechar {
                background: #fff;
                border: 1px solid #ccc;
                padding: 8px 18px;
                border-radius: 6px;
                font-size: 13px;
                color: #333;
                cursor: pointer;
            }
            .btn-modal-fechar:hover { background: #f5f5f5; }
 
            /* Modal de dados escolares (visualizar) */
            #modal-overlay-aluno .modal-box-aluno {
                background: #fff;
                border-radius: 8px;
                padding: 30px;
                max-width: 870px;
                width: 92%;
                max-height: 80vh;
                overflow-y: auto;
                position: relative;
                box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            }
            #modal-overlay-aluno .modal-title {
                font-size: 17px;
                font-weight: bold;
                margin-bottom: 18px;
                padding-bottom: 10px;
                border-bottom: 2px solid #4CAF50;
                color: #333;
            }
            #modal-overlay-aluno .modal-footer-aluno {
                text-align: right;
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            }
            .btn-fechar-modal {
                background: #fff;
                border: 1px solid #aaa;
                border-radius: 4px;
                padding: 8px 24px;
                font-size: 14px;
                cursor: pointer;
            }
            .btn-fechar-modal:hover { background: #f0f0f0; }
 
            /* Pontuação de completude no header do grupo */
            .badge-score {
                font-size: 11px;
                color: #555;
                background: #f0f0f0;
                border: 1px solid #ccc;
                padding: 2px 8px;
                border-radius: 10px;
            }
        </style>
        ";
 
        echo "
        <script>
        // ======================================================
        //  CONTROLE: quais grupos já viram o modal
        // ======================================================
        var gruposModalVistos = {};
 
        // ======================================================
        //  ACCORDION (chamado diretamente após o modal ser visto)
        // ======================================================
        function toggleAccordion(grupoId) {
            var content = document.getElementById('accordion-content-' + grupoId);
            var icone   = document.getElementById('icone-' + grupoId);
            if (content.classList.contains('ativo')) {
                content.classList.remove('ativo');
                icone.classList.remove('rotacionado');
            } else {
                content.classList.add('ativo');
                icone.classList.add('rotacionado');
            }
        }
 
        // ======================================================
        //  CLIQUE NO HEADER DO GRUPO
        //  → 1ª vez: abre o modal de orientação
        //  → 2ª vez em diante: alterna o accordion normalmente
        // ======================================================
        function handleHeaderClick(grupoId) {
            if (!gruposModalVistos[grupoId]) {
                // Primeira vez: mostrar modal
                var modal = document.getElementById('modal-orientacao-' + grupoId);
                if (modal) {
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            } else {
                // Já viu o modal: comportamento normal de accordion
                toggleAccordion(grupoId);
            }
        }
 
        function fecharModalOrientacao(grupoId) {
            var modal = document.getElementById('modal-orientacao-' + grupoId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
            // Marcar que este grupo já mostrou o modal
            gruposModalVistos[grupoId] = true;
            // Abrir o accordion ao fechar o modal
            var content = document.getElementById('accordion-content-' + grupoId);
            var icone   = document.getElementById('icone-' + grupoId);
            if (content && !content.classList.contains('ativo')) {
                content.classList.add('ativo');
                icone.classList.add('rotacionado');
            }
        }
 
        function selecionarPrincipalRecomendado(grupoId, codAluno) {
            var radio = document.querySelector('input[name=\"principal_' + grupoId + '\"][value=\"' + codAluno + '\"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
            fecharModalOrientacao(grupoId);
        }
 
        // ======================================================
        //  MODAL DE DADOS ESCOLARES (botão Visualizar)
        // ======================================================
        function fecharModalAluno() {
            var overlay = document.getElementById('modal-overlay-aluno');
            if (overlay) overlay.remove();
            document.body.style.overflow = '';
        }
 
        function visualizarDadosAluno(codAluno, nomeAluno) {
            var url = '/module/Api/Aluno?oper=get&resource=dadosMatriculasHistoricosAlunos&aluno_id=' + codAluno;
 
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var html = '';
 
                    if (data.matriculas && data.matriculas.length > 0) {
                        html += '<h4 style=\"margin-top:0;\">Matrículas</h4>';
                        html += '<table class=\"tabela-grupo\"><thead><tr><th>Ano</th><th>Escola</th><th>Curso</th><th>Série</th><th>Turma</th></tr></thead><tbody>';
                        data.matriculas.forEach(function(m) {
                            html += '<tr><td>' + m.ano + '</td><td>' + m.escola + '</td><td>' + m.curso + '</td><td>' + m.serie + '</td><td>' + m.turma + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    }
 
                    if (data.historicos && data.historicos.length > 0) {
                        html += '<h4>Históricos</h4>';
                        html += '<table class=\"tabela-grupo\"><thead><tr><th>Ano</th><th>Escola</th><th>Curso</th><th>Série</th><th>Situação</th></tr></thead><tbody>';
                        data.historicos.forEach(function(h) {
                            html += '<tr><td>' + h.ano + '</td><td>' + h.escola + '</td><td>' + h.curso + '</td><td>' + h.serie + '</td><td>' + h.situacao + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    }
 
                    if (!html) html = '<p>Nenhum dado encontrado para este aluno.</p>';
 
                    var anterior = document.getElementById('modal-overlay-aluno');
                    if (anterior) anterior.remove();
 
                    var overlay = document.createElement('div');
                    overlay.id = 'modal-overlay-aluno';
                    overlay.className = 'modal-overlay';
 
                    var box = document.createElement('div');
                    box.className = 'modal-box-aluno';
 
                    var titulo = document.createElement('div');
                    titulo.className = 'modal-title';
                    titulo.textContent = 'Dados Escolares — ' + nomeAluno;
 
                    var corpo = document.createElement('div');
                    corpo.innerHTML = html;
 
                    var rodape = document.createElement('div');
                    rodape.className = 'modal-footer-aluno';
 
                    var btnFechar = document.createElement('button');
                    btnFechar.className = 'btn-fechar-modal';
                    btnFechar.textContent = 'Fechar';
                    btnFechar.onclick = fecharModalAluno;
 
                    rodape.appendChild(btnFechar);
                    box.appendChild(titulo);
                    box.appendChild(corpo);
                    box.appendChild(rodape);
                    overlay.appendChild(box);
                    document.body.appendChild(overlay);
                    document.body.style.overflow = 'hidden';
 
                    overlay.addEventListener('click', function(e) {
                        if (e.target === overlay) fecharModalAluno();
                    });
                })
                .catch(function() { alert('Erro ao carregar dados do aluno.'); });
        }
 
        // ======================================================
        //  CONFIRMAÇÃO / UNIFICAÇÃO
        // ======================================================
        function confirmaAnaliseDoGrupo(grupoId) {
            var checked         = document.getElementById('check_confirma_grupo_' + grupoId).checked;
            var radioSelecionado = document.querySelector('input[name=\"principal_' + grupoId + '\"]:checked');
            var btnUnificar      = document.getElementById('btn_unificar_grupo_' + grupoId);
            btnUnificar.disabled = !(radioSelecionado && checked);
        }
 
        function removerAlunoDoGrupo(grupoId, codAluno) {
            var row         = document.getElementById('row_' + grupoId + '_' + codAluno);
            var totalAlunos = document.querySelectorAll('#' + grupoId + ' .linha_listagem_grupo').length;
 
            if (totalAlunos <= 2) {
                if (confirm('É necessário ao menos 2 alunos para a unificação. Este grupo será removido. Deseja prosseguir?')) {
                    var grupo = document.getElementById(grupoId);
                    grupo.style.transition = 'opacity 0.4s';
                    grupo.style.opacity = '0';
                    setTimeout(function() { grupo.remove(); }, 400);
                }
                return;
            }
 
            row.style.transition = 'opacity 0.4s';
            row.style.opacity    = '0';
            setTimeout(function() {
                row.remove();
                document.getElementById('check_confirma_grupo_' + grupoId).checked = false;
                document.getElementById('btn_unificar_grupo_' + grupoId).disabled   = true;
            }, 400);
        }
 
        function removerGrupo(grupoId) {
            if (confirm('Deseja remover este grupo de unificação?')) {
                var grupo = document.getElementById(grupoId);
                grupo.style.transition = 'opacity 0.4s';
                grupo.style.opacity    = '0';
                setTimeout(function() { grupo.remove(); }, 400);
            }
        }
 
        function unificarGrupo(grupoId) {
            var alunoPrincipal = document.querySelector('input[name=\"principal_' + grupoId + '\"]:checked').value;
            var alunosIds = [];
 
            document.querySelectorAll('#' + grupoId + ' .linha_listagem_grupo').forEach(function(row) {
                alunosIds.push(parseInt(row.getAttribute('data-codigo')));
            });
 
            if (confirm('Confirmar unificação de ' + alunosIds.length + ' alunos deste grupo?\\n\\nAluno principal: ' + alunoPrincipal + '\\n\\nEsta ação não poderá ser desfeita!')) {
                enviarUnificacaoEspecifica(alunoPrincipal, alunosIds.filter(function(id) { return id != alunoPrincipal; }));
            }
        }
 
        function enviarUnificacaoEspecifica(alunoPrincipal, alunosDuplicados) {
            var dados = [{ codAluno: parseInt(alunoPrincipal), aluno_principal: true }];
            alunosDuplicados.forEach(function(id) {
                dados.push({ codAluno: parseInt(id), aluno_principal: false });
            });
 
            var form = document.createElement('form');
            form.method = 'post';
            form.action = 'educar_unifica_aluno.php';
 
            var acao    = document.createElement('input');
            acao.type   = 'hidden';
            acao.name   = 'tipoacao';
            acao.value  = 'Novo';
            form.appendChild(acao);
 
            var campo   = document.createElement('input');
            campo.type  = 'hidden';
            campo.name  = 'alunos';
            campo.value = JSON.stringify(dados);
            form.appendChild(campo);
 
            document.body.appendChild(form);
            form.submit();
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
        $this->acao_enviar = 'carregaDadosAlunos()';
        $this->campoTabelaInicio(nome: 'tabela_alunos', arr_campos: ['Aluno duplicado', 'Campo aluno duplicado'], arr_valores: $this->tabela_alunos);
        $this->campoRotulo(nome: 'aluno_label', campo: '', valor: 'Aluno(a) a ser unificado(a) <span class="campo_obrigatorio">*</span>');
        $this->campoTexto(nome: 'aluno_duplicado', campo: 'Aluno duplicado', valor: $this->aluno_duplicado, tamanhovisivel: 50, tamanhomaximo: 255, expressao: true, duplo: false);
        $this->campoTabelaFim();
        echo '</div>';
 
        // ========== LISTAGEM DE DUPLICATAS ==========
        if (!empty($duplicatas) && count($duplicatas) > 0) {
            echo '<div class="titulo-duplicatas">📋 Grupos de possíveis duplicatas encontrados</div>';
 
            $totalGrupos  = count($duplicatas);
            $totalPaginas = ceil($totalGrupos / $this->itens_por_pagina);
 
            if ($this->pagina_atual < 1) $this->pagina_atual = 1;
            if ($this->pagina_atual > $totalPaginas) $this->pagina_atual = $totalPaginas;
 
            $inicio       = ($this->pagina_atual - 1) * $this->itens_por_pagina;
            $gruposPagina = array_slice($duplicatas, $inicio, $this->itens_por_pagina);
 
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
                    if ($i == $this->pagina_atual)
                        echo '<span class="pagina-ativa">' . $i . '</span>';
                    else
                        echo '<a href="#" onclick="mudarPagina(' . $i . '); return false;">' . $i . '</a>';
                }
                if ($this->pagina_atual < $totalPaginas) {
                    echo '<a href="#" onclick="mudarPagina(' . ($this->pagina_atual + 1) . '); return false;">Próxima ›</a>';
                    echo '<a href="#" onclick="mudarPagina(' . $totalPaginas . '); return false;">Última »</a>';
                }
                echo '</div>';
                echo '<div style="text-align:center;margin:10px 0 20px 0;color:#666;">Total de grupos: ' . $totalGrupos . ' | Página ' . $this->pagina_atual . ' de ' . $totalPaginas . '</div>';
            }
        } else {
            echo "<div class='accordion-container'><p>✅ Nenhuma duplicata encontrada no sistema.</p></div>";
        }
 
        $styles  = ['/vendor/legacy/Cadastro/Assets/Stylesheets/UnificaAluno.css'];
        $scripts = ['/vendor/legacy/Portabilis/Assets/Javascripts/ClientApi.js'];
        Portabilis_View_Helper_Application::loadStylesheet(viewInstance: $this, files: $styles);
        Portabilis_View_Helper_Application::loadJavascript(viewInstance: $this, files: $scripts);
    }
 
    // ------------------------------------------------------------------
    //  Extrai iniciais do nome para o avatar do modal
    // ------------------------------------------------------------------
    private function obterIniciaisNome(string $nome): string
    {
        $partes = array_values(array_filter(explode(' ', trim($nome))));

        if (count($partes) >= 2) {
            return strtoupper(mb_substr($partes[0], 0, 1) . mb_substr($partes[count($partes) - 1], 0, 1));
        }

        return strtoupper(mb_substr($partes[0] ?? 'A', 0, 2));
    }

    // ------------------------------------------------------------------
    //  Calcula pontuação de completude de um aluno (0–100)
    // ------------------------------------------------------------------
    private function calcularCompletude(array $aluno): int
    {
        $campos = [
            'cpf'             => $aluno['cpf']      !== 'Não consta' ? 1 : 0,
            'rg'              => $aluno['rg']       !== 'Não consta' ? 1 : 0,
            'mae_aluno'       => $aluno['mae_aluno'] !== 'Não consta' ? 1 : 0,
            'inep'            => $aluno['inep']     !== 'Não consta' ? 1 : 0,
            'matriculas'      => ($aluno['qtd_matriculas'] ?? 0) > 0 ? 1 : 0,
            'historicos'      => ($aluno['qtd_historicos']  ?? 0) > 0 ? 1 : 0,
        ];
 
        $pontos = array_sum($campos);
        $total  = count($campos);
 
        return (int) round(($pontos / $total) * 100);
    }
 
    // ------------------------------------------------------------------
    //  Gera o card acordeon de um grupo, incluindo modal de orientação
    // ------------------------------------------------------------------
    private function gerarCardGrupoAcordeon(array $grupo, int $indice): void
    {
        $grupoId   = 'grupo_' . $indice;
        $nomeGrupo = $grupo[0]['nome'];
        $dataNasc  = $grupo[0]['data_nascimento'];
        $quantidade = count($grupo);
 
        // Calcular completude de cada aluno e identificar o recomendado
        foreach ($grupo as &$aluno) {
            $aluno['score'] = $this->calcularCompletude($aluno);
        }
        unset($aluno);
 
        usort($grupo, fn($a, $b) => $b['score'] <=> $a['score']);
        $recomendado = $grupo[0]; // maior score = recomendado como principal
 
        // ---- MODAL DE ORIENTAÇÃO (oculto, abre ao clicar no header) ----
        $this->gerarModalOrientacao($grupoId, $grupo, $recomendado, $indice);
 
        // ---- CARD ACCORDION ----
        $scoreRecomendado = $recomendado['score'];
        $badgeScore = '<span class="badge-score">📊 Melhor cadastro: ' . $scoreRecomendado . '% completo</span>';
 
        echo "
        <div id='{$grupoId}' class='accordion-item'>
            <div class='accordion-header' onclick='handleHeaderClick(\"{$grupoId}\")'>
                <div class='titulo'>
                    <span class='icone' id='icone-{$grupoId}'>▶</span>
                    <span>📋 Grupo " . ($indice + 1) . ": <strong>{$nomeGrupo}</strong> — Nascimento: {$dataNasc}</span>
                    <span class='badge'>{$quantidade} alunos</span>
                    {$badgeScore}
                </div>
                <!--
                    <div>
                        <button class='btn-remover-grupo' onclick='event.stopPropagation(); removerGrupo(\"{$grupoId}\")'>🗑️ Remover</button>
                    </div>
                -->
            </div>
            <div id='accordion-content-{$grupoId}' class='accordion-content'>
                <table class='tabela-grupo'>
                    <thead>
                        <tr>
                            <th style='width:60px;text-align:center;'>Principal</th>
                            <th>Código</th>
                            <th>INEP</th>
                            <th>Nome</th>
                            <th>Data Nasc.</th>
                            <th>CPF</th>
                            <th>RG</th>
                            <th>Nome da Mãe</th>
                            <th>Completude</th>
                            <th>Visualizar</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
        ";
 
        $primeiro = true;
        foreach ($grupo as $aluno) {
            $nomeEscapado    = addslashes($aluno['nome']);
            $ehRecomendado   = ($aluno['codigo'] === $recomendado['codigo']);
            $classeRecomendada = $ehRecomendado ? " class='linha_listagem_grupo linha-recomendada'" : " class='linha_listagem_grupo'";
            $badgeRec        = $ehRecomendado ? '<span class="badge-recomendado">⭐ Recomendado</span>' : '';
            $checked         = $primeiro ? 'checked' : '';
 
            // Barra de completude
            $score     = $aluno['score'];
            $classBarra = $score >= 70 ? '' : ($score >= 40 ? ' media' : ' baixa');
            $completudeHtml = "
                <div style='white-space:nowrap;'>
                    <div class='completude-bar-wrap'>
                        <div class='completude-bar{$classBarra}' style='width:{$score}%'></div>
                    </div>
                    <span class='completude-texto'>{$score}%</span>
                </div>
            ";
 
            echo "
                <tr id='row_{$grupoId}_{$aluno['codigo']}'{$classeRecomendada} data-codigo='{$aluno['codigo']}'>
                    <td style='text-align:center;'>
                        <input type='radio' class='radio-principal' name='principal_{$grupoId}' value='{$aluno['codigo']}' {$checked} onchange='confirmaAnaliseDoGrupo(\"{$grupoId}\")'>
                        {$badgeRec}
                    </td>
                    <td><a target='_blank' href='/intranet/educar_aluno_det.php?cod_aluno={$aluno['codigo']}'>{$aluno['codigo']}</a></td>
                    <td>{$aluno['inep']}</td>
                    <td>{$aluno['nome']}</td>
                    <td>{$aluno['data_nascimento']}</td>
                    <td>{$aluno['cpf']}</td>
                    <td>{$aluno['rg']}</td>
                    <td>{$aluno['mae_aluno']}</td>
                    <td>{$completudeHtml}</td>
                    <td><button class='btn-visualizar' onclick='visualizarDadosAluno({$aluno['codigo']}, \"{$nomeEscapado}\")'>👁️ Visualizar</button></td>
                    <td><a class='link_remove' onclick='removerAlunoDoGrupo(\"{$grupoId}\", {$aluno['codigo']})'><b><u>EXCLUIR</u></b></a></td>
                </tr>
            ";
            $primeiro = false;
        }
 
        echo "
                    </tbody>
                </table>
                <div class='confirmacao-grupo'>
                    <input type='checkbox' id='check_confirma_grupo_{$grupoId}' onchange='confirmaAnaliseDoGrupo(\"{$grupoId}\")'>
                    <label for='check_confirma_grupo_{$grupoId}'>
                        Confirmo a análise de que os cadastros referem-se a mesma pessoa.
                    </label>
                    <br><br>
                    <button id='btn_unificar_grupo_{$grupoId}' class='btn-unificar-grupo' onclick='unificarGrupo(\"{$grupoId}\")' disabled>
                        🔄 Unificar este grupo ({$quantidade} alunos)
                    </button>
                </div>
            </div>
        </div>
        ";
    }
 
    // ------------------------------------------------------------------
    //  Gera o modal de orientação de unificação para o grupo
    // ------------------------------------------------------------------
    private function gerarModalOrientacao(string $grupoId, array $grupo, array $recomendado, int $indice): void
    {
        $nomeRec   = htmlspecialchars($recomendado['nome']);
        $codRec    = $recomendado['codigo'];
        $scoreRec  = $recomendado['score'];
        $inepRec   = $recomendado['inep'];
        $cpfRec    = $recomendado['cpf'];
        $rgRec     = $recomendado['rg'];
        $maeRec    = $recomendado['mae_aluno'];
        $qtdMat    = $recomendado['qtd_matriculas'] ?? 0;
        $qtdHist   = $recomendado['qtd_historicos']  ?? 0;
        $quantidade = count($grupo);
 
        $iniciaisRec = $this->obterIniciaisNome($recomendado['nome']);
        $maeRecExib  = htmlspecialchars($maeRec !== 'Não consta' ? $maeRec : '— não consta');
        $histRecTxt  = $qtdHist > 0 ? "{$qtdHist} histórico" . ($qtdHist > 1 ? 's' : '') : 'nenhum histórico';

        // Tabela comparativa
        $linhasComparativas = '';
        foreach ($grupo as $aluno) {
            $ehRec   = ($aluno['codigo'] === $codRec);
            $classTr = $ehRec ? " class='linha-recomendada-modal'" : '';

            $tdVal = fn($v) => ($v !== 'Não consta' && $v !== '' && $v !== '0')
                ? htmlspecialchars((string) $v)
                : "<span class='celula-vazio'>— não consta</span>";

            $qtdMatAluno  = $aluno['qtd_matriculas'] ?? 0;
            $qtdHistAluno = $aluno['qtd_historicos'] ?? 0;

            $matStr = $qtdMatAluno > 0
                ? "<span class='celula-qtd'><span class='check-qtd'>✓</span>{$qtdMatAluno}</span>"
                : "<span class='celula-vazio'>— não consta</span>";
            $histStr = $qtdHistAluno > 0
                ? "<span class='celula-qtd'><span class='check-qtd'>✓</span>{$qtdHistAluno}</span>"
                : "<span class='celula-vazio'>— não consta</span>";

            $classCod = $ehRec ? '' : ' neutro';
            $badgeRec = $ehRec ? "<span class='badge-rec-tabela'>Recomendado</span>" : '';

            $linhasComparativas .= "
                <tr{$classTr}>
                    <td>
                        <span class='td-cadastro-cod{$classCod}'>Cód. {$aluno['codigo']}</span>
                        {$badgeRec}
                    </td>
                    <td>" . $tdVal($aluno['cpf']) . "</td>
                    <td>" . $tdVal($aluno['rg']) . "</td>
                    <td>" . $tdVal($aluno['inep']) . "</td>
                    <td>" . $tdVal($aluno['mae_aluno']) . "</td>
                    <td>{$matStr}</td>
                    <td>{$histStr}</td>
                    <td>{$aluno['score']}%</td>
                </tr>
            ";
        }

        echo "
        <div id='modal-orientacao-{$grupoId}' class='modal-overlay' style='display:none;'
             onclick='if(event.target===this) fecharModalOrientacao(\"{$grupoId}\")'>
            <div class='modal-box'>

                <!-- CABEÇALHO -->
                <div class='modal-header'>
                    <div class='modal-header-icon'>⑂</div>
                    <div class='modal-header-title'>Unificação de cadastros — Grupo " . ($indice + 1) . "</div>
                </div>

                <div class='modal-body'>

                    <!-- TABELA COMPARATIVA -->
                    <div class='modal-secao-titulo'>Comparativo dos cadastros do grupo</div>
                    <div class='modal-secao-subtitulo'>Aluno(a): {$nomeRec}</div>
                    <table class='modal-tabela-comparativa'>
                        <thead>
                            <tr>
                                <th>Cadastro</th>
                                <th>CPF</th>
                                <th>RG</th>
                                <th>INEP</th>
                                <th>Nome da Mãe</th>
                                <th>Matrículas</th>
                                <th>Históricos</th>
                                <th>Completo</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$linhasComparativas}
                        </tbody>
                    </table>

                    <!-- PASSO A PASSO -->
                    <div class='modal-passos'>
                        <div class='modal-secao-titulo'>Como proceder na tela seguinte</div>
                        <div class='passo'>
                            <div class='passo-num'>1</div>
                            <div class='passo-texto'>
                                O sistema já selecionou o cadastro <strong>Cód. {$codRec}</strong> como principal
                                (maior completude). Antes de unificar você poderá alterar, se necessário.
                            </div>
                        </div>
                        <div class='passo'>
                            <div class='passo-num'>2</div>
                            <div class='passo-texto'>
                                Na tela seguinte, use o botão <strong>Visualizar</strong> para consultar matrículas de cada cadastro antes de decidir.
                            </div>
                        </div>
                        <div class='passo'>
                            <div class='passo-num'>3</div>
                            <div class='passo-texto'>
                                Marque o checkbox de confirmação e clique em <strong>Unificar este grupo</strong>.
                                Os dados das matrículas e históricos serão consolidados no cadastro principal automaticamente.
                            </div>
                        </div>
                        <div class='passo'>
                            <div class='passo-num'>4</div>
                            <div class='passo-texto'>
                                Para seguir agora, clique em <strong>Usar o cadastro recomendado como principal</strong> abaixo.
                            </div>
                        </div>
                    </div>

                    <!-- AVISO GÊMEOS -->
                    <div class='modal-aviso-gemeos'>
                        ⚠ Antes de unificar, verifique se não se trata de <strong>homônimos</strong> (duas pessoas diferentes com o mesmo nome).                        
                    </div>

                </div><!-- /modal-body -->

                <div class='modal-footer'>
                    <button class='btn-modal-fechar' onclick='fecharModalOrientacao(\"{$grupoId}\"); return false;'>Fechar</button>
                    <button class='btn-modal-ok' onclick='selecionarPrincipalRecomendado(\"{$grupoId}\", {$codRec})'>
                        ☆ Usar o cadastro recomendado como principal
                    </button>
                </div>
 
            </div><!-- /modal-box -->
        </div><!-- /modal-overlay -->
        ";
    }
 
    // ------------------------------------------------------------------
    //  Busca duplicatas + contagem de matrículas e históricos
    // ------------------------------------------------------------------
    private function buscarPossiveisDuplicatas(): array
    {
        $db = new clsBanco();

        // null  = administrador (sem restrição, comportamento original)
        // array = usuário vinculado a escola(s) específica(s)
        $escolasPermitidas = $this->getEscolasPermitidasUsuario();

        $restricaoPrincipal = $this->montaRestricaoEscolaAluno('a.cod_aluno', $escolasPermitidas);
        $restricaoGrupo     = $this->montaRestricaoEscolaAluno('a2.cod_aluno', $escolasPermitidas);

        $sql = "
            SELECT
                a.cod_aluno AS codigo,
                p.nome,
                COALESCE(to_char(f.data_nasc, 'dd/mm/yyyy'), '')  AS data_nascimento,
                COALESCE(f.cpf::varchar, 'Não consta')             AS cpf,
                COALESCE(d.rg, 'Não consta')                       AS rg,
                COALESCE(relatorio.get_mae_aluno(a.cod_aluno), 'Não consta') AS mae_aluno,
                COALESCE(eca.cod_aluno_inep::varchar, 'Não consta') AS inep,
                (
                    SELECT COUNT(*) FROM pmieducar.matricula m
                    WHERE m.ref_cod_aluno = a.cod_aluno AND m.ativo = 1
                ) AS qtd_matriculas,
                (
                    SELECT COUNT(*) FROM pmieducar.historico_escolar he
                    WHERE he.ref_cod_aluno = a.cod_aluno
                ) AS qtd_historicos
            FROM pmieducar.aluno a
            JOIN cadastro.pessoa p   ON p.idpes  = a.ref_idpes
            JOIN cadastro.fisica f   ON f.idpes  = a.ref_idpes
            LEFT JOIN cadastro.documento d          ON d.idpes   = a.ref_idpes
            LEFT JOIN modules.educacenso_cod_aluno eca ON eca.cod_aluno = a.cod_aluno
            WHERE a.ativo = 1
              {$restricaoPrincipal}
              AND (p.nome, f.data_nasc) IN (
                    SELECT p2.nome, f2.data_nasc
                    FROM pmieducar.aluno a2
                    JOIN cadastro.pessoa p2 ON p2.idpes = a2.ref_idpes
                    JOIN cadastro.fisica f2 ON f2.idpes = a2.ref_idpes
                    WHERE a2.ativo = 1
                      {$restricaoGrupo}
                    GROUP BY p2.nome, f2.data_nasc
                    HAVING COUNT(*) > 1
              )
            ORDER BY p.nome, f.data_nasc, a.cod_aluno
        ";
 
        $db->Consulta($sql);
 
        $alunos = [];
        while ($db->ProximoRegistro()) {
            $row      = $db->Tupla();
            $alunos[] = [
                'codigo'          => (int) $row['codigo'],
                'nome'            => $row['nome'],
                'data_nascimento' => $row['data_nascimento'],
                'cpf'             => $row['cpf'],
                'rg'              => $row['rg'],
                'mae_aluno'       => $row['mae_aluno'],
                'inep'            => $row['inep'],
                'qtd_matriculas'  => (int) $row['qtd_matriculas'],
                'qtd_historicos'  => (int) $row['qtd_historicos'],
            ];
        }
 
        if (empty($alunos)) return [];
 
        $grupos = [];
        foreach ($alunos as $aluno) {
            $chave = $aluno['nome'] . '|' . $aluno['data_nascimento'];
            $grupos[$chave][] = $aluno;
        }
 
        $grupos = array_filter($grupos, fn($g) => count($g) > 1);
 
        return array_values($grupos);
    }
 
    // ------------------------------------------------------------------
    //  Escopo de escola do usuário logado
    //  (mesmo critério já usado no Resumo Rápido da Home / educar_index.php)
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
     * Monta a cláusula SQL que restringe um aluno (pela coluna cod_aluno informada)
     * a ter ao menos uma matrícula ativa em uma das escolas permitidas.
     * Quando $escolasPermitidas é null (admin), não aplica nenhuma restrição.
     */
    private function montaRestricaoEscolaAluno(string $colunaCodAluno, ?array $escolasPermitidas): string
    {
        if ($escolasPermitidas === null) {
            return '';
        }

        if (empty($escolasPermitidas)) {
            return ' AND 1 = 0 ';
        }

        $ids = implode(',', array_map('intval', $escolasPermitidas));

        return "
              AND EXISTS (
                    SELECT 1 FROM pmieducar.matricula m_esc
                    WHERE m_esc.ref_cod_aluno = {$colunaCodAluno}
                      AND m_esc.ativo = 1
                      AND m_esc.ref_ref_cod_escola IN ({$ids})
              )
        ";
    }

    private function validaPermissaoDaPagina(): void
    {
        (new clsPermissoes)->permissao_cadastra(
            int_processo_ap: 999847,
            int_idpes_usuario: $this->pessoa_logada,
            int_soma_nivel_acesso: 7,
            str_pagina_redirecionar: 'index.php'
        );
    }
 
    private function validaDadosDaUnificacaoAluno(array $alunos): bool
    {
        foreach ($alunos as $item) {
            if (!isset($item['codAluno']))       return false;
            if (!isset($item['aluno_principal'])) return false;
        }
        return true;
    }
 
    public function Novo()
    {
        $this->validaPermissaoDaPagina();
 
        try {
            $alunos = json_decode(json: $this->alunos, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (TypeError|Exception) {
            $this->mensagem = 'Informações inválidas para unificação';
            return false;
        }
 
        if (!$this->validaDadosDaUnificacaoAluno(alunos: $alunos)) {
            $this->mensagem = 'Dados enviados inválidos, recarregue a tela e tente novamente!';
            return false;
        }
 
        $validationData = new ValidationDataService;
 
        if (!$validationData->verifyQuantityByKey(data: $alunos, key: 'aluno_principal', quantity: 0)) {
            $this->mensagem = 'Aluno principal não informado';
            return false;
        }
 
        if ($validationData->verifyQuantityByKey(data: $alunos, key: 'aluno_principal', quantity: 1)) {
            $this->mensagem = 'Não pode haver mais de um aluno principal';
            return false;
        }
 
        if (!$validationData->verifyDataContainsDuplicatesByKey(data: $alunos, key: 'codAluno')) {
            $this->mensagem = 'Erro ao tentar unificar Alunos, foi inserido cadastro duplicados';
            return false;
        }
 
        $cod_aluno_principal = $this->buscaPessoaPrincipal(pessoas: $alunos);
        $cod_alunos          = $this->buscaIdesDasPessoasParaUnificar(pessoas: $alunos);
 
        DB::beginTransaction();
        $unificationId = $this->createLog(mainId: $cod_aluno_principal, duplicatesId: $cod_alunos, createdBy: $this->pessoa_logada);
        App_Unificacao_Aluno::unifica(
            codAlunoPrincipal: $cod_aluno_principal,
            codAlunos: $cod_alunos,
            codPessoa: $this->pessoa_logada,
            db: new clsBanco,
            unificationId: $unificationId
        );
 
        try {
            DB::commit();
        } catch (Throwable) {
            DB::rollBack();
            $this->mensagem = 'Não foi possível realizar a unificação';
            return false;
        }
 
        $this->mensagem = '<span>Alunos unificados com sucesso.</span>';
        $this->simpleRedirect(url: route(name: 'student-log-unification.index'));
    }
 
    private function buscaIdesDasPessoasParaUnificar(array $pessoas): array
    {
        return array_values(array_map(
            callback: static fn($item) => (int) $item['codAluno'],
            array: array_filter(
                array: $pessoas,
                callback: static fn($p) => $p['aluno_principal'] === false
            )
        ));
    }
 
    private function buscaPessoaPrincipal(array $pessoas): int
    {
        $pessoas = array_values(array_filter(
            array: $pessoas,
            callback: static fn($p) => $p['aluno_principal'] === true
        ));
        return (int) current($pessoas)['codAluno'];
    }
 
    private function createLog(int $mainId, array $duplicatesId, $createdBy): int
    {
        $log               = new LogUnification;
        $log->type         = StudentLogUnification::getType();
        $log->main_id      = $mainId;
        $log->duplicates_id = json_encode(array_values($duplicatesId));
        $log->created_by   = $createdBy;
        $log->updated_by   = $createdBy;
        $log->save();
        return $log->id;
    }
 
    public function makeExtra()
    {
        return file_get_contents(filename: __DIR__ . '/scripts/extra/educar-unifica-aluno.js');
    }
 
    public function Formular()
    {
        $this->title      = 'Unificação de alunos';
        $this->processoAp = '999847';
    }
};