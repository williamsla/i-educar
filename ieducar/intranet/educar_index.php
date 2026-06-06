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
        // Obter o usuário logado e sua escola
        $user = $this->getCurrentUser();
 
        // Verificar se é administrador
        $isAdmin = $this->isAdminUser($user);
        $schoolsIds = $isAdmin ? null : $this->getSchoolsIdByUser($user);
        // Verificar se veio da edição de CPF via session FLASH
        $cpfAtualizado = Session::has('cpf_atualizado');
        if ($cpfAtualizado) {
            Session::forget('cpf_atualizado');
        }
                
        // Buscar escolas vinculadas ao usuário (ou todas se admin)
        $escolas = $this->getEscolasComResumo($schoolsIds);
 
        // Totais globais para o modal de CPF (soma de todas as escolas)
        $alunosSemCPF = $this->getAlunosSemCPFValido($schoolsIds);
        $quantidadeSemCPF = $alunosSemCPF['quantidade'];
 
        return '<!--
                <table width=\'100%\' style=\'height: 100%;\'>
                    <tr align=center valign=\'top\'>｜<div id=\'flash-container\' align=\'right\' style=\'width: 200px; right: 10px;top: 27px; position: absolute;\'><p style=\'min-height: 0px;\' class=\'flash sucess\'>Olá! Alteramos o menu do lançamento de notas, agora, acesse apenas <strong>Movimentação > Faltas/Notas</strong> e pronto! Qualquer dúvida, entre em contato. :)</p></div>｜2<-->
 
                <link rel="stylesheet" href="styles/educar_index.css">
                
                <style>
                    .item-bullet {
                        margin-right: 8px;
                        color: #007bff;
                    }
                    
                    .document-link {
                        cursor: pointer;
                    }
                    
                    .document-link:hover {
                        color: #007bff;
                    }
                    
                    .menu-principal {
                        cursor: pointer;
                        font-weight: 500;
                        padding: 4px 0;
                        display: block;
                        color: #333;
                        width: 100%;
                        text-align: left;
                    }
                    
                    .menu-principal:hover {
                        color: #007bff;
                    }
                    
                    .submenu-items {
                        display: none;
                        margin: 0;
                        padding: 0;
                        list-style: none;
                        width: 100%;
                    }
                    
                    .submenu-items.show {
                        display: block;
                    }
                    
                    .submenu-items li {
                        padding: 4px 0;
                        list-style: none;
                        margin-left: 0;
                        width: 100%;
                    }
                    
                    .submenu-items li a {
                        text-decoration: none;
                        color: #555;
                        font-size: 12px;
                        display: block;
                        padding: 2px 0;
                        width: 100%;
                    }
                    
                    .submenu-items li a:hover {
                        color: #007bff;
                    }
                    
                    .card-content ul li {
                        display: block;
                        width: 100%;
                    }
                    
                    .success-message {
                        background-color: #d4edda;
                        color: #155724;
                        padding: 10px;
                        border-radius: 4px;
                        margin-bottom: 15px;
                        border: 1px solid #c3e6cb;
                        display: none;
                    }
                    
                    .alunos-table {
                        width: 100%;
                        border-collapse: collapse;
                    }
                    
                    .alunos-table th {
                        padding: 12px;
                        text-align: left;
                        border-bottom: 2px solid #dee2e6;
                        background: #f8f9fa;
                    }
                    
                    .alunos-table td {
                        padding: 12px;
                        border-bottom: 1px solid #f0f0f0;
                    }
                    
                    .btn-editar-cpf {
                        background: #007bff;
                        color: white;
                        text-decoration: none;
                        padding: 8px 16px;
                        border-radius: 4px;
                        display: inline-block;
                        font-size: 12px;
                        border: none;
                        cursor: pointer;
                    }
                    
                    .btn-editar-cpf:hover {
                        background: #0056b3;
                    }
                    
                    .alert-pending {
                        margin-top: 10px;
                        padding: 6px 12px;
                        background: #dc3545;
                        color: white;
                        border-radius: 20px;
                        font-size: 12px;
                        font-weight: bold;
                        display: inline-block;
                    }
                    
                    .alert-icon {
                        font-size: 14px;
                        margin-right: 4px;
                    }
                    
                    .summary-number-container {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                    }
 
                    /* Accordion de escola */
                    .escola-accordion {
                        margin-bottom: 12px;
                        border: 1px solid #e0e0e0;
                        border-radius: 10px;
                        overflow: hidden;
                        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
                    }
 
                    .escola-accordion-header {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        padding: 14px 20px;
                        background: #f8f9fa;
                        cursor: pointer;
                        user-select: none;
                        transition: background 0.2s;
                    }
 
                    .escola-accordion-header:hover {
                        background: #eef2ff;
                    }
 
                    .escola-accordion-header .escola-nome {
                        font-weight: 600;
                        font-size: 14px;
                        color: #333;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }
 
                    .escola-accordion-chevron {
                        font-size: 12px;
                        color: #666;
                        transition: transform 0.3s;
                        display: inline-block;
                    }
 
                    .escola-accordion-chevron.open {
                        transform: rotate(180deg);
                    }
 
                    .escola-accordion-body {
                        display: block;
                        overflow: hidden;
                        max-height: 0;
                        transition: max-height 0.35s ease, padding 0.2s;
                        padding: 0 20px;
                        background: #fff;
                    }
 
                    .escola-accordion-body.open {
                        max-height: 300px;
                        padding: 16px 20px;
                    }
 
                    .escola-badge-pendente {
                        background: #dc3545;
                        color: white;
                        border-radius: 12px;
                        font-size: 11px;
                        font-weight: bold;
                        padding: 2px 8px;
                        margin-left: 8px;
                    }
                </style>
 
                <div class="dashboard-container">
                    <div class="welcome-section">
                        <h1>Bem-vindo ao i-Educar</h1>
                        ' . '<p style="color: #666; margin-top: 5px; font-size: 14px;">Exibindo dados das escolas vinculadas ao seu perfil administrativo</p>' . '
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
 
                        <!-- NOVO CARD DE VERIFICAR ALUNOS DUPLICADOS -->
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
 
                        <!-- NOVO CARD DE RELATÓRIOS -->
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
                                    <li><a href="#" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Quantitativo de matrículas</a></li>
                                    <li><a href="/module/Reports/MonthlyAbsenceByStudent" style="text-decoration: none; color: inherit; display: block;"><span class="item-bullet">•</span> Sistema presença</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
 
                    <div class="quick-summary-section">
                        <h2>Resumo Rápido</h2>
                        ' . $this->gerarAccordionEscolas($escolas) . '
                    </div>
                </div>
 
                <!-- Modal para lista de alunos sem CPF -->
                <div id="modalAlunosSemCPF" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
                    <div style="background: white; border-radius: 12px; width: 90%; max-width: 800px; max-height: 80vh; overflow: hidden;">
                        <div style="padding: 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; color: #333;">Alunos sem CPF válido (<span id="modalCount">' . $quantidadeSemCPF . '</span>)</h3>
                            <button onclick="fecharModal()" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                        </div>
                        <div style="padding: 20px; max-height: 60vh; overflow-y: auto;">
                            ' . $this->gerarListaAlunosSemCPF($alunosSemCPF['alunos']) . '
                        </div>
                        <div style="padding: 15px 20px; border-top: 1px solid #f0f0f0; text-align: right;">
                            <button onclick="fecharModal()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Fechar</button>
                        </div>
                    </div>
                </div>
 
                <script>
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
                    if (submenu.classList.contains("show")) {
                        submenu.classList.remove("show");
                    } else {
                        submenu.classList.add("show");
                    }
                }
 
                function abrirListaAlunosSemCPF() {
                    document.getElementById("modalAlunosSemCPF").style.display = "flex";
                }
 
                function fecharModal() {
                    document.getElementById("modalAlunosSemCPF").style.display = "none";
                }
 
                function editarAluno(codAluno) {
                    window.location.href = "/module/Cadastro/aluno?id=" + codAluno;
                }
 
                // Esconder mensagem de sucesso e recarregar
                var successMsg = document.getElementById("successMessage");
                if (successMsg && successMsg.style.display === "block") {
                    setTimeout(function() {
                        successMsg.style.display = "none";
                        window.location.href = window.location.pathname + "?refresh=" + new Date().getTime();
                    }, 3000);
                }
 
                window.onclick = function(event) {
                    var modal = document.getElementById("modalAlunosSemCPF");
                    if (event.target === modal) {
                        fecharModal();
                    }
                }
                </script>
                ';
    }
 
    public function Formular()
    {
        $this->title = 'Escola';
        $this->processoAp = 55;
    }
 
    private function getCurrentUser()
    {
        try {
            if (Auth::check()) {
                return Auth::user();
            }
            
            if (isset($_SESSION['id_pessoa']) || isset($_SESSION['cod_usuario'])) {
                $userId = $_SESSION['cod_usuario'] ?? null;
                if ($userId) {
                    return LegacyUser::find($userId);
                }
            }
            
            return null;
        } catch (\Exception $e) {
            error_log('Erro ao obter usuário atual: ' . $e->getMessage());
            return null;
        }
    }
 
    private function isAdminUser($user)
    {
        if (!$user) {
            return false;
        }
 
        try {
            if (isset($user->nivel) && $user->nivel == 1) {
                return true;
            }
            
            $schoolsCount = LegacyUserSchool::where('ref_cod_usuario', $user->cod_usuario)->count();
            
            if ($schoolsCount == 0) {
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            error_log('Erro ao verificar admin: ' . $e->getMessage());
            return false;
        }
    }
 
    private function getSchoolsIdByUser($user)
    {
        if (!$user) {
            return null;
        }
 
        try {
            
            $schools = LegacyUserSchool::where('ref_cod_usuario', $user->cod_usuario)->get();
            $schoolsIds = $schools->pluck('ref_cod_escola')->toArray();
            return $schoolsIds;
 
        } catch (\Exception $e) {
            error_log('Erro ao obter escola do usuário: ' . $e->getMessage());
            return null;
        }
    }
 
    private function getAlunosSemCPFValido($schoolIds = null)
    {
        try {
            [$schoolSql, $schoolParams] = $this->sqlSchoolIdsInClause('m.ref_ref_cod_escola', $schoolIds);
            $sql = "SELECT DISTINCT
                        a.cod_aluno,
                        p.nome,
                        public.formata_cpf(f.cpf) as cpf,
                        m.ref_ref_cod_escola as escola_id
                    FROM pmieducar.aluno a
                    INNER JOIN cadastro.pessoa p
                        ON p.idpes = a.ref_idpes
                    INNER JOIN cadastro.fisica f
                        ON f.idpes = p.idpes
                    INNER JOIN pmieducar.matricula m
                        ON m.ref_cod_aluno = a.cod_aluno
                    WHERE a.ativo = 1
                    AND m.ativo = 1
                    AND m.ano = ?
                    {$schoolSql}
                    AND (
                        f.cpf IS null
                        OR f.cpf = 0
					    OR public.formata_cpf(f.cpf) !~ '^[0-9.-]{14}$' -- não tem exatamente 11 dígitos
					    OR public.formata_cpf(f.cpf) IN (
					        '000.000.000-00','111.111.111-11','222.222.222-22','333.333.333-33',
					        '444.444.444-44','555.555.555-55','666.666.666-66','777.777.777-77',
					        '888.888.888-88','999.999.999-99'
					    )
                    )
                    ORDER BY p.nome";
 
            $params = array_merge([date('Y')], $schoolParams);
 
            $result = DB::select($sql, $params);
 
            $alunos = collect($result)->map(function ($item) {
                return [
                    'cod_aluno' => $item->cod_aluno,
                    'nome' => $item->nome,
                    'cpf' => $this->formatarCPFNumerico($item->cpf)
                ];
            });
 
            return [
                'quantidade' => count($result),
                'alunos' => $alunos
            ];
 
        } catch (\Exception $e) {
            error_log('Erro ao buscar alunos sem CPF: ' . $e->getMessage());
            return ['quantidade' => 0, 'alunos' => []];
        }
    }
 
    /**
     * Obtém matrículas AEE
     */
    private function getMatriculasAEE($schoolIds = null)
    {
        try {
            [$schoolSql, $schoolParams] = $this->sqlSchoolIdsInClause('m.ref_ref_cod_escola', $schoolIds);
            $sql = "SELECT COUNT(DISTINCT m.cod_matricula) as total
                    FROM pmieducar.matricula m
                    INNER JOIN pmieducar.matricula_turma mt ON mt.ref_cod_matricula = m.cod_matricula
                    INNER JOIN pmieducar.serie s ON s.cod_serie = m.ref_ref_cod_serie
                    WHERE m.ativo = 1
                    AND mt.ativo = 1
                    AND m.ano = ?
                    {$schoolSql}
                    AND (
                        LOWER(s.nm_serie) LIKE '%aee%'
                    )";
 
            $params = array_merge([date('Y')], $schoolParams);
 
            $result = DB::select($sql, $params);
            return $result[0]->total ?? 0;
 
        } catch (\Exception $e) {
            error_log('Erro ao buscar matrículas AEE: ' . $e->getMessage());
            return 0;
        }
    }
 
    /**
     * Formata CPF numérico para exibição
     */
    private function formatarCPFNumerico($cpfNumerico)
    {
        if ($cpfNumerico === null || $cpfNumerico == 0) {
            return 'Não informado';
        }
        $cpfString = str_pad((string)$cpfNumerico, 11, '0', STR_PAD_LEFT);
        if ($cpfString === '00000000000') {
            return '000.000.000-00';
        }
        return substr($cpfString, 0, 3) . '.' . substr($cpfString, 3, 3) . '.' . substr($cpfString, 6, 3) . '-' . substr($cpfString, 9, 2);
    }
 
    private function gerarListaAlunosSemCPF($alunos)
    {
        if (empty($alunos)) {
            return '
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 10px;">🎉</div>
                    <h4>Todos os alunos estão com CPF cadastrado corretamente!</h4>
                </div>
            ';
        }
 
        $html = '
            <table class="alunos-table">
                <thead>
                    <tr>
                        <th>Nome do Aluno</th>
                        <th>CPF Atual</th>
                        <th style="text-align: center">Ação</th>
                    </tr>
                </thead>
                <tbody>';
 
        foreach ($alunos as $aluno) {
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($aluno['nome']) . '</td>
                        <td><span style="color: #dc3545; font-weight: bold;">' . $aluno['cpf'] . '</span></td>
                        <td style="text-align: center">
                            <button type="button" 
                                    class="btn-editar-cpf" 
                                    onclick="editarAluno(' . $aluno['cod_aluno'] . ')">
                                ✏️ Editar CPF
                            </button>
                        </td>
                    </tr>';
        }
 
        $html .= '
                </tbody>
             <td>';
 
        return $html;
    }
 
    private function getTotalAlunosMatriculados($schoolIds = null)
    {
        try {
            $year = (int) date('Y');
            [$schoolSql, $schoolParams] = $this->sqlSchoolIdsInClause('m.ref_ref_cod_escola', $schoolIds);
            $sql = "SELECT COUNT(DISTINCT a.cod_aluno) as total 
                    FROM pmieducar.aluno a
                    INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                    WHERE a.ativo = 1 AND m.ativo = 1 AND m.ano = ?{$schoolSql}";
            $params = array_merge([$year], $schoolParams);
            $result = DB::select($sql, $params);
 
            return $result[0]->total ?? 0;
        } catch (\Exception $e) {
            error_log('Erro ao buscar total de alunos: ' . $e->getMessage());
            return 0;
        }
    }
 
    private function getTotalTurmasAtivas($schoolIds = null)
    {
        try {
            $year = (int) date('Y');
            [$schoolSql, $schoolParams] = $this->sqlSchoolIdsInClause('ref_ref_cod_escola', $schoolIds);
            $sql = "SELECT COUNT(cod_turma) as total 
                    FROM pmieducar.turma 
                    WHERE ativo = 1 AND ano = ? AND visivel = true{$schoolSql}";
            $params = array_merge([$year], $schoolParams);
            $result = DB::select($sql, $params);
 
            return $result[0]->total ?? 0;
        } catch (\Exception $e) {
            error_log('Erro ao buscar total de turmas: ' . $e->getMessage());
            return 0;
        }
    }
 
    /**
     * Monta filtro SQL por lista de escolas com bind seguro (não use implode na mão em IN).
     *
     * @param  array<int>|null  $schoolIds  null = sem filtro; [] = nenhuma escola (resultado vazio)
     * @return array{0: string, 1: array<int>}
     */
    private function sqlSchoolIdsInClause(string $columnSql, ?array $schoolIds): array
    {
        if ($schoolIds === null) {
            return ['', []];
        }
 
        $ids = array_values(array_unique(array_map('intval', $schoolIds)));
        if ($ids === []) {
            return [' AND 1 = 0 ', []];
        }
 
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
 
        return [" AND {$columnSql} IN ({$placeholders}) ", $ids];
    }
 
    /**
     * Busca todas as escolas vinculadas ao usuário (ou todas se admin)
     * e retorna os dados de resumo de cada uma.
     *
     * @param  array<int>|null  $schoolIds
     * @return array<int, array{cod_escola: int, nome: string, alunos: int, turmas: int, aee: int, sem_cpf: int}>
     */
    private function getEscolasComResumo(?array $schoolIds): array
    {
        try {
            [$schoolSql, $schoolParams] = $this->sqlSchoolIdsInClause('e.cod_escola', $schoolIds);
 
            $sql = "SELECT
                        e.cod_escola,
                        p.nome
                    FROM pmieducar.escola e
                    INNER JOIN cadastro.pessoa p ON p.idpes = e.ref_idpes
                    WHERE e.ativo = 1
                    {$schoolSql}
                    ORDER BY p.nome";
 
            $escolas = DB::select($sql, $schoolParams);
 
            $ano = (int) date('Y');
            $result = [];
 
            foreach ($escolas as $escola) {
                $id = $escola->cod_escola;
 
                // Alunos matriculados
                $alunos = DB::select(
                    "SELECT COUNT(DISTINCT a.cod_aluno) as total
                     FROM pmieducar.aluno a
                     INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                     WHERE a.ativo = 1 AND m.ativo = 1 AND m.ano = ? AND m.ref_ref_cod_escola = ?",
                    [$ano, $id]
                );
 
                // Turmas ativas
                $turmas = DB::select(
                    "SELECT COUNT(cod_turma) as total
                     FROM pmieducar.turma
                     WHERE ativo = 1 AND ano = ? AND visivel = true AND ref_ref_cod_escola = ?",
                    [$ano, $id]
                );
 
                // Matrículas AEE
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
 
                // Alunos sem CPF válido
                $semCpf = DB::select(
                    "SELECT COUNT(DISTINCT a.cod_aluno) as total
                     FROM pmieducar.aluno a
                     INNER JOIN cadastro.pessoa p ON p.idpes = a.ref_idpes
                     INNER JOIN cadastro.fisica f ON f.idpes = p.idpes
                     INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                     WHERE a.ativo = 1 AND m.ativo = 1 AND m.ano = ?
                       AND m.ref_ref_cod_escola = ?
                       AND (
                           f.cpf IS NULL OR f.cpf = 0
                           OR public.formata_cpf(f.cpf) !~ '^[0-9.-]{14}$'
                           OR public.formata_cpf(f.cpf) IN (
                               '000.000.000-00','111.111.111-11','222.222.222-22','333.333.333-33',
                               '444.444.444-44','555.555.555-55','666.666.666-66','777.777.777-77',
                               '888.888.888-88','999.999.999-99'
                           )
                       )",
                    [$ano, $id]
                );
 
                $result[] = [
                    'cod_escola' => $id,
                    'nome'       => $escola->nome,
                    'alunos'     => (int) ($alunos[0]->total ?? 0),
                    'turmas'     => (int) ($turmas[0]->total ?? 0),
                    'aee'        => (int) ($aee[0]->total ?? 0),
                    'sem_cpf'    => (int) ($semCpf[0]->total ?? 0),
                ];
            }
 
            return $result;
 
        } catch (\Exception $e) {
            error_log('Erro ao buscar escolas com resumo: ' . $e->getMessage());
            return [];
        }
    }
 
    /**
     * Gera o HTML do accordion de resumo por escola.
     */
    private function gerarAccordionEscolas(array $escolas): string
    {
        if (empty($escolas)) {
            return '<p style="color:#666; padding: 20px 0;">Nenhuma escola encontrada.</p>';
        }
 
        $html = '';
 
        foreach ($escolas as $index => $escola) {
            $idx        = $index;
            $nomeEscola = htmlspecialchars($escola['nome']);
            $alunos     = number_format($escola['alunos'],  0, '', '.');
            $turmas     = number_format($escola['turmas'],  0, '', '.');
            $aee        = number_format($escola['aee'],     0, '', '.');
            $semCpf     = $escola['sem_cpf'];
            $semCpfFmt  = number_format($semCpf, 0, '', '.');
 
            $badgePendente = $semCpf > 0
                ? '<span class="escola-badge-pendente">⚠️ ' . $semCpfFmt . ' pendente(s)</span>'
                : '';
 
            $alertPendente = $semCpf > 0
                ? '<div class="alert-pending" style="margin-top:8px;"><span class="alert-icon">⚠️</span> Requer atenção</div>'
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
                        <div class="summary-item" onclick="abrirListaAlunosSemCPF()" style="cursor: pointer;">
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