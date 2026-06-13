<?php
 
use App\Models\LegacyStudent;
use App\Models\LegacyEnrollment;
use App\Models\LegacySchoolClass;
use App\Models\LegacyUser;
use App\Models\LegacyUserSchool;
use App\Models\LegacySchool;
use Illuminate\Support\Facades\DB;
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

        return '
                <link rel="stylesheet" href="styles/educar_index.css">
                
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

                    /* Filtro de ano */
                    .ano-filtro-wrapper {
                        display: flex; align-items: center; gap: 8px;
                        margin-bottom: 14px; flex-wrap: wrap;
                    }
                    .ano-filtro-wrapper label {
                        font-size: 13px; font-weight: 600; color: #555;
                    }
                    .ano-filtro-select {
                        padding: 5px 10px; border: 1px solid #ced4da; border-radius: 6px;
                        font-size: 13px; color: #333; background: #fff; cursor: pointer;
                    }
                    .ano-filtro-select:focus { outline: none; border-color: #007bff; }
                    .btn-filtrar-ano {
                        padding: 5px 14px; background: #007bff; color: white;
                        border: none; border-radius: 6px; font-size: 13px;
                        cursor: pointer; font-weight: 500;
                    }
                    .btn-filtrar-ano:hover { background: #0056b3; }

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
                                    <!--
                                        <li><a href="/module/Reports/MonthlyAbsenceByStudent" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Sistema presença</a></li>
                                    -->
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="quick-summary-section">
                        <h2>Resumo Rápido</h2>
                        ' . $this->gerarAccordionEscolas($escolas, $anoSelecionado, $anosDisponiveis) . '
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
                // Modal com dados ESPECÍFICOS da escola clicada
                // ---------------------------------------------------------------
                function abrirListaAlunosSemCPF(escolaNome, alunosJson) {
                    var alunos = JSON.parse(alunosJson);
                    var modal  = document.getElementById("modalAlunosSemCPF");
                    var titulo = document.getElementById("modalTitulo");
                    var corpo  = document.getElementById("modalCorpo");

                    titulo.textContent = "Alunos sem CPF válido (" + alunos.length + ") — " + escolaNome;

                    if (alunos.length === 0) {
                        corpo.innerHTML = \'<div style="text-align:center;padding:40px;color:#666;">\' +
                            \'<div style="font-size:48px;margin-bottom:10px;">🎉</div>\' +
                            \'<h4>Todos os alunos estão com CPF cadastrado corretamente!</h4></div>\';
                    } else {
                        var html = \'<table class="alunos-table"><thead><tr>\' +
                            \'<th>Nome do Aluno</th><th>CPF Atual</th><th style="text-align:center">Ação</th>\' +
                            \'</tr></thead><tbody>\';

                        alunos.forEach(function(a) {
                            html += \'<tr>\' +
                                \'<td>\' + escapeHtml(a.nome) + \'</td>\' +
                                \'<td><span style="color:#dc3545;font-weight:bold;">\' + escapeHtml(a.cpf) + \'</span></td>\' +
                                \'<td style="text-align:center">\' +
                                \'<button type="button" class="btn-editar-cpf" onclick="editarAluno(\' + a.cod_aluno + \')">✏️ Editar CPF</button>\' +
                                \'</td></tr>\';
                        });

                        html += \'</tbody></table>\';
                        corpo.innerHTML = html;
                    }

                    modal.style.display = "flex";
                }

                function fecharModal() {
                    document.getElementById("modalAlunosSemCPF").style.display = "none";
                }

                function editarAluno(codAluno) {
                    window.location.href = "/module/Cadastro/aluno?id=" + codAluno;
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

                // ---------------------------------------------------------------
                // Filtro de ano letivo por escola
                // ---------------------------------------------------------------
                function filtrarAnoEscola(codEscola, selectEl) {
                    var ano = selectEl.value;
                    var url = window.location.pathname + "?ano=" + ano;
                    window.location.href = url;
                }

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
     * @return array<int, array{cod_escola: int, nome: string, alunos: int, turmas: int, aee: int, sem_cpf: int, alunos_sem_cpf_lista: array}>
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

                // *** CORREÇÃO PRINCIPAL ***
                // Alunos SEM CPF válido APENAS desta escola e neste ano
                $semCpfRows = DB::select(
                    "SELECT DISTINCT
                         a.cod_aluno,
                         p.nome,
                         public.formata_cpf(f.cpf) as cpf_formatado
                     FROM pmieducar.aluno a
                     INNER JOIN cadastro.pessoa p   ON p.idpes = a.ref_idpes
                     INNER JOIN cadastro.fisica f   ON f.idpes = p.idpes
                     INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                     WHERE a.ativo = 1
                       AND m.ativo = 1
                       AND m.ano = ?
                       AND m.ref_ref_cod_escola = ?
                       AND (
                           f.cpf IS NULL
                           OR f.cpf = 0
                           OR public.formata_cpf(f.cpf) !~ '^[0-9.-]{14}$'
                           OR public.formata_cpf(f.cpf) IN (
                               '000.000.000-00','111.111.111-11','222.222.222-22','333.333.333-33',
                               '444.444.444-44','555.555.555-55','666.666.666-66','777.777.777-77',
                               '888.888.888-88','999.999.999-99'
                           )
                       )
                     ORDER BY p.nome",
                    [$ano, $id]
                );

                // Serializa a lista de alunos para embutir no HTML (usada pelo modal JS)
                $alunosListaJson = array_map(function ($row) {
                    return [
                        'cod_aluno' => (int) $row->cod_aluno,
                        'nome'      => $row->nome,
                        'cpf'       => $this->formatarCPFNumerico($row->cpf_formatado),
                    ];
                }, $semCpfRows);

                $result[] = [
                    'cod_escola'          => $id,
                    'nome'                => $escola->nome,
                    'alunos'              => (int) ($alunos[0]->total ?? 0),
                    'turmas'              => (int) ($turmas[0]->total ?? 0),
                    'aee'                 => (int) ($aee[0]->total  ?? 0),
                    'sem_cpf'             => count($semCpfRows),
                    // Lista completa serializada para o modal JS
                    'alunos_sem_cpf_json' => json_encode($alunosListaJson, JSON_UNESCAPED_UNICODE),
                ];
            }

            return $result;

        } catch (\Exception $e) {
            error_log('Erro ao buscar escolas com resumo: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Formata CPF numérico para exibição
     */
    private function formatarCPFNumerico($cpf): string
    {
        if ($cpf === null || $cpf == 0 || $cpf === '' || $cpf === '0') {
            return 'Não informado';
        }
        // Se já veio formatado pela função SQL, devolve direto
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
     * Gera o HTML do accordion de escolas com filtro de ano e modal por escola.
     */
    private function gerarAccordionEscolas(array $escolas, int $anoSelecionado, array $anosDisponiveis): string
    {
        if (empty($escolas)) {
            return '<p style="color:#666; padding: 20px 0;">Nenhuma escola encontrada.</p>';
        }

        // Monta as options do select de ano
        $opcoesAno = '';
        foreach ($anosDisponiveis as $ano) {
            $sel       = $ano === $anoSelecionado ? ' selected' : '';
            $opcoesAno .= '<option value="' . $ano . '"' . $sel . '>' . $ano . '</option>';
        }

        $html = '';

        foreach ($escolas as $index => $escola) {
            $idx        = $index;
            $nomeEscola = htmlspecialchars($escola['nome']);
            $alunos     = number_format($escola['alunos'], 0, '', '.');
            $turmas     = number_format($escola['turmas'], 0, '', '.');
            $aee        = number_format($escola['aee'],    0, '', '.');
            $semCpf     = $escola['sem_cpf'];
            $semCpfFmt  = number_format($semCpf, 0, '', '.');
            $codEscola  = $escola['cod_escola'];

            // JSON escapado para uso inline no atributo onclick
            // json_encode já faz escape de aspas duplas; usamos aspas simples no onclick
            $alunosJsonEscaped = htmlspecialchars($escola['alunos_sem_cpf_json'], ENT_QUOTES, 'UTF-8');

            $badgePendente = $semCpf > 0
                ? '<span class="escola-badge-pendente">⚠️ ' . $semCpfFmt . ' pendente(s)</span>'
                : '';

            $alertPendente = $semCpf > 0
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

                    <!-- Filtro de Ano Letivo -->
                    <div class="ano-filtro-wrapper">
                        <label for="ano-select-' . $idx . '">📅 Ano letivo:</label>
                        <select id="ano-select-' . $idx . '" class="ano-filtro-select"
                                onchange="filtrarAnoEscola(' . $codEscola . ', this)">
                            ' . $opcoesAno . '
                        </select>
                        <small style="color:#888;font-size:11px;">Dados exibidos para: <strong>' . $anoSelecionado . '</strong></small>
                    </div>

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
                        <!-- CORREÇÃO: onclick passa os dados DESTA escola via JSON -->
                        <div class="summary-item" style="cursor: pointer;"
                             onclick=\'abrirListaAlunosSemCPF(' . json_encode($escola['nome']) . ', ' . json_encode($escola['alunos_sem_cpf_json']) . ')\'>
                            <div class="summary-label">Documentos Pendentes</div>
                            <div class="summary-number-container">
                                <div class="summary-number summary-documentos">' . $semCpfFmt . '</div>
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