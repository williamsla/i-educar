<?php

use App\Models\LogUnification;
use App\Services\ValidationDataService;
use iEducar\Modules\Unification\StudentLogUnification;
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
        
        // Pegar página atual da URL
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
        // Buscar possíveis duplicatas diretamente no PHP
        $duplicatas = $this->buscarPossiveisDuplicatas();
        
        // CSS para os cards acordeon
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
                background-color: #e0e0e0;
            }
            .accordion-header .titulo {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .accordion-header .badge {
                background-color: #4CAF50;
                color: white;
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 12px;
            }
            .accordion-header .status {
                font-size: 14px;
                color: #666;
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
                margin-left: 15px;
                font-size: 12px;
            }
            .btn-remover-grupo:hover {
                background-color: #cc0000;
            }
            .tabela-grupo {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .tabela-grupo th, .tabela-grupo td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: left;
                vertical-align: top;
            }
            .tabela-grupo th {
                background-color: #4cae4f;
                color: white;
                font-weight: bold;
            }
            .tabela-grupo tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .tabela-grupo tr:hover {
                background-color: #f5f5f5;
            }
            .btn-visualizar {
                background-color: #2196F3;
                color: white;
                border: none;
                padding: 4px 10px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
            }
            .btn-visualizar:hover {
                background-color: #0b7dda;
            }
            .link_remove {
                color: #ff4444;
                cursor: pointer;
                text-decoration: underline;
            }
            .confirmacao-grupo {
                margin-top: 15px;
                padding: 15px;
                background-color: #f9f9f9;
                border-radius: 5px;
                border-left: 4px solid #4CAF50;
            }
            .btn-unificar-grupo {
                background-color: #4CAF50;
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
                background-color: #cccccc;
                cursor: not-allowed;
            }
            .btn-unificar-grupo:hover:not(:disabled) {
                background-color: #45a049;
            }
            .radio-principal {
                transform: scale(1.2);
                margin: 0;
            }
            .info-duplicata {
                color: #ff9800;
                font-size: 12px;
                margin-left: 10px;
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
            .formulario-superior {
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 1px solid #ddd;
            }

            /* Modal de visualização */
            #modal-overlay-aluno {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.55);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            #modal-overlay-aluno .modal-box {
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
            #modal-overlay-aluno .modal-footer {
                text-align: right;
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            }
            #modal-overlay-aluno .btn-fechar-modal {
                background: #fff;
                border: 1px solid #aaa;
                border-radius: 4px;
                padding: 8px 24px;
                font-size: 14px;
                cursor: pointer;
                transition: background 0.2s;
            }
            #modal-overlay-aluno .btn-fechar-modal:hover {
                background: #f0f0f0;
            }
        </style>
        ";
        
        // JavaScript para o acordeon e funcionalidades
        echo "
        <script>
        // Função para alternar o accordion
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
        
        // Fecha o modal de visualização
        function fecharModalAluno() {
            var overlay = document.getElementById('modal-overlay-aluno');
            if (overlay) {
                overlay.remove();
            }
        }
 
        // Função para visualizar dados do aluno
        function visualizarDadosAluno(codAluno, nomeAluno) {
            var url = '/module/Api/Aluno?oper=get&resource=dadosMatriculasHistoricosAlunos&aluno_id=' + codAluno;
            
            fetch(url)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    var html = '';
 
                    if (data.matriculas && data.matriculas.length > 0) {
                        html += '<h4 style=\"margin-top:0;\">Matrículas</h4>';
                        html += '<table class=\"tabela-grupo\">';
                        html += '<thead><tr><th>Ano</th><th>Escola</th><th>Curso</th><th>Série</th><th>Turma</th></tr></thead><tbody>';
                        data.matriculas.forEach(function(m) {
                            html += '<tr><td>' + m.ano + '</td><td>' + m.escola + '</td><td>' + m.curso + '</td><td>' + m.serie + '</td><td>' + m.turma + '</td></tr>';
                        });
                        html += '</tbody><tr>';
                    }
                    
                    if (data.historicos && data.historicos.length > 0) {
                        html += '<h4>Históricos</h4>';
                        html += '<table class=\"tabela-grupo\">';
                        html += '<thead><tr><th>Ano</th><th>Escola</th><th>Curso</th><th>Série</th><th>Situação</th></tr></thead><tbody>';
                        data.historicos.forEach(function(h) {
                            html += '<tr><td>' + h.ano + '</td><td>' + h.escola + '</td><td>' + h.curso + '</td><td>' + h.serie + '</td><td>' + h.situacao + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    }
                    
                    if (!html) {
                        html = '<p>Nenhum dado encontrado para este aluno.</p>';
                    }
 
                    // Remove modal anterior se existir
                    var anterior = document.getElementById('modal-overlay-aluno');
                    if (anterior) anterior.remove();
 
                    // Monta o modal
                    var overlay = document.createElement('div');
                    overlay.id = 'modal-overlay-aluno';
 
                    var box = document.createElement('div');
                    box.className = 'modal-box';
 
                    var titulo = document.createElement('div');
                    titulo.className = 'modal-title';
                    titulo.textContent = 'Dados Escolares - ' + nomeAluno;
 
                    var corpo = document.createElement('div');
                    corpo.innerHTML = html;
 
                    var rodape = document.createElement('div');
                    rodape.className = 'modal-footer';
 
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
 
                    // Fechar ao clicar fora do box
                    overlay.addEventListener('click', function(e) {
                        if (e.target === overlay) fecharModalAluno();
                    });
                })
                .catch(function(error) {
                    console.error('Erro:', error);
                    alert('Erro ao carregar dados do aluno');
                });
        }
        
        // Função para confirmar análise do grupo
        function confirmaAnaliseDoGrupo(grupoId) {
            var checked = document.getElementById('check_confirma_grupo_' + grupoId).checked;
            var radioSelecionado = document.querySelector('input[name=\"principal_' + grupoId + '\"]:checked');
            var btnUnificar = document.getElementById('btn_unificar_grupo_' + grupoId);
            
            if (radioSelecionado && checked) {
                btnUnificar.disabled = false;
            } else {
                btnUnificar.disabled = true;
            }
        }
        
        // Função para remover aluno do grupo
        function removerAlunoDoGrupo(grupoId, codAluno) {
            var row = document.getElementById('row_' + grupoId + '_' + codAluno);
            var totalAlunos = document.querySelectorAll('#' + grupoId + ' .linha_listagem_grupo').length;
            
            if (totalAlunos <= 2) {
                if (confirm('É necessário ao menos 2 alunos para a unificação. Este grupo será removido. Deseja prosseguir?')) {
                    var grupo = document.getElementById(grupoId);
                    grupo.remove();
                }
                return;
            }
            
            row.style.transition = 'opacity 0.4s';
            row.style.opacity = '0';
            setTimeout(function() {
                row.remove();
                var checkConfirm = document.getElementById('check_confirma_grupo_' + grupoId);
                if (checkConfirm) {
                    checkConfirm.checked = false;
                }
                var btnUnificar = document.getElementById('btn_unificar_grupo_' + grupoId);
                if (btnUnificar) {
                    btnUnificar.disabled = true;
                }
            }, 400);
        }
        
        // Função para remover grupo inteiro
        function removerGrupo(grupoId) {
            if (confirm('Deseja remover este grupo de unificação?')) {
                var grupo = document.getElementById(grupoId);
                grupo.style.transition = 'opacity 0.4s';
                grupo.style.opacity = '0';
                setTimeout(function() {
                    grupo.remove();
                }, 400);
            }
        }
        
        // Função para unificar um grupo
        function unificarGrupo(grupoId) {
            var alunoPrincipal = document.querySelector('input[name=\"principal_' + grupoId + '\"]:checked').value;
            var alunosIds = [];
            
            document.querySelectorAll('#' + grupoId + ' .linha_listagem_grupo').forEach(function(row) {
                var codAluno = row.getAttribute('data-codigo');
                alunosIds.push(parseInt(codAluno));
            });
            
            if (confirm('Confirmar unificação dos ' + alunosIds.length + ' alunos deste grupo?\\n\\nAluno principal: ' + alunoPrincipal + '\\n\\nEsta ação não poderá ser desfeita!')) {
                enviarUnificacaoEspecifica(alunoPrincipal, alunosIds.filter(function(id) {
                    return id != alunoPrincipal;
                }));
            }
        }
        
        // Função para enviar unificação específica
        function enviarUnificacaoEspecifica(alunoPrincipal, alunosDuplicados) {
            var dados = [];
            dados.push({
                codAluno: parseInt(alunoPrincipal),
                aluno_principal: true
            });
            
            for (var i = 0; i < alunosDuplicados.length; i++) {
                dados.push({
                    codAluno: parseInt(alunosDuplicados[i]),
                    aluno_principal: false
                });
            }
            
            var formData = document.createElement('form');
            formData.method = 'post';
            formData.action = 'educar_unifica_aluno.php';
            
            var acao = document.createElement('input');
            acao.type = 'hidden';
            acao.name = 'tipoacao';
            acao.value = 'Novo';
            formData.appendChild(acao);
            
            var hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = 'alunos';
            hiddenField.value = JSON.stringify(dados);
            formData.appendChild(hiddenField);
            
            document.body.appendChild(formData);
            formData.submit();
        }
        
        // Função para mudar de página
        function mudarPagina(pagina) {
            var url = new URL(window.location.href);
            url.searchParams.set('pagina', pagina);
            window.location.href = url.toString();
        }
        </script>
        ";
        
        // ========== PARTE SUPERIOR: FORMULÁRIO ==========
        echo '<div class="formulario-superior">';
        
        $this->acao_enviar = 'carregaDadosAlunos()';
        $this->campoTabelaInicio(nome: 'tabela_alunos', arr_campos: ['Aluno duplicado', 'Campo aluno duplicado'], arr_valores: $this->tabela_alunos);
        $this->campoRotulo(nome: 'aluno_label', campo: '', valor: 'Aluno(a) a ser unificado(a)  <span class="campo_obrigatorio">*</span>');
        $this->campoTexto(nome: 'aluno_duplicado', campo: 'Aluno duplicado', valor: $this->aluno_duplicado, tamanhovisivel: 50, tamanhomaximo: 255, expressao: true, duplo: false);
        $this->campoTabelaFim();
        
        echo '</div>';
        
        // ========== PARTE INFERIOR: LISTAGEM DE DUPLICATAS ==========
        if (!empty($duplicatas) && count($duplicatas) > 0) {
            echo '<div class="titulo-duplicatas">📋 Grupos de possíveis duplicatas encontrados</div>';
            
            $totalGrupos = count($duplicatas);
            $totalPaginas = ceil($totalGrupos / $this->itens_por_pagina);
            
            // Validar página atual
            if ($this->pagina_atual < 1) $this->pagina_atual = 1;
            if ($this->pagina_atual > $totalPaginas) $this->pagina_atual = $totalPaginas;
            
            $inicio = ($this->pagina_atual - 1) * $this->itens_por_pagina;
            $gruposPagina = array_slice($duplicatas, $inicio, $this->itens_por_pagina);
            
            echo '<div id="todos-grupos" class="accordion-container">';
            
            // Gerar os cards da página atual
            foreach ($gruposPagina as $indice => $grupo) {
                $indiceGlobal = $inicio + $indice;
                $this->gerarCardGrupoAcordeon($grupo, $indiceGlobal);
            }
            
            echo '</div>';
            
            // Gerar paginação
            if ($totalPaginas > 1) {
                echo '<div class="paginacao">';
                
                // Botão primeira página
                if ($this->pagina_atual > 1) {
                    echo '<a href="#" onclick="mudarPagina(1); return false;">« Primeira</a>';
                    echo '<a href="#" onclick="mudarPagina(' . ($this->pagina_atual - 1) . '); return false;">‹ Anterior</a>';
                }
                
                // Links de páginas
                $paginaInicio = max(1, $this->pagina_atual - 2);
                $paginaFim = min($totalPaginas, $this->pagina_atual + 2);
                
                for ($i = $paginaInicio; $i <= $paginaFim; $i++) {
                    if ($i == $this->pagina_atual) {
                        echo '<span class="pagina-ativa">' . $i . '</span>';
                    } else {
                        echo '<a href="#" onclick="mudarPagina(' . $i . '); return false;">' . $i . '</a>';
                    }
                }
                
                // Botão última página
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
 
        $styles = ['/vendor/legacy/Cadastro/Assets/Stylesheets/UnificaAluno.css'];
        Portabilis_View_Helper_Application::loadStylesheet(viewInstance: $this, files: $styles);
        $scripts = ['/vendor/legacy/Portabilis/Assets/Javascripts/ClientApi.js'];
        Portabilis_View_Helper_Application::loadJavascript(viewInstance: $this, files: $scripts);
    }
    
    /**
     * Gera o HTML de um card de grupo no formato acordeon
     */
    private function gerarCardGrupoAcordeon($grupo, $indice)
    {
        $grupoId = 'grupo_' . $indice;
        $primeiroAluno = $grupo[0];
        $nomeGrupo = $primeiroAluno['nome'];
        $dataNasc = $primeiroAluno['data_nascimento'];
        $quantidade = count($grupo);
        
        echo "
        <div id='{$grupoId}' class='accordion-item'>
            <div class='accordion-header' onclick='toggleAccordion(\"{$grupoId}\")'>
                <div class='titulo'>
                    <span class='icone' id='icone-{$grupoId}'>▶</span>
                    <span>📋 Grupo " . ($indice + 1) . ": <strong>{$nomeGrupo}</strong> - Nascimento: {$dataNasc}</span>
                    <span class='badge'>{$quantidade} alunos</span>
                </div>
                <div>
                    <button class='btn-remover-grupo' onclick='event.stopPropagation(); removerGrupo(\"{$grupoId}\")'>🗑️ Remover Grupo</button>
                </div>
            </div>
            <div id='accordion-content-{$grupoId}' class='accordion-content'>
                <table class='tabela-grupo'>
                    <thead>
                        <tr>
                            <th>Principal</th>
                            <th>Código</th>
                            <th>INEP</th>
                            <th>Nome</th>
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
        
        foreach ($grupo as $aluno) {
            $nomeEscapado = addslashes($aluno['nome']);
            echo "
                <tr id='row_{$grupoId}_{$aluno['codigo']}' class='linha_listagem_grupo' data-codigo='{$aluno['codigo']}'>
                    <td style='text-align: center;'>
                        <input type='radio' class='radio-principal' name='principal_{$grupoId}' value='{$aluno['codigo']}' onchange='confirmaAnaliseDoGrupo(\"{$grupoId}\")'>
                    </td>
                    <td><a target='_blank' href='/intranet/educar_aluno_det.php?cod_aluno={$aluno['codigo']}'>{$aluno['codigo']}</a></td>
                    <td>{$aluno['inep']}</td>
                    <td>{$aluno['nome']}</td>
                    <td>{$aluno['data_nascimento']}</td>
                    <td>{$aluno['cpf']}</td>
                    <td>{$aluno['rg']}</td>
                    <td>{$aluno['mae_aluno']}</td>
                    <td><button class='btn-visualizar' onclick='visualizarDadosAluno({$aluno['codigo']}, \"{$nomeEscapado}\")'>👁️ Visualizar</button></td>
                    <td><a class='link_remove' onclick='removerAlunoDoGrupo(\"{$grupoId}\", {$aluno['codigo']})'><b><u>EXCLUIR</u></b></a></td>
                </tr>
            ";
        }
        
        echo "
                    </tbody>
                </table>
                <div class='confirmacao-grupo'>
                    <input type='checkbox' id='check_confirma_grupo_{$grupoId}' onchange='confirmaAnaliseDoGrupo(\"{$grupoId}\")'>
                    <label for='check_confirma_grupo_{$grupoId}'>
                        ✅ Confirmo a análise de que são a mesma pessoa, levando em conta a possibilidade de gêmeos cadastrados.
                    </label>
                    <br>
                    <button id='btn_unificar_grupo_{$grupoId}' class='btn-unificar-grupo' onclick='unificarGrupo(\"{$grupoId}\")' disabled>
                        🔄 Unificar este grupo ({$quantidade} alunos)
                    </button>
                </div>
            </div>
        </div>
        ";
    }
    
    /**
     * Busca possíveis duplicatas de alunos diretamente no banco
     */
    private function buscarPossiveisDuplicatas()
    {
        $db = new clsBanco();
        $sql = "
            SELECT 
                a.cod_aluno AS codigo,
                p.nome AS nome,
                COALESCE(to_char(f.data_nasc, 'dd/mm/yyyy'), '') AS data_nascimento,
                COALESCE(f.cpf::varchar, 'Não consta') AS cpf,
                COALESCE(d.rg, 'Não consta') AS rg,
                COALESCE(relatorio.get_mae_aluno(a.cod_aluno), 'Não consta') AS mae_aluno,
                COALESCE(eca.cod_aluno_inep::varchar, 'Não consta') AS inep
            FROM pmieducar.aluno a
            JOIN cadastro.pessoa p ON p.idpes = a.ref_idpes
            JOIN cadastro.fisica f ON f.idpes = a.ref_idpes
            LEFT JOIN cadastro.documento d ON d.idpes = a.ref_idpes
            LEFT JOIN modules.educacenso_cod_aluno eca ON eca.cod_aluno = a.cod_aluno
            WHERE a.ativo = 1
            AND (p.nome, f.data_nasc) IN (
                SELECT p2.nome, f2.data_nasc
                FROM pmieducar.aluno a2
                JOIN cadastro.pessoa p2 ON p2.idpes = a2.ref_idpes
                JOIN cadastro.fisica f2 ON f2.idpes = a2.ref_idpes
                WHERE a2.ativo = 1
                GROUP BY p2.nome, f2.data_nasc
                HAVING COUNT(*) > 1
            )
            ORDER BY p.nome, f.data_nasc, a.cod_aluno
        ";
        
        $db->Consulta($sql);
        
        $alunos = [];
        while ($db->ProximoRegistro()) {
            $row = $db->Tupla();
            $alunos[] = [
                'codigo'          => (int) $row['codigo'],
                'nome'            => $row['nome'],
                'data_nascimento' => $row['data_nascimento'],
                'cpf'             => $row['cpf'],
                'rg'              => $row['rg'],
                'mae_aluno'       => $row['mae_aluno'],
                'inep'            => $row['inep'],
            ];
        }
        
        if (empty($alunos)) {
            return [];
        }
        
        // Agrupar por nome e data de nascimento
        $grupos = [];
        foreach ($alunos as $aluno) {
            $chave = $aluno['nome'] . '|' . $aluno['data_nascimento'];
            if (!isset($grupos[$chave])) {
                $grupos[$chave] = [];
            }
            $grupos[$chave][] = $aluno;
        }
        
        // Filtrar apenas grupos com mais de 1 aluno
        $grupos = array_filter($grupos, function($grupo) {
            return count($grupo) > 1;
        });
        
        return array_values($grupos);
    }
 
    private function validaPermissaoDaPagina()
    {
        (new clsPermissoes)
            ->permissao_cadastra(
                int_processo_ap: 999847,
                int_idpes_usuario: $this->pessoa_logada,
                int_soma_nivel_acesso: 7,
                str_pagina_redirecionar: 'index.php'
            );
    }
 
    private function validaDadosDaUnificacaoAluno($alunos): bool
    {
        foreach ($alunos as $item) {
            if (!isset($item['codAluno'])) {
                return false;
            }
 
            if (!isset($item['aluno_principal'])) {
                return false;
            }
        }
 
        return true;
    }
 
    public function Novo()
    {
        $this->validaPermissaoDaPagina();
 
        try {
            $alunos = json_decode(json: $this->alunos, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (TypeError $exception) {
            $this->mensagem = 'Informações inválidas para unificação';
 
            return false;
        } catch (Exception $exception) {
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
            $this->mensagem = 'Não pode haver mais de uma aluno principal';
 
            return false;
        }
 
        if (!$validationData->verifyDataContainsDuplicatesByKey(data: $alunos, key: 'codAluno')) {
            $this->mensagem = 'Erro ao tentar unificar Alunos, foi inserido cadastro duplicados';
 
            return false;
        }
 
        $cod_aluno_principal = $this->buscaPessoaPrincipal(pessoas: $alunos);
        $cod_alunos = $this->buscaIdesDasPessoasParaUnificar(pessoas: $alunos);
 
        DB::beginTransaction();
        $unificationId = $this->createLog(mainId: $cod_aluno_principal, duplicatesId: $cod_alunos, createdBy: $this->pessoa_logada);
        App_Unificacao_Aluno::unifica(codAlunoPrincipal: $cod_aluno_principal, codAlunos: $cod_alunos, codPessoa: $this->pessoa_logada, db: new clsBanco, unificationId: $unificationId);
 
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
 
    private function buscaIdesDasPessoasParaUnificar($pessoas)
    {
        return array_values(array: array_map(callback: static fn ($item) => (int) $item['codAluno'],
            array: array_filter(array: $pessoas, callback: static fn ($pessoas) => $pessoas['aluno_principal'] === false)
        ));
    }
 
    private function buscaPessoaPrincipal($pessoas)
    {
        $pessoas = array_values(array: array_filter(array: $pessoas,
            callback: static fn ($pessoas) => $pessoas['aluno_principal'] === true)
        );
 
        return (int) current(array: $pessoas)['codAluno'];
    }
 
    private function createLog($mainId, $duplicatesId, $createdBy)
    {
        $log = new LogUnification;
        $log->type = StudentLogUnification::getType();
        $log->main_id = $mainId;
        $log->duplicates_id = json_encode(value: array_values(array: $duplicatesId));
        $log->created_by = $createdBy;
        $log->updated_by = $createdBy;
        $log->save();
 
        return $log->id;
    }
 
    public function makeExtra()
    {
        return file_get_contents(filename: __DIR__ . '/scripts/extra/educar-unifica-aluno.js');
    }
 
    public function Formular()
    {
        $this->title = 'Unificação de alunos';
        $this->processoAp = '999847';
    }
};