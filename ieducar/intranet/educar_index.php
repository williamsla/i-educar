<?php

use App\Models\LegacyStudent;
use App\Models\LegacyEnrollment;
use App\Models\LegacySchoolClass;
use App\Models\LegacyUser;
use App\Models\LegacyUserSchool;
use App\Models\LegacySchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

return new class
{
    public function RenderHTML()
    {
        // Obter o usuário logado e sua escola
        $user = $this->getCurrentUser();
        $schoolId = $this->getUserSchoolId($user);
        
        // Buscar dados filtrados por escola
        $totalAlunos = $this->getTotalAlunosMatriculados($schoolId);
        $totalTurmasAtivas = $this->getTotalTurmasAtivas($schoolId);
        $alunosSemCPF = $this->getAlunosSemCPFValido($schoolId);
        $matriculasAEE = $this->getMatriculasAEE($schoolId);

        // Obter nome da escola para exibição
        $schoolName = $this->getSchoolName($schoolId);

        return '<!--
                <table width=\'100%\' style=\'height: 100%;\'>
                    <tr align=center valign=\'top\'>｜<div id=\'flash-container\' align=\'right\' style=\'width: 200px; right: 10px;top: 27px; position: absolute;\'><p style=\'min-height: 0px;\' class=\'flash sucess\'>Olá! Alteramos o menu do lançamento de notas, agora, acesse apenas <strong>Movimentação > Faltas/Notas</strong> e pronto! Qualquer dúvida, entre em contato. :)</p></div>｜2<-->

                <link rel="stylesheet" href="styles/educar_index.css">

                <div class="dashboard-container">
                    <div class="welcome-section">
                        <h1>Bem-vindo ao i-Educar</h1>
                        ' . ($schoolName ? '<p style="color: #666; margin-top: 5px; font-size: 16px;">Escola: ' . htmlspecialchars($schoolName) . '</p>' : '') . '
                    </div>

                    <div class="cards-grid">
                        <!-- Card Matrículas -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-matriculas">
                                    <div class="card-icon">📋</div>
                                </div>
                                <h2>Matrículas</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li><span class="item-bullet">•</span>Nova matrícula</li>
                                    <li><a href="/module/Cadastro/aluno" style="text-decoration: none; color: inherit;"><span class="item-bullet">•</span>Novo aluno</a></li>
                                    <li><a href="/intranet/educar_aluno_lst.php" style="text-decoration: none; color: inherit;"><span class="item-bullet">•</span>Consultar alunos</a></li>
                                    <li><a href="/module/Reports/StudentSheet" style="text-decoration: none; color: inherit;"><span class="item-bullet">•</span>Requerimento de matrícula</a></li>
                                    <li><span class="item-bullet">•</span>Transferência de aluno</li>
                                    <li><span class="item-bullet">•</span>Trocar aluno de turma</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Card Boletins -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-boletins">
                                    <div class="card-icon">📊</div>
                                </div>
                                <h2>Boletins</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li><span class="item-bullet">•</span>Boletim numérico</li>
                                    <li><span class="item-bullet">•</span>Boletim conceitual</li>
                                    <li><a href="/module/Reports/TeacherReportCard" style="text-decoration: none; color: inherit;"><span class="item-bullet">•</span>Boletim do professor</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Card Documentos -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-documentos">
                                    <div class="card-icon">📄</div>
                                </div>
                                <h2>Documentos</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li><span class="item-bullet">•</span>Informar histórico de anos anteriores</li>
                                    <li><span class="item-bullet">•</span>Imprimir histórico</li>
                                    <li><span class="item-bullet">•</span>Ata de resultado final</li>
                                    <li><span class="item-bullet">•</span>Ficha Individual</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Card Declarações -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-declaracoes">
                                    <div class="card-icon">📜</div>
                                </div>
                                <h2>Declarações</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li><a href="/module/Reports/TransferenceCertificate" style="text-decoration: none; color: inherit;"><span class="item-bullet">•</span>Declaração de transferência</a></li>
                                    <li><a href="/module/Reports/FrequencyCertificate" style="text-decoration: none; color: inherit;"><span class="item-bullet">•</span>Declaração de frequência</a></li>
                                    <li><a href="/module/Reports/ConclusionCertificate" style="text-decoration: none; color: inherit;"><span class="item-bullet">•</span>Declaração de conclusão</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Card Movimentação -->
                        <a href=\'../module/Avaliacao/diario\' style=\'text-decoration: none; color: inherit;\'><div class="card">
                            <div class="card-header">
                                <div class="card-icon-wrapper icon-movimentacao">
                                    <div class="card-icon">🔄</div>
                                </div>
                                <h2>Movimentação</h2>
                            </div>
                            <div class="card-content">
                                <ul>
                                    <li><span class="item-bullet">•</span>Lançar notas recebidas de outra escola</li>
                                </ul>
                            </div>
                        </div></a>
                    </div>

                    <div class="quick-summary-section">
                        <h2>Resumo Rápido - ' . ($schoolName ? htmlspecialchars($schoolName) : 'Sua Escola') . '</h2>
                        <div class="summary-grid">
                            <!-- Resumo Alunos -->
                            <div class="summary-item">
                                <div class="summary-label">Total de Alunos Matriculados</div>
                                <div class="summary-number-container">
                                    <div class="summary-number summary-alunos">' . number_format($totalAlunos, 0, '', '.') . '</div>
                                </div>
                            </div>
                            <!-- Resumo Turmas -->
                            <div class="summary-item">
                                <div class="summary-label">Turmas Ativas</div>
                                <div class="summary-number-container">
                                    <div class="summary-number summary-turmas">' . number_format($totalTurmasAtivas, 0, '', '.') . '</div>
                                </div>
                            </div>
                            <!-- Resumo AEE -->
                            <div class="summary-item">
                                <div class="summary-label">Atendimento educacional especializado (AEE)</div>
                                <div class="summary-number-container">
                                    <div class="summary-number summary-aee">' . number_format($matriculasAEE, 0, '', '.') . '</div>
                                </div>
                            </div>
                            <!-- Resumo Documentos Pendentes com Alerta -->
                            <div class="summary-item" onclick="abrirListaAlunosSemCPF()" style="cursor: pointer;">
                                <div class="summary-label">Documentos Pendentes</div>
                                <div class="summary-number-container">
                                    <div class="summary-number summary-documentos">' . number_format($alunosSemCPF['quantidade'], 0, '', '.') . '</div>
                                    ' . ($alunosSemCPF['quantidade'] > 0 ? '
                                    <div class="alert-pending">
                                        <span class="alert-icon">▲</span> Requer atenção
                                    </div>' : '') . '
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal para lista de alunos sem CPF -->
                <div id="modalAlunosSemCPF" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
                    <div style="background: white; border-radius: 12px; width: 90%; max-width: 800px; max-height: 80vh; overflow: hidden;">
                        <div style="padding: 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; color: #333;">Alunos sem CPF válido (' . $alunosSemCPF['quantidade'] . ')</h3>
                            <button onclick="fecharModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666;">×</button>
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
                function abrirListaAlunosSemCPF() {
                    document.getElementById(\'modalAlunosSemCPF\').style.display = \'flex\';
                }

                function fecharModal() {
                    document.getElementById(\'modalAlunosSemCPF\').style.display = \'none\';
                }

                function editarAluno(codAluno) {
                    // Redireciona para a página de detalhes do aluno
                    window.location.href = \'/intranet/educar_aluno_det.php?cod_aluno=\' + codAluno + \'&origem=index_cpf\';
                }

                // Fechar modal ao clicar fora
                document.addEventListener(\'click\', function(event) {
                    const modal = document.getElementById(\'modalAlunosSemCPF\');
                    if (event.target === modal) {
                        fecharModal();
                    }
                });

                // Função para recarregar a página após a correção do CPF
                function recarregarPagina() {
                    // Remove o parâmetro \'cpf_atualizado\' da URL para evitar loop
                    const url = new URL(window.location.href);
                    url.searchParams.delete(\'cpf_atualizado\');
                    window.location.href = url.toString();
                }

                // Verifica se a página foi recarregada após a correção do CPF
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has(\'cpf_atualizado\') && urlParams.get(\'cpf_atualizado\') === \'true\') {
                    // Se o CPF foi atualizado, recarrega a página para buscar a lista atualizada
                    // Uma solução mais elegante seria via AJAX, mas para manter a estrutura, vamos recarregar.
                    recarregarPagina();
                }
                </script>
                ';
    }

    public function Formular()
    {
        $this->title = 'Escola';
        $this->processoAp = 55;
    }

    /**
     * Obtém o usuário atual logado
     */
    private function getCurrentUser()
    {
        try {
            // Tentativa 1: Usar Auth do Laravel
            if (Auth::check()) {
                return Auth::user();
            }
            
            // Tentativa 2: Buscar via session ou método alternativo
            if (isset($_SESSION['id_pessoa']) || isset($_SESSION['cod_usuario'])) {
                $userId = $_SESSION['cod_usuario'] ?? null;
                if ($userId) {
                    return LegacyUser::find($userId);
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Erro ao obter usuário atual: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtém o ID da escola do usuário
     */
    private function getUserSchoolId($user)
    {
        if (!$user) {
            return null;
        }

        try {
            // Buscar a escola atual do usuário
            $userSchool = LegacyUserSchool::where('ref_cod_usuario', $user->cod_usuario)
                ->where('escola_atual', 1)
                ->first();

            if ($userSchool) {
                return $userSchool->ref_cod_escola;
            }

            // Se não encontrou escola atual, buscar a primeira escola do usuário
            $firstSchool = LegacyUserSchool::where('ref_cod_usuario', $user->cod_usuario)
                ->first();

            return $firstSchool ? $firstSchool->ref_cod_escola : null;

        } catch (Exception $e) {
            error_log('Erro ao obter escola do usuário: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtém o nome da escola
     */
    private function getSchoolName($schoolId)
    {
        if (!$schoolId) {
            return null;
        }

        try {
            // NOTA: No i-Educar, a tabela de escolas pode não ter o campo "name"
            // Vamos tentar diferentes campos possíveis
            $school = LegacySchool::find($schoolId);
            
            if (!$school) {
                return null;
            }
            
            // Tentar diferentes campos que podem conter o nome da escola
            if (isset($school->name) && !empty($school->name)) {
                return $school->name;
            }
            
            if (isset($school->nome) && !empty($school->nome)) {
                return $school->nome;
            }
            
            if (isset($school->nm_escola) && !empty($school->nm_escola)) {
                return $school->nm_escola;
            }
            
            return 'Escola #' . $schoolId;
            
        } catch (Exception $e) {
            error_log('Erro ao obter nome da escola: ' . $e->getMessage());
            return 'Escola #' . $schoolId;
        }
    }

    /**
     * Obtém alunos sem CPF válido - CORRIGIDO para coluna numeric
     */
    private function getAlunosSemCPFValido($schoolId = null)
    {
        try {
            // Como a coluna cpf é numeric, não podemos comparar com string vazia
            // Vamos buscar CPFs que são zero, nulos ou inválidos
            $sql = "SELECT 
                        a.cod_aluno,
                        p.nome,
                        f.cpf,
                        m.ref_ref_cod_escola as escola_id
                    FROM pmieducar.aluno a
                    INNER JOIN cadastro.pessoa p ON p.idpes = a.ref_idpes
                    INNER JOIN cadastro.fisica f ON f.idpes = p.idpes
                    INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                    WHERE a.ativo = 1
                    AND m.ativo = 1
                    AND m.ano = ?
                    " . ($schoolId ? " AND m.ref_ref_cod_escola = ? " : "") . "
                    AND (
                        f.cpf IS NULL 
                        OR f.cpf = 0 
                        OR f.cpf = 00000000000
                        OR CAST(f.cpf AS TEXT) LIKE '000%'
                        OR LENGTH(CAST(f.cpf AS TEXT)) < 11
                    )
                    GROUP BY a.cod_aluno, p.nome, f.cpf, m.ref_ref_cod_escola
                    ORDER BY p.nome";

            $params = [date('Y')];
            if ($schoolId) {
                $params[] = $schoolId;
            }

            $result = DB::select($sql, $params);
            
            $alunos = collect($result)->map(function ($item) {
                return [
                    'cod_aluno' => $item->cod_aluno,
                    'nome' => $item->nome,
                    'cpf' => $this->formatarCPFNumerico($item->cpf),
                    'escola_id' => $item->escola_id
                ];
            });

            return [
                'quantidade' => count($result),
                'alunos' => $alunos
            ];

        } catch (Exception $e) {
            error_log('Erro ao buscar alunos sem CPF válido: ' . $e->getMessage());
            
            // Fallback mais simples
            return $this->getAlunosSemCPFValidoFallback($schoolId);
        }
    }

    /**
     * Método fallback simplificado para coluna numeric
     */
    private function getAlunosSemCPFValidoFallback($schoolId = null)
    {
        try {
            // Consulta mais simples focando apenas em CPF zero ou nulo
            $sql = "SELECT 
                        a.cod_aluno,
                        p.nome,
                        f.cpf
                    FROM pmieducar.aluno a
                    INNER JOIN cadastro.pessoa p ON p.idpes = a.ref_idpes
                    INNER JOIN cadastro.fisica f ON f.idpes = p.idpes
                    INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                    WHERE a.ativo = 1
                    AND m.ativo = 1
                    AND m.ano = ?
                    " . ($schoolId ? " AND m.ref_ref_cod_escola = ? " : "") . "
                    AND (f.cpf IS NULL OR f.cpf = 0 OR f.cpf = 00000000000)
                    ORDER BY p.nome";

            $params = [date('Y')];
            if ($schoolId) {
                $params[] = $schoolId;
            }

            $result = DB::select($sql, $params);
            
            $alunos = collect($result)->map(function ($item) {
                return [
                    'cod_aluno' => $item->cod_aluno,
                    'nome' => $item->nome,
                    'cpf' => $this->formatarCPFNumerico($item->cpf),
                    'escola_id' => null
                ];
            });

            return [
                'quantidade' => count($result),
                'alunos' => $alunos
            ];

        } catch (Exception $e) {
            error_log('Erro no fallback de alunos sem CPF: ' . $e->getMessage());
            
            // Último fallback - apenas contar
            $sql = "SELECT COUNT(DISTINCT a.cod_aluno) as total
                    FROM pmieducar.aluno a
                    INNER JOIN cadastro.fisica f ON f.idpes = a.ref_idpes
                    INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                    WHERE a.ativo = 1
                    AND m.ativo = 1 
                    AND m.ano = ?
                    " . ($schoolId ? " AND m.ref_ref_cod_escola = ? " : "") . "
                    AND (f.cpf IS NULL OR f.cpf = 0 OR f.cpf = 00000000000)";

            $params = [date('Y')];
            if ($schoolId) {
                $params[] = $schoolId;
            }

            $result = DB::select($sql, $params);
            
            return [
                'quantidade' => $result[0]->total ?? 0,
                'alunos' => []
            ];
        }
    }

    /**
     * Obtém o quantitativo de alunos matriculados no Atendimento Educacional Especializado (AEE)
     * CORRIGIDO - Busca alunos em turmas com tipo_atendimento = 2 (AEE)
     */
    private function getMatriculasAEE($schoolId = null)
    {
        try {
            // Primeiro, vamos verificar qual valor o tipo_atendimento tem para AEE
            // Vamos fazer uma consulta de teste para ver os valores existentes
            $testSql = "SELECT DISTINCT tipo_atendimento, COUNT(*) as total
                        FROM pmieducar.turma 
                        WHERE ativo = 1 
                        AND ano = ?
                        " . ($schoolId ? " AND ref_ref_cod_escola = ? " : "") . "
                        GROUP BY tipo_atendimento 
                        ORDER BY tipo_atendimento";
            
            $testParams = [date('Y')];
            if ($schoolId) {
                $testParams[] = $schoolId;
            }
            
            $testResult = DB::select($testSql, $testParams);
            
            // Log para debugging
            error_log('Valores de tipo_atendimento encontrados: ' . json_encode($testResult));
            
            // Agora a consulta principal para alunos em turmas AEE
            // Baseado na estrutura comum do i-Educar:
            // tipo_atendimento = 1: Regular
            // tipo_atendimento = 2: Atendimento Educacional Especializado (AEE)
            // tipo_atendimento = 3: Atividade Complementar
            // tipo_atendimento = 4: AEE - Braille
            // tipo_atendimento = 5: AEE - Soroban
            // tipo_atendimento = 6: AEE - Orientação e Mobilidade
            // tipo_atendimento = 7: AEE - Língua Brasileira de Sinais
            // tipo_atendimento = 8: AEE - Comunicação Alternativa
            // tipo_atendimento = 9: AEE - Tecnologia Assistiva
            
            $sql = "SELECT COUNT(DISTINCT m.cod_matricula) as total
                    FROM pmieducar.matricula m
                    INNER JOIN pmieducar.matricula_turma mt ON mt.ref_cod_matricula = m.cod_matricula
                    INNER JOIN pmieducar.turma t ON t.cod_turma = mt.ref_cod_turma
                    WHERE m.ativo = 1
                    AND mt.ativo = 1
                    AND m.ano = ?
                    AND t.ativo = 1
                    " . ($schoolId ? " AND t.ref_ref_cod_escola = ? " : "") . "
                    AND (
                        -- Tipo de atendimento para AEE (2 ou valores maiores que 2 para tipos específicos)
                        t.tipo_atendimento IN (2, 4, 5, 6, 7, 8, 9)
                        
                        -- OU se o nome da turma contém indicadores de AEE
                        OR LOWER(t.nm_turma) LIKE '%aee%'
                        OR LOWER(t.nm_turma) LIKE '%atendimento educacional especializado%'
                        OR LOWER(t.nm_turma) LIKE '%educação especial%'
                        OR LOWER(t.nm_turma) LIKE '%inclusão%'
                        OR LOWER(t.nm_turma) LIKE '%braille%'
                        OR LOWER(t.nm_turma) LIKE '%soroban%'
                        OR LOWER(t.nm_turma) LIKE '%libras%'
                        OR LOWER(t.nm_turma) LIKE '%comunicação alternativa%'
                        
                        -- OU se tem atividades AEE definidas
                        OR (t.atividades_aee IS NOT NULL AND t.atividades_aee != '')
                        
                        -- OU se é classe especial
                        OR t.classe_especial = 1
                        
                        -- OU se tem algum dos campos AEE específicos marcados
                        OR t.aee_braille = 1
                        OR t.aee_recurso_optico = 1
                        OR t.aee_estrategia_desenvolvimento = 1
                        OR t.aee_tecnica_mobilidade = 1
                        OR t.aee_libras = 1
                        OR t.aee_caa = 1
                        OR t.aee_curricular = 1
                        OR t.aee_soroban = 1
                        OR t.aee_informatica = 1
                        OR t.aee_lingua_escrita = 1
                        OR t.aee_autonomia = 1
                    )";

            $params = [date('Y')];
            if ($schoolId) {
                $params[] = $schoolId;
            }

            $result = DB::select($sql, $params);
            
            $total = $result[0]->total ?? 0;
            error_log('Total de matrículas AEE encontradas: ' . $total);
            
            return $total;

        } catch (Exception $e) {
            error_log('Erro ao buscar matrículas AEE: ' . $e->getMessage());
            
            // Fallback: tentar consulta alternativa mais simples
            return $this->getMatriculasAEEFallback($schoolId);
        }
    }

    /**
     * Método fallback simplificado para buscar matrículas AEE
     */
    private function getMatriculasAEEFallback($schoolId = null)
    {
        try {
            // Consulta alternativa mais simples
            $sql = "SELECT COUNT(DISTINCT m.cod_matricula) as total
                    FROM pmieducar.matricula m
                    INNER JOIN pmieducar.matricula_turma mt ON mt.ref_cod_matricula = m.cod_matricula
                    INNER JOIN pmieducar.turma t ON t.cod_turma = mt.ref_cod_turma
                    WHERE m.ativo = 1
                    AND mt.ativo = 1
                    AND m.ano = ?
                    AND t.ativo = 1
                    " . ($schoolId ? " AND t.ref_ref_cod_escola = ? " : "") . "
                    AND (
                        t.tipo_atendimento = 2 
                        OR LOWER(t.nm_turma) LIKE '%aee%'
                        OR LOWER(t.nm_turma) LIKE '%atendimento educacional especializado%'
                    )";

            $params = [date('Y')];
            if ($schoolId) {
                $params[] = $schoolId;
            }

            $result = DB::select($sql, $params);
            
            return $result[0]->total ?? 0;

        } catch (Exception $e) {
            error_log('Erro no fallback de matrículas AEE: ' . $e->getMessage());
            
            // Último fallback: contar apenas pelo nome da turma
            return $this->getMatriculasAEEPorNome($schoolId);
        }
    }

    /**
     * Último fallback - busca apenas por nome da turma
     */
    private function getMatriculasAEEPorNome($schoolId = null)
    {
        try {
            $sql = "SELECT COUNT(DISTINCT m.cod_matricula) as total
                    FROM pmieducar.matricula m
                    INNER JOIN pmieducar.matricula_turma mt ON mt.ref_cod_matricula = m.cod_matricula
                    INNER JOIN pmieducar.turma t ON t.cod_turma = mt.ref_cod_turma
                    WHERE m.ativo = 1
                    AND mt.ativo = 1
                    AND m.ano = ?
                    AND t.ativo = 1
                    " . ($schoolId ? " AND t.ref_ref_cod_escola = ? " : "") . "
                    AND (
                        LOWER(t.nm_turma) LIKE '%aee%'
                        OR LOWER(t.nm_turma) LIKE '%atendimento educacional especializado%'
                    )";

            $params = [date('Y')];
            if ($schoolId) {
                $params[] = $schoolId;
            }

            $result = DB::select($sql, $params);
            
            return $result[0]->total ?? 0;

        } catch (Exception $e) {
            error_log('Erro no último fallback de matrículas AEE: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Formata CPF numérico para exibição
     */
    private function formatarCPFNumerico($cpfNumerico)
    {
        if ($cpfNumerico === null) {
            return 'Não informado';
        }

        // Converte para string e preenche com zeros à esquerda
        $cpfString = str_pad((string)$cpfNumerico, 11, '0', STR_PAD_LEFT);
        
        // Se for tudo zero, retorna como inválido
        if ($cpfString === '00000000000' || $cpfNumerico == 0) {
            return '000.000.000-00';
        }

        // Formata o CPF
        if (strlen($cpfString) === 11) {
            return substr($cpfString, 0, 3) . '.' . substr($cpfString, 3, 3) . '.' . substr($cpfString, 6, 3) . '-' . substr($cpfString, 9, 2);
        }

        return $cpfString;
    }

    /**
     * Gera a lista HTML de alunos sem CPF válido
     */
    private function gerarListaAlunosSemCPF($alunos)
    {
        if (empty($alunos)) {
            return '
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 10px;">🎉</div>
                    <h4 style="margin: 0 0 10px 0;">Nenhum aluno sem CPF válido encontrado</h4>
                    <p style="margin: 0;">Todos os alunos estão com CPF cadastrado corretamente.</p>
                </div>
            ';
        }

        $html = '
            <div style="margin-bottom: 15px; font-size: 14px; color: #666;">
                Clique em "Editar CPF" para corrigir o cadastro do aluno.
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Nome do Aluno</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">CPF Atual</th>
                        <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;">Ação</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($alunos as $aluno) {
            $cpfFormatado = $aluno['cpf'];
            $isCPFInvalido = $cpfFormatado === '000.000.000-00' || $cpfFormatado === 'Não informado';
            $cpfDisplay = $isCPFInvalido ? '<span style="color: #dc3545; font-weight: bold;">' . $cpfFormatado . '</span>' : $cpfFormatado;
            
            $html .= '
                <tr style="border-bottom: 1px solid #f0f0f0;">
                    <td style="padding: 12px; vertical-align: middle;">' . htmlspecialchars($aluno['nome']) . '</td>
                    <td style="padding: 12px; vertical-align: middle; font-family: monospace;">' . $cpfDisplay . '</td>
                    <td style="padding: 12px; text-align: center; vertical-align: middle;">
                        <a href="/module/Cadastro/aluno?id=' . $aluno['cod_aluno'] . '" style="text-decoration: none;">
                            <button type="button"
                                    style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 12px; transition: background 0.2s;">
                                Editar CPF
                            </button>
                        </a>
                    </td>
                </tr>
            ';
        }

        $html .= '</tbody>
             </table>';

        return $html;
    }

    /**
     * Obtém o total de alunos matriculados
     */
    private function getTotalAlunosMatriculados($schoolId = null)
    {
        try {
            if ($schoolId) {
                // Consulta filtrada por escola
                $sql = "SELECT COUNT(DISTINCT a.cod_aluno) as total 
                        FROM pmieducar.aluno a
                        INNER JOIN pmieducar.matricula m ON m.ref_cod_aluno = a.cod_aluno
                        WHERE a.ativo = 1
                        AND m.ativo = 1 
                        AND m.ano = ?
                        AND m.ref_ref_cod_escola = ?";

                $result = DB::select($sql, [date('Y'), $schoolId]);
                return $result[0]->total ?? 0;
            }

            // Fallback: consulta sem filtro de escola (compatibilidade)
            return $this->getTotalAlunosFromDirectSQL();

        } catch (Exception $e) {
            error_log('Erro ao buscar total de alunos por escola: ' . $e->getMessage());
            return $this->getTotalAlunosFromDirectSQL();
        }
    }

    /**
     * Obtém o total de turmas ativas do ano atual para o resumo
     */
    private function getTotalTurmasAtivas($schoolId = null)
    {
        try {
            if ($schoolId) {
                // Consulta filtrada por escola
                $sql = "SELECT COUNT(cod_turma) as total 
                        FROM pmieducar.turma 
                        WHERE ativo = 1 
                        AND ano = ? 
                        AND visivel = true
                        AND ref_ref_cod_escola = ?";

                $result = DB::select($sql, [date('Y'), $schoolId]);
                return $result[0]->total ?? 0;
            }

            // Consulta sem filtro de escola (compatibilidade)
            return $this->getTotalTurmasFromSQL();

        } catch (Exception $e) {
            // Em caso de erro, usar fallback SQL
            error_log('Erro ao buscar total de turmas: ' . $e->getMessage());
            return $this->getTotalTurmasFromSQL();
        }
    }

    /**
     * Método fallback com consulta SQL direta para turmas
     */
    private function getTotalTurmasFromSQL()
    {
        try {
            $sql = "SELECT COUNT(cod_turma) as total 
                    FROM pmieducar.turma 
                    WHERE ativo = 1 
                    AND ano = ? 
                    AND visivel = true";

            $result = DB::select($sql, [date('Y')]);
            return $result[0]->total ?? 0;

        } catch (Exception $e) {
            error_log('Erro na consulta SQL direta de turmas: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Método fallback com consulta SQL direta para alunos
     */
    private function getTotalAlunosFromDirectSQL()
    {
        try {
            $sql = "SELECT COUNT(DISTINCT cod_aluno) as total 
                    FROM pmieducar.aluno 
                    WHERE ativo = 1 
                    AND cod_aluno IN (
                        SELECT ref_cod_aluno 
                        FROM pmieducar.matricula 
                        WHERE ativo = 1 
                        AND ano = ?
                    )";

            $result = DB::select($sql, [date('Y')]);
            return $result[0]->total ?? 0;
        } catch (Exception $e) {
            error_log('Erro na consulta SQL direta: ' . $e->getMessage());
            return 0; 
        }
    }
};