<?php
 
use App\Models\LegacyStudent;
use App\Models\LegacyEnrollment;
use App\Models\LegacySchoolClass;
use App\Models\LegacyUser;
use App\Models\LegacyUserSchool;
use App\Models\LegacySchool;
use Illuminate\Support\Facades\DB;
use App\Facades\Asset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
 
return new class
{
    public function RenderHTML()
    {
        $user    = $this->getCurrentUser();
        $isAdmin = $this->isAdminUser($user);
        $schoolsIds = $isAdmin ? null : $this->getSchoolsIdByUser($user);

        // Lê o ano selecionado via GET (padrão: ano atual)
        $anoSelecionado = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

        $cpfAtualizado = Session::has('cpf_atualizado');
        if ($cpfAtualizado) {
            Session::forget('cpf_atualizado');
        }

        // Anos disponíveis para o filtro global (últimos 5 anos + próximo)
        $anoAtual = (int) date('Y');
        $anosDisponiveis = range($anoAtual + 1, $anoAtual - 4);

        // Busca escolas com resumo já filtrado pelo ano
        $escolas = $this->getEscolasComResumo($schoolsIds, $anoSelecionado);

        // Totais gerais (soma de todas as escolas)
        $totalAlunos      = array_sum(array_column($escolas, 'alunos'));
        $totalTurmas      = array_sum(array_column($escolas, 'turmas'));
        $totalAee         = array_sum(array_column($escolas, 'aee'));
        $totalPendencias  = array_sum(array_column($escolas, 'total_pendencias'));

        // Monta as options do select de ano (reutilizado no seletor global)
        $opcoesAnoGlobal = '';
        foreach ($anosDisponiveis as $ano) {
            $sel = $ano === $anoSelecionado ? ' selected' : '';
            $opcoesAnoGlobal .= '<option value="' . $ano . '"' . $sel . '>' . $ano . '</option>';
        }

        return '
                <link rel="stylesheet" href="' . Asset::get('/intranet/styles/educar_index.css') . '">
                
                <style>
                    .item-bullet { margin-right: 8px; color: #007bff; }
                    .document-link { cursor: pointer; }
                    .document-link:hover { color: #007bff; }
                    .menu-principal {
                        cursor: pointer; font-weight: 500; padding: 4px 0;
                        display: block; color: #333; width: 100%; text-align: left;
                    }
                    .menu-principal:hover { color: #007bff; }
                    .submenu-items {
                        display: none; margin: 0; padding: 0;
                        list-style: none; width: 100%;
                    }
                    .submenu-items.show { display: block; }
                    .submenu-items li { padding: 4px 0; list-style: none; margin-left: 0; width: 100%; }
                    .submenu-items li a {
                        text-decoration: none; color: #555; font-size: 12px;
                        display: block; padding: 2px 0; width: 100%;
                    }
                    .submenu-items li a:hover { color: #007bff; }
                    .card-content ul li { display: block; width: 100%; }
                    .success-message {
                        background-color: #d4edda; color: #155724; padding: 10px;
                        border-radius: 4px; margin-bottom: 15px;
                        border: 1px solid #c3e6cb; display: none;
                    }
                    .alunos-table { width: 100%; border-collapse: collapse; }
                    .alunos-table th {
                        padding: 12px; text-align: left;
                        border-bottom: 2px solid #dee2e6; background: #f8f9fa;
                    }
                    .alunos-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; }
                    .btn-editar-cpf {
                        background: #007bff; color: white; text-decoration: none;
                        padding: 8px 16px; border-radius: 4px; display: inline-block;
                        font-size: 12px; border: none; cursor: pointer;
                    }
                    .btn-editar-cpf:hover { background: #0056b3; }
                    .alert-pending {
                        margin-top: 10px; padding: 6px 12px; background: #dc3545;
                        color: white; border-radius: 20px; font-size: 12px;
                        font-weight: bold; display: inline-block;
                    }
                    .alert-icon { font-size: 14px; margin-right: 4px; }
                    .summary-number-container {
                        display: flex; flex-direction: column;
                        align-items: center; justify-content: center;
                    }

                    /* Cabeçalho do Resumo Rápido com seletor global de ano */
                    .quick-summary-header {
                        display: flex; align-items: center;
                        justify-content: space-between; flex-wrap: wrap;
                        gap: 10px; margin-bottom: 18px;
                    }
                    .quick-summary-header h2 {
                        margin: 0; font-size: 20px; color: #333;
                    }
                    .ano-filtro-global {
                        display: flex; align-items: center; gap: 8px;
                    }
                    .ano-filtro-global label {
                        font-size: 13px; font-weight: 600; color: #555; white-space: nowrap;
                    }
                    .ano-filtro-select {
                        padding: 5px 10px; border: 1px solid #ced4da; border-radius: 6px;
                        font-size: 13px; color: #333; background: #fff; cursor: pointer;
                    }
                    .ano-filtro-select:focus { outline: none; border-color: #007bff; }

                    /* Card de resumo geral */
                    .resumo-geral-card {
                        background: linear-gradient(135deg, #f0f4ff 0%, #fafbff 100%);
                        border: 1px solid #d0d9f0; border-radius: 12px;
                        padding: 20px 24px; margin-bottom: 24px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
                    }
                    .resumo-geral-title {
                        font-size: 12px; font-weight: 700; color: #666;
                        text-transform: uppercase; letter-spacing: 0.8px;
                        margin-bottom: 14px;
                    }
                    .resumo-geral-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                        gap: 16px;
                    }
                    .resumo-geral-item {
                        text-align: center; padding: 12px 8px;
                        background: #fff; border-radius: 10px;
                        border: 1px solid #e8edf8;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
                    }
                    .resumo-geral-item .rg-label {
                        font-size: 11px; color: #888; font-weight: 600;
                        text-transform: uppercase; letter-spacing: 0.5px;
                        margin-bottom: 8px; line-height: 1.3;
                    }
                    .resumo-geral-item .rg-number {
                        font-size: 28px; font-weight: 700; line-height: 1;
                    }
                    .rg-number.rg-alunos  { color: #007bff; }
                    .rg-number.rg-turmas  { color: #28a745; }
                    .rg-number.rg-aee     { color: #fd7e14; }
                    .rg-number.rg-docs    { color: #dc3545; }
                    .resumo-geral-item.rg-docs-item {
                        cursor: pointer; transition: background 0.2s;
                    }
                    .resumo-geral-item.rg-docs-item:hover { background: #fff5f5; }

                    /* Divisor entre resumo geral e individual */
                    .resumo-individual-titulo {
                        font-size: 13px; font-weight: 700; color: #555;
                        text-transform: uppercase; letter-spacing: 0.6px;
                        margin-bottom: 12px; padding-top: 4px;
                        border-top: 1px solid #e8e8e8; padding-top: 18px;
                    }

                    /* Accordion de escola */
                    .escola-accordion {
                        margin-bottom: 12px; border: 1px solid #e0e0e0;
                        border-radius: 10px; overflow: hidden;
                        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
                    }
                    .escola-accordion-header {
                        display: flex; align-items: center;
                        justify-content: space-between; padding: 14px 20px;
                        background: #f8f9fa; cursor: pointer;
                        user-select: none; transition: background 0.2s;
                    }
                    .escola-accordion-header:hover { background: #eef2ff; }
                    .escola-accordion-header .escola-nome {
                        font-weight: 600; font-size: 14px; color: #333;
                        display: flex; align-items: center; gap: 10px;
                    }
                    .escola-accordion-chevron {
                        font-size: 12px; color: #666;
                        transition: transform 0.3s; display: inline-block;
                    }
                    .escola-accordion-chevron.open { transform: rotate(180deg); }
                    .escola-accordion-body {
                        display: block; overflow: hidden; max-height: 0;
                        transition: max-height 0.35s ease, padding 0.2s;
                        padding: 0 20px; background: #fff;
                    }
                    .escola-accordion-body.open { max-height: 400px; padding: 16px 20px; }
                    .escola-badge-pendente {
                        background: #dc3545; color: white; border-radius: 12px;
                        font-size: 11px; font-weight: bold; padding: 2px 8px; margin-left: 8px;
                    }

                    /* Modal */
                    #modalAlunosSemCPF {
                        display: none; position: fixed; top: 0; left: 0;
                        width: 100%; height: 100%;
                        background: rgba(0,0,0,0.5); z-index: 1000;
                        justify-content: center; align-items: center;
                    }
                    .modal-inner {
                        background: white; border-radius: 12px; width: 90%;
                        max-width: 800px; max-height: 80vh; overflow: hidden;
                        display: flex; flex-direction: column;
                    }
                    .modal-header {
                        padding: 20px; border-bottom: 1px solid #f0f0f0;
                        display: flex; justify-content: space-between; align-items: center;
                    }
                    .modal-header h3 { margin: 0; color: #333; }
                    .modal-body { padding: 20px; overflow-y: auto; flex: 1; }
                    .modal-footer {
                        padding: 15px 20px; border-top: 1px solid #f0f0f0; text-align: right;
                    }
                    .btn-fechar-modal {
                        background: #6c757d; color: white; border: none;
                        padding: 8px 16px; border-radius: 4px; cursor: pointer;
                    }

                    /* Tabs do modal (Alunos / Servidores) */
                    .modal-tabs {
                        display: flex; gap: 6px; margin-bottom: 16px;
                        border-bottom: 2px solid #f0f0f0;
                    }
                    .modal-tab-btn {
                        background: none; border: none; cursor: pointer;
                        padding: 10px 16px; font-size: 13px; font-weight: 600;
                        color: #777; border-bottom: 3px solid transparent;
                        margin-bottom: -2px; transition: color 0.2s, border-color 0.2s;
                    }
                    .modal-tab-btn:hover { color: #007bff; }
                    .modal-tab-btn.active { color: #007bff; border-bottom-color: #007bff; }

                    /* Badges de pendência (CPF / Data de Nascimento) */
                    .pendencia-badge {
                        display: inline-block; font-size: 11px; font-weight: 600;
                        padding: 3px 8px; border-radius: 10px; margin-right: 4px;
                        white-space: nowrap;
                    }
                    .pendencia-cpf  { background: #fde2e1; color: #c0392b; }
                    .pendencia-nasc { background: #fff2d9; color: #b8790a; }

                    /* Paginação do modal */
                    .modal-pagination {
                        display: flex; align-items: center; justify-content: center;
                        gap: 6px; margin-top: 16px; flex-wrap: wrap;
                    }
                    .pg-btn {
                        min-width: 30px; height: 30px; padding: 0 8px;
                        border: 1px solid #dee2e6; background: #fff; color: #333;
                        border-radius: 6px; cursor: pointer; font-size: 12px;
                    }
                    .pg-btn:hover:not(:disabled) { background: #eef2ff; border-color: #007bff; }
                    .pg-btn.active { background: #007bff; border-color: #007bff; color: #fff; }
                    .pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }
                    .pg-ellipsis { color: #999; font-size: 12px; padding: 0 2px; }

                    .empty-state {
                        text-align: center; padding: 40px; color: #666;
                    }
                    .empty-state .empty-icon { font-size: 48px; margin-bottom: 10px; }
                </style>

                <div class="dashboard-container">
                    <div class="welcome-section">
                        <h1>Bem-vindo ao i-Educar</h1>
                        <p style="color: #666; margin-top: 5px; font-size: 14px;">Exibindo dados das escolas vinculadas ao seu perfil administrativo</p>
                    </div>
                    
                    <div id="successMessage" class="success-message" style="' . ($cpfAtualizado ? 'display: block;' : 'display: none;') . '">
                        ✅ CPF atualizado com sucesso! A lista foi atualizada.
                    </div>

                    <div class="cards-grid">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-matriculas">
                                    <div class="card-icon">📋</div>
                                </div>
                                <h2>Matrículas</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li style="display: block; width: 100%;">
                                        <div class="menu-principal" onclick="toggleSubmenu(this)">
                                            <span class="item-bullet">•</span> Gestão de Alunos
                                        </div>
                                        <ul class="submenu-items">
                                            <li><a href="/module/Cadastro/aluno">Nova matrícula</a></li>
                                            <li><a href="/intranet/educar_aluno_lst.php">Transferência de aluno</a></li>
                                            <li><a href="/intranet/educar_aluno_lst.php">Trocar aluno de turma</a></li>
                                            <li><a href="/intranet/educar_aluno_lst.php">Informar histórico de anos anteriores</a></li>
                                        </ul>
                                    </li>
                                    <li><a href="/intranet/educar_aluno_lst.php" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Consultar alunos</a></li>
                                    <li><a href="/module/Reports/StudentSheet" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Requerimento de matrícula</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-boletins">
                                    <div class="card-icon">📊</div>
                                </div>
                                <h2>Boletins</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li><a href="/module/Reports/ReportCard" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Boletim Escolar (Numérico)</a></li>
                                    <li><a href="/module/Reports/ReportConceptualCard" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Boletim Conceitual</a></li>
                                    <li><a href="/module/Reports/ReportDescriptiveCard" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Boletim Parecer Descritivo</a></li>
                                    <li><a href="/module/Reports/TeacherReportCard" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Boletim do professor</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-documentos">
                                    <div class="card-icon">📄</div>
                                </div>
                                <h2>Documentos</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li class="document-link" onclick="window.location.href=\'/module/Reports/SchoolHistory\'" style="display: block;">
                                        <span class="item-bullet">•</span> Imprimir histórico
                                    </li>
                                    <li class="document-link" onclick="window.location.href=\'/module/Reports/MinutesFinalResult\'" style="display: block;">
                                        <span class="item-bullet">•</span> Ata de resultado final
                                    </li>
                                    <li class="document-link" onclick="window.location.href=\'/module/Reports/IndividualStudentSheet\'" style="display: block;">
                                        <span class="item-bullet">•</span> Ficha Individual
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-declaracoes">
                                    <div class="card-icon">📜</div>
                                </div>
                                <h2>Declarações</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li><a href="/module/Reports/TransferenceCertificate" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Declaração de transferência</a></li>
                                    <li><a href="/module/Reports/FrequencyCertificate" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Declaração de frequência</a></li>
                                    <li><a href="/module/Reports/ConclusionCertificate" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Declaração de conclusão</a></li>
                                </ul>
                            </div>
                        </div>

                        <a href=\'../module/Avaliacao/diario\' style=\'text-decoration: none; color: inherit;\'>
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-icon-wrapper icon-movimentacao">
                                        <div class="card-icon">🔄</div>
                                    </div>
                                    <h2>Movimentação</h2>
                                </div>
                                <div class="card-content">
                                    <ul>
                                        <li><span class="item-bullet">•</span> Lançar notas recebidas de outra escola</li>
                                    </ul>
                                </div>
                            </div>
                        </a>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-duplicados">
                                    <div class="card-icon">👥</div>
                                </div>
                                <h2>Verificar alunos duplicados</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li><a href="/intranet/educar_unifica_aluno.php" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Alunos duplicados</a></li>
                                    <li><a href="/intranet/educar_unifica_pessoa.php" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Pessoas duplicadas</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-relatorios">
                                    <div class="card-icon">📑</div>
                                </div>
                                <h2>Relatórios</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li><a href="/module/Reports/StudentsPerClass" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Alunos por turma</a></li>
                                    <li><a href="/module/Reports/EnrollmentQuantitativeMap" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Quantitativo de matrículas</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="quick-summary-section">

                        <!-- Cabeçalho: título + seletor de ano global -->
                        <div class="quick-summary-header">
                            <h2>Resumo Rápido</h2>
                            <div class="ano-filtro-global">
                                <label for="ano-select-global">📅 Ano letivo:</label>
                                <select id="ano-select-global" class="ano-filtro-select"
                                        onchange="filtrarAnoGlobal(this)">
                                    ' . $opcoesAnoGlobal . '
                                </select>
                            </div>
                        </div>

                        <!-- Resumo geral consolidado -->
                        <div class="resumo-geral-card">
                            <div class="resumo-geral-title">📊 Consolidado geral — ' . $anoSelecionado . '</div>
                            <div class="resumo-geral-grid">
                                <div class="resumo-geral-item">
                                    <div class="rg-label">Total de Alunos Matriculados</div>
                                    <div class="rg-number rg-alunos">' . number_format($totalAlunos, 0, '', '.') . '</div>
                                </div>
                                <div class="resumo-geral-item">
                                    <div class="rg-label">Turmas Ativas</div>
                                    <div class="rg-number rg-turmas">' . number_format($totalTurmas, 0, '', '.') . '</div>
                                </div>
                                <div class="resumo-geral-item">
                                    <div class="rg-label">Atend. Educacional Especializado (AEE)</div>
                                    <div class="rg-number rg-aee">' . number_format($totalAee, 0, '', '.') . '</div>
                                </div>
                                <div class="resumo-geral-item rg-docs-item" onclick="abrirModalGeralPendencias()">
                                    <div class="rg-label">Documentos Pendentes</div>
                                    <div class="rg-number rg-docs">' . number_format($totalPendencias, 0, '', '.') . '</div>
                                    ' . ($totalPendencias > 0 ? '<div class="alert-pending" style="margin-top:8px;"><span class="alert-icon">⚠️</span> Requer atenção</div>' : '') . '
                                </div>
                            </div>
                        </div>

                        <!-- Accordions individuais por escola -->
                        <div class="resumo-individual-titulo">🏫 Por escola</div>
                        ' . $this->gerarAccordionEscolas($escolas, $anoSelecionado) . '

                    </div>
                </div>

                <!-- Modal global para lista de alunos sem CPF por escola -->
                <div id="modalAlunosSemCPF">
                    <div class="modal-inner">
                        <div class="modal-header">
                            <h3 id="modalTitulo">Alunos sem CPF válido</h3>
                            <button onclick="fecharModal()" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                        </div>
                        <div class="modal-body" id="modalCorpo">
                            <!-- preenchido via JS -->
                        </div>
                        <div class="modal-footer">
                            <button class="btn-fechar-modal" onclick="fecharModal()">Fechar</button>
                        </div>
                    </div>
                </div>

                <script>
                // Dados de todas as escolas para o modal geral (injetados pelo PHP)
                var _todasEscolas = ' . json_encode(array_map(function($e) {
                    return [
                        'nome'                      => $e['nome'],
                        'alunos_pendentes_json'     => $e['alunos_pendentes_json'],
                        'servidores_pendentes_json' => $e['servidores_pendentes_json'],
                    ];
                }, $escolas), JSON_UNESCAPED_UNICODE) . ';

                var PAGE_SIZE = 8;
                var MAX_PAGE_BUTTONS = 4;
                var _modalState = { alunos: [], servidores: [], abaAtiva: "alunos", paginaAlunos: 1, paginaServidores: 1 };

                // ---------------------------------------------------------------
                // Filtro de ano GLOBAL — recarrega a página com ?ano=X
                // ---------------------------------------------------------------
                function filtrarAnoGlobal(selectEl) {
                    var ano = selectEl.value;
                    window.location.href = window.location.pathname + "?ano=" + ano;
                }

                // ---------------------------------------------------------------
                // Accordion
                // ---------------------------------------------------------------
                function toggleEscolaAccordion(header, idx) {
                    var body    = document.getElementById("accordion-body-" + idx);
                    var chevron = document.getElementById("chevron-" + idx);
                    if (body.classList.contains("open")) {
                        body.classList.remove("open");
                        chevron.classList.remove("open");
                    } else {
                        body.classList.add("open");
                        chevron.classList.add("open");
                    }
                }

                function toggleSubmenu(element) {
                    var submenu = element.nextElementSibling;
                    submenu.classList.toggle("show");
                }

                // ---------------------------------------------------------------
                // Modal com dados ESPECÍFICOS da escola clicada (alunos + servidores)
                // ---------------------------------------------------------------
                function abrirListaPendencias(escolaNome, alunosJson, servidoresJson) {
                    var alunos     = JSON.parse(alunosJson);
                    var servidores = JSON.parse(servidoresJson);
                    var total = alunos.length + servidores.length;
                    preencherModalPendencias("Documentos Pendentes (" + total + ") — " + escolaNome, alunos, servidores);
                }

                // ---------------------------------------------------------------
                // Modal consolidado com TODAS as escolas (alunos + servidores)
                // ---------------------------------------------------------------
                function abrirModalGeralPendencias() {
                    var todosAlunos = [];
                    var todosServidores = [];
                    _todasEscolas.forEach(function(escola) {
                        JSON.parse(escola.alunos_pendentes_json).forEach(function(a) {
                            todosAlunos.push(Object.assign({}, a, { escola: escola.nome }));
                        });
                        JSON.parse(escola.servidores_pendentes_json).forEach(function(s) {
                            todosServidores.push(Object.assign({}, s, { escola: escola.nome }));
                        });
                    });
                    var total = todosAlunos.length + todosServidores.length;
                    preencherModalPendencias("Documentos Pendentes — Geral (" + total + ")", todosAlunos, todosServidores);
                }

                // ---------------------------------------------------------------
                // Monta o modal com abas (Alunos / Servidores) e paginação
                // ---------------------------------------------------------------
                function preencherModalPendencias(titulo, alunos, servidores) {
                    _modalState.alunos = alunos;
                    _modalState.servidores = servidores;
                    _modalState.abaAtiva = "alunos";
                    _modalState.paginaAlunos = 1;
                    _modalState.paginaServidores = 1;

                    document.getElementById("modalTitulo").textContent = titulo;
                    renderModalTabs();
                    document.getElementById("modalAlunosSemCPF").style.display = "flex";
                }

                function renderModalTabs() {
                    var corpo = document.getElementById("modalCorpo");
                    var abaAlunos = _modalState.abaAtiva === "alunos";
                    var html =
                        \'<div class="modal-tabs">\' +
                        \'<button type="button" class="modal-tab-btn\' + (abaAlunos ? " active" : "") + \'" onclick="mudarAbaModal(\\\'alunos\\\')">👨‍🎓 Alunos (\' + _modalState.alunos.length + \')</button>\' +
                        \'<button type="button" class="modal-tab-btn\' + (!abaAlunos ? " active" : "") + \'" onclick="mudarAbaModal(\\\'servidores\\\')">🧑‍💼 Servidores (\' + _modalState.servidores.length + \')</button>\' +
                        \'</div><div id="modalTabContent"></div>\';
                    corpo.innerHTML = html;
                    renderTabContent();
                }

                function mudarAbaModal(aba) {
                    _modalState.abaAtiva = aba;
                    renderModalTabs();
                }

                function renderTabContent() {
                    var container = document.getElementById("modalTabContent");
                    if (_modalState.abaAtiva === "alunos") {
                        renderListaAlunos(container);
                    } else {
                        renderListaServidores(container);
                    }
                }

                function emptyStateHtml(tipo) {
                    var msg = tipo === "alunos"
                        ? "Todos os alunos estão com CPF e data de nascimento cadastrados corretamente!"
                        : "Todos os servidores estão com CPF cadastrado corretamente!";
                    return \'<div class="empty-state"><div class="empty-icon">🎉</div><h4>\' + msg + \'</h4></div>\';
                }

                function renderListaAlunos(container) {
                    var lista = _modalState.alunos;
                    if (lista.length === 0) {
                        container.innerHTML = emptyStateHtml("alunos");
                        return;
                    }

                    var pagina = _modalState.paginaAlunos;
                    var totalPaginas = Math.ceil(lista.length / PAGE_SIZE);
                    var inicio = (pagina - 1) * PAGE_SIZE;
                    var pageItems = lista.slice(inicio, inicio + PAGE_SIZE);
                    var temEscola = pageItems.length > 0 && pageItems[0].escola !== undefined;

                    var html = \'<table class="alunos-table"><thead><tr>\';
                    if (temEscola) html += \'<th>Escola</th>\';
                    html += \'<th>Nome do Aluno</th><th>Pendência</th><th style="text-align:center">Ação</th></tr></thead><tbody>\';

                    pageItems.forEach(function(a) {
                        var badges = "";
                        if (a.sem_cpf) badges += \'<span class="pendencia-badge pendencia-cpf">CPF ausente</span>\';
                        if (a.sem_data_nasc) badges += \'<span class="pendencia-badge pendencia-nasc">Data Nasc. ausente</span>\';
                        html += "<tr>";
                        if (temEscola) html += \'<td style="font-size:12px;color:#555;">\' + escapeHtml(a.escola) + "</td>";
                        html += "<td>" + escapeHtml(a.nome) + "</td>" +
                            "<td>" + badges + "</td>" +
                            \'<td style="text-align:center"><button type="button" class="btn-editar-cpf" onclick="editarAluno(\' + a.cod_aluno + \')">✏️ Editar</button></td>\' +
                            "</tr>";
                    });

                    html += "</tbody></table>";
                    html += renderPaginacaoHtml(pagina, totalPaginas, "alunos");
                    container.innerHTML = html;
                }

                function renderListaServidores(container) {
                    var lista = _modalState.servidores;
                    if (lista.length === 0) {
                        container.innerHTML = emptyStateHtml("servidores");
                        return;
                    }

                    var pagina = _modalState.paginaServidores;
                    var totalPaginas = Math.ceil(lista.length / PAGE_SIZE);
                    var inicio = (pagina - 1) * PAGE_SIZE;
                    var pageItems = lista.slice(inicio, inicio + PAGE_SIZE);
                    var temEscola = pageItems.length > 0 && pageItems[0].escola !== undefined;

                    var html = \'<table class="alunos-table"><thead><tr>\';
                    if (temEscola) html += \'<th>Escola</th>\';
                    html += \'<th>Nome do Servidor</th><th>CPF Atual</th><th style="text-align:center">Ação</th></tr></thead><tbody>\';

                    pageItems.forEach(function(s) {
                        html += "<tr>";
                        if (temEscola) html += \'<td style="font-size:12px;color:#555;">\' + escapeHtml(s.escola) + "</td>";
                        html += "<td>" + escapeHtml(s.nome) + "</td>" +
                            \'<td><span class="pendencia-badge pendencia-cpf">\' + escapeHtml(s.cpf) + "</span></td>" +
                            \'<td style="text-align:center"><button type="button" class="btn-editar-cpf" onclick="editarServidor(\' + s.cod_servidor + \')">✏️ Editar CPF</button></td>\' +
                            "</tr>";
                    });

                    html += "</tbody></table>";
                    html += renderPaginacaoHtml(pagina, totalPaginas, "servidores");
                    container.innerHTML = html;
                }

                // ---------------------------------------------------------------
                // Paginação genérica (no máximo 4 botões de página visíveis)
                // ---------------------------------------------------------------
                function renderPaginacaoHtml(paginaAtual, totalPaginas, tipo) {
                    if (totalPaginas <= 1) return "";

                    var start = Math.max(1, paginaAtual - 1);
                    var end   = Math.min(totalPaginas, start + MAX_PAGE_BUTTONS - 1);
                    start     = Math.max(1, end - MAX_PAGE_BUTTONS + 1);

                    var html = \'<div class="modal-pagination">\';
                    html += \'<button type="button" class="pg-btn" \' + (paginaAtual === 1 ? "disabled" : "") +
                        \' onclick="mudarPagina(\\\'\' + tipo + \'\\\', \' + (paginaAtual - 1) + \')">‹</button>\';
                    if (start > 1) html += \'<span class="pg-ellipsis">…</span>\';
                    for (var i = start; i <= end; i++) {
                        html += \'<button type="button" class="pg-btn\' + (i === paginaAtual ? " active" : "") + \'" onclick="mudarPagina(\\\'\' + tipo + \'\\\', \' + i + \')">\' + i + "</button>";
                    }
                    if (end < totalPaginas) html += \'<span class="pg-ellipsis">…</span>\';
                    html += \'<button type="button" class="pg-btn" \' + (paginaAtual === totalPaginas ? "disabled" : "") +
                        \' onclick="mudarPagina(\\\'\' + tipo + \'\\\', \' + (paginaAtual + 1) + \')">›</button>\';
                    html += "</div>";
                    return html;
                }

                function mudarPagina(tipo, novaPagina) {
                    if (tipo === "alunos") {
                        _modalState.paginaAlunos = novaPagina;
                    } else {
                        _modalState.paginaServidores = novaPagina;
                    }
                    renderTabContent();
                }

                function fecharModal() {
                    document.getElementById("modalAlunosSemCPF").style.display = "none";
                }

                function editarAluno(codAluno) {
                    window.location.href = "/module/Cadastro/aluno?id=" + codAluno;
                }

                function editarServidor(codServidor) {
                    window.location.href = "/intranet/atendidos_cad.php?cod_pessoa_fj=" + codServidor;
                }

                function escapeHtml(str) {
                    if (!str) return "";
                    return String(str)
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;");
                }

                // Fecha modal ao clicar fora
                document.getElementById("modalAlunosSemCPF").addEventListener("click", function(e) {
                    if (e.target === this) fecharModal();
                });

                // Mensagem de sucesso
                var successMsg = document.getElementById("successMessage");
                if (successMsg && successMsg.style.display === "block") {
                    setTimeout(function() {
                        successMsg.style.display = "none";
                        window.location.href = window.location.pathname + "?refresh=" + new Date().getTime();
                    }, 3000);
                }
                </script>
                ';
    }

    public function Formular()
    {
        $this->title      = 'Escola';
        $this->processoAp = 55;
    }

    // =========================================================================
    // Métodos privados auxiliares
    // =========================================================================

    private function getCurrentUser()
    {
        try {
            if (Auth::check()) return Auth::user();

            $userId = $_SESSION['cod_usuario'] ?? null;
            if ($userId) return LegacyUser::find($userId);

            return null;
        } catch (\Exception $e) {
            error_log('Erro ao obter usuário atual: ' . $e->getMessage());
            return null;
        }
    }

    private function isAdminUser($user): bool
    {
        if (!$user) return false;

        try {
            if (isset($user->nivel) && $user->nivel == 1) return true;

            return LegacyUserSchool::where('ref_cod_usuario', $user->cod_usuario)->count() === 0;
        } catch (\Exception $e) {
            error_log('Erro ao verificar admin: ' . $e->getMessage());
            return false;
        }
    }

    private function getSchoolsIdByUser($user): ?array
    {
        if (!$user) return null;

        try {
            return LegacyUserSchool::where('ref_cod_usuario', $user->cod_usuario)
                ->pluck('ref_cod_escola')
                ->toArray();
        } catch (\Exception $e) {
            error_log('Erro ao obter escola do usuário: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Monta filtro SQL por lista de escolas.
     *
     * @param  array<int>|null $schoolIds  null = sem filtro; [] = nenhuma
     * @return array{0: string, 1: array<int>}
     */
    private function sqlSchoolIdsInClause(string $columnSql, ?array $schoolIds): array
    {
        if ($schoolIds === null) return ['', []];

        $ids = array_values(array_unique(array_map('intval', $schoolIds)));
        if ($ids === []) return [' AND 1 = 0 ', []];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return [" AND {$columnSql} IN ({$placeholders}) ", $ids];
    }

    /**
     * Busca escolas com métricas já filtradas pelo ano letivo.
     *
     * @param  array<int>|null $schoolIds
     * @param  int             $ano
     * @return array<int, array{cod_escola: int, nome: string, alunos: int, turmas: int, aee: int, total_pendencias: int, alunos_pendentes_json: string, servidores_pendentes_json: string}>
     */
    private function getEscolasComResumo(?array $schoolIds, int $ano): array
    {
        try {
            [$schoolSql, $schoolParams] = $this->sqlSchoolIdsInClause('e.cod_escola', $schoolIds);

            $sql = "SELECT e.cod_escola, p.nome
                    FROM pmieducar.escola e
                    INNER JOIN cadastro.pessoa p ON p.idpes = e.ref_idpes
                    WHERE e.ativo = 1 {$schoolSql}
                    ORDER BY p.nome";

            $escolas = DB::select($sql, $schoolParams);

            $result = [];

            foreach ($escolas as $escola) {
                $id = $escola->cod_escola;

                // Alunos matriculados no ano
                $alunos = DB::select(
                    "SELECT COUNT(DISTINCT a.cod_aluno) as total
                     FROM pmieducar.aluno a
                     INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                     WHERE a.ativo = 1 AND m.ativo = 1 AND m.ano = ? AND m.ref_ref_cod_escola = ?",
                    [$ano, $id]
                );

                // Turmas ativas no ano
                $turmas = DB::select(
                    "SELECT COUNT(cod_turma) as total
                     FROM pmieducar.turma
                     WHERE ativo = 1 AND ano = ? AND visivel = true AND ref_ref_cod_escola = ?",
                    [$ano, $id]
                );

                // Matrículas AEE no ano
                $aee = DB::select(
                    "SELECT COUNT(DISTINCT m.cod_matricula) as total
                     FROM pmieducar.matricula m
                     INNER JOIN pmieducar.matricula_turma mt ON mt.ref_cod_matricula = m.cod_matricula
                     INNER JOIN pmieducar.serie s ON s.cod_serie = m.ref_ref_cod_serie
                     WHERE m.ativo = 1 AND mt.ativo = 1 AND m.ano = ?
                       AND m.ref_ref_cod_escola = ?
                       AND LOWER(s.nm_serie) LIKE '%aee%'",
                    [$ano, $id]
                );

                // Alunos com pendência (CPF inválido/ausente OU data de nascimento ausente) desta escola/ano
                $pendenciaCpfSql = "(
                           f.cpf IS NULL
                           OR f.cpf = 0
                           OR public.formata_cpf(f.cpf) !~ '^[0-9.-]{14}$'
                           OR public.formata_cpf(f.cpf) IN (
                               '000.000.000-00','111.111.111-11','222.222.222-22','333.333.333-33',
                               '444.444.444-44','555.555.555-55','666.666.666-66','777.777.777-77',
                               '888.888.888-88','999.999.999-99'
                           )
                       )";

                $alunosPendentesRows = DB::select(
                    "SELECT DISTINCT
                         a.cod_aluno,
                         p.nome,
                         public.formata_cpf(f.cpf) as cpf_formatado,
                         f.data_nasc
                     FROM pmieducar.aluno a
                     INNER JOIN cadastro.pessoa p   ON p.idpes = a.ref_idpes
                     LEFT JOIN cadastro.fisica f    ON f.idpes = p.idpes
                     INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                     WHERE a.ativo = 1
                       AND m.ativo = 1
                       AND m.ano = ?
                       AND m.ref_ref_cod_escola = ?
                       AND ({$pendenciaCpfSql} OR f.data_nasc IS NULL)
                     ORDER BY p.nome",
                    [$ano, $id]
                );

                // Serializa a lista de alunos com pendência para embutir no HTML (usada pelo modal JS)
                $alunosListaJson = array_map(function ($row) {
                    $cpfFormatado = $this->formatarCPFNumerico($row->cpf_formatado);
                    return [
                        'cod_aluno'      => (int) $row->cod_aluno,
                        'nome'           => $row->nome,
                        'cpf'            => $cpfFormatado,
                        'sem_cpf'        => $this->isCpfInvalido($row->cpf_formatado),
                        'sem_data_nasc'  => empty($row->data_nasc),
                    ];
                }, $alunosPendentesRows);

                // Servidores SEM CPF válido, alocados nesta escola neste ano letivo
                $servidoresPendentesRows = DB::select(
                    "SELECT DISTINCT
                         s.cod_servidor,
                         s.ref_cod_instituicao,
                         p.nome,
                         public.formata_cpf(f.cpf) as cpf_formatado
                     FROM pmieducar.servidor s
                     INNER JOIN pmieducar.servidor_alocacao sa ON sa.ref_cod_servidor = s.cod_servidor
                     INNER JOIN cadastro.pessoa p ON p.idpes = s.cod_servidor
                     LEFT JOIN cadastro.fisica f  ON f.idpes = s.cod_servidor
                     WHERE s.ativo = 1
                       AND sa.ativo = 1
                       AND sa.ano = ?
                       AND sa.ref_cod_escola = ?
                       AND {$pendenciaCpfSql}
                     ORDER BY p.nome",
                    [$ano, $id]
                );

                $servidoresListaJson = array_map(function ($row) {
                    return [
                        'cod_servidor'        => (int) $row->cod_servidor,
                        'ref_cod_instituicao' => (int) $row->ref_cod_instituicao,
                        'nome'                => $row->nome,
                        'cpf'                 => $this->formatarCPFNumerico($row->cpf_formatado),
                    ];
                }, $servidoresPendentesRows);

                $result[] = [
                    'cod_escola'                => $id,
                    'nome'                      => $escola->nome,
                    'alunos'                    => (int) ($alunos[0]->total ?? 0),
                    'turmas'                    => (int) ($turmas[0]->total ?? 0),
                    'aee'                       => (int) ($aee[0]->total  ?? 0),
                    'total_pendencias'          => count($alunosPendentesRows) + count($servidoresPendentesRows),
                    'alunos_pendentes_json'     => json_encode($alunosListaJson, JSON_UNESCAPED_UNICODE),
                    'servidores_pendentes_json' => json_encode($servidoresListaJson, JSON_UNESCAPED_UNICODE),
                ];
            }

            return $result;

        } catch (\Exception $e) {
            error_log('Erro ao buscar escolas com resumo: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Lista de CPFs "placeholder" (dígitos repetidos) considerados inválidos.
     * Mantida em sincronia com o predicado SQL usado nas consultas de pendência.
     */
    private const CPFS_PLACEHOLDER_INVALIDOS = [
        '000.000.000-00', '111.111.111-11', '222.222.222-22', '333.333.333-33',
        '444.444.444-44', '555.555.555-55', '666.666.666-66', '777.777.777-77',
        '888.888.888-88', '999.999.999-99',
    ];

    /**
     * Verifica se um CPF (já formatado por public.formata_cpf) é ausente ou inválido.
     */
    private function isCpfInvalido($cpfFormatado): bool
    {
        if ($cpfFormatado === null || $cpfFormatado === '' || $cpfFormatado === '0') {
            return true;
        }
        if (!preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $cpfFormatado)) {
            return true;
        }
        return in_array($cpfFormatado, self::CPFS_PLACEHOLDER_INVALIDOS, true);
    }

    /**
     * Formata CPF numérico para exibição
     */
    private function formatarCPFNumerico($cpf): string
    {
        if ($cpf === null || $cpf == 0 || $cpf === '' || $cpf === '0') {
            return 'Não informado';
        }
        if (preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $cpf)) {
            return $cpf;
        }
        $cpfString = str_pad(preg_replace('/\D/', '', (string) $cpf), 11, '0', STR_PAD_LEFT);
        if ($cpfString === '00000000000') return '000.000.000-00';

        return substr($cpfString, 0, 3) . '.' .
               substr($cpfString, 3, 3) . '.' .
               substr($cpfString, 6, 3) . '-' .
               substr($cpfString, 9, 2);
    }

    /**
     * Gera o HTML do accordion de escolas — SEM seletor individual de ano.
     * O seletor de ano agora é único e fica no cabeçalho do Resumo Rápido.
     */
    private function gerarAccordionEscolas(array $escolas, int $anoSelecionado): string
    {
        if (empty($escolas)) {
            return '<p style="color:#666; padding: 20px 0;">Nenhuma escola encontrada.</p>';
        }

        $html = '';

        foreach ($escolas as $index => $escola) {
            $idx        = $index;
            $nomeEscola = htmlspecialchars($escola['nome']);
            $alunos     = number_format($escola['alunos'], 0, '', '.');
            $turmas     = number_format($escola['turmas'], 0, '', '.');
            $aee        = number_format($escola['aee'],    0, '', '.');
            $totalPendencias    = $escola['total_pendencias'];
            $totalPendenciasFmt = number_format($totalPendencias, 0, '', '.');

            $badgePendente = $totalPendencias > 0
                ? '<span class="escola-badge-pendente">⚠️ ' . $totalPendenciasFmt . ' pendente(s)</span>'
                : '';

            $alertPendente = $totalPendencias > 0
                ? '<div class="alert-pending"><span class="alert-icon">⚠️</span> Requer atenção</div>'
                : '';

            $html .= '
            <div class="escola-accordion">
                <div class="escola-accordion-header" onclick="toggleEscolaAccordion(this, ' . $idx . ')">
                    <span class="escola-nome">
                        🏫 ' . $nomeEscola . $badgePendente . '
                    </span>
                    <span class="escola-accordion-chevron open" id="chevron-' . $idx . '">▼</span>
                </div>
                <div class="escola-accordion-body open" id="accordion-body-' . $idx . '">

                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-label">Total de Alunos Matriculados</div>
                            <div class="summary-number-container">
                                <div class="summary-number summary-alunos">' . $alunos . '</div>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Turmas Ativas</div>
                            <div class="summary-number-container">
                                <div class="summary-number summary-turmas">' . $turmas . '</div>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Atendimento educacional especializado (AEE)</div>
                            <div class="summary-number-container">
                                <div class="summary-number summary-aee">' . $aee . '</div>
                            </div>
                        </div>
                        <div class="summary-item" style="cursor: pointer;"
                             onclick=\'abrirListaPendencias(' . json_encode($escola['nome']) . ', ' . json_encode($escola['alunos_pendentes_json']) . ', ' . json_encode($escola['servidores_pendentes_json']) . ')\'>
                            <div class="summary-label">Documentos Pendentes</div>
                            <div class="summary-number-container">
                                <div class="summary-number summary-documentos">' . $totalPendenciasFmt . '</div>
                                ' . $alertPendente . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }

        return $html;
    }
};