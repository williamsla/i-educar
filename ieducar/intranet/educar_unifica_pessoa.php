<?php

use App\Models\Individual;
use App\Models\LogUnification;
use App\Services\ValidationDataService;
use iEducar\Modules\Unification\PersonLogUnification;
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

        // Pegar página atual da URL
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
        </style>
        ";
        
        // JavaScript para o acordeon e funcionalidades
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
        
        function unificarGrupo(grupoId) {
            var pessoaPrincipal = document.querySelector('input[name=\"principal_' + grupoId + '\"]:checked');
            
            if (!pessoaPrincipal) {
                alert('Selecione uma pessoa principal.');
                return;
            }
            
            var pessoaPrincipalValor = pessoaPrincipal.value;
            var pessoasIds = [];
            
            document.querySelectorAll('#' + grupoId + ' .linha_listagem_grupo').forEach(function(row) {
                var idpes = row.getAttribute('data-idpes');
                if (idpes) pessoasIds.push(parseInt(idpes));
            });
            
            if (confirm('Confirmar unificação dos ' + pessoasIds.length + ' pessoas deste grupo?\\n\\nPessoa principal: ' + pessoaPrincipalValor + '\\n\\nEsta ação não poderá ser desfeita!')) {
                var dados = [];
                dados.push({ idpes: parseInt(pessoaPrincipalValor), pessoa_principal: true });
                
                for (var i = 0; i < pessoasIds.length; i++) {
                    if (pessoasIds[i] != pessoaPrincipalValor) {
                        dados.push({ idpes: pessoasIds[i], pessoa_principal: false });
                    }
                }
                
                var formData = document.createElement('form');
                formData.method = 'post';
                formData.action = 'educar_unifica_pessoa.php';
                
                var acao = document.createElement('input');
                acao.type = 'hidden';
                acao.name = 'tipoacao';
                acao.value = 'Novo';
                formData.appendChild(acao);
                
                var hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = 'pessoas';
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
        
        // ========== PARTE SUPERIOR: FORMULÁRIO ==========
        echo '<div class="formulario-superior">';
        
        $this->acao_enviar = 'carregaDadosPessoas()';
        $this->campoTabelaInicio(nome: 'tabela_pessoas', arr_campos: ['Pessoa duplicada', 'Campo Pessoa duplicada'], arr_valores: $this->tabela_pessoas);
        $this->campoRotulo(nome: 'pessoa_label', campo: '', valor: 'Pessoa física a ser unificada <span class="campo_obrigatorio">*</span>');
        $this->campoTexto(nome: 'pessoa_duplicada', campo: 'Pessoa duplicada', valor: $this->pessoa_duplicada, tamanhovisivel: 50, tamanhomaximo: 255, expressao: true, duplo: false);
        $this->campoTabelaFim();
        
        echo '</div>';
        
        // ========== PARTE INFERIOR: LISTAGEM DE DUPLICATAS ==========
        if (!empty($duplicatas) && count($duplicatas) > 0) {
            echo '<div class="titulo-duplicatas">📋 Grupos de possíveis duplicatas encontrados</div>';
            
            $totalGrupos = count($duplicatas);
            $totalPaginas = ceil($totalGrupos / $this->itens_por_pagina);
            
            if ($this->pagina_atual < 1) $this->pagina_atual = 1;
            if ($this->pagina_atual > $totalPaginas) $this->pagina_atual = $totalPaginas;
            
            $inicio = ($this->pagina_atual - 1) * $this->itens_por_pagina;
            $gruposPagina = array_slice($duplicatas, $inicio, $this->itens_por_pagina);
            
            echo '<div id="todos-grupos" class="accordion-container">';
            
            foreach ($gruposPagina as $indice => $grupo) {
                $indiceGlobal = $inicio + $indice;
                $this->gerarCardGrupoAcordeon($grupo, $indiceGlobal);
            }
            
            echo '</div>';
            
            if ($totalPaginas > 1) {
                echo '<div class="paginacao">';
                
                if ($this->pagina_atual > 1) {
                    echo '<a href="#" onclick="mudarPagina(1); return false;">« Primeira</a>';
                    echo '<a href="#" onclick="mudarPagina(' . ($this->pagina_atual - 1) . '); return false;">‹ Anterior</a>';
                }
                
                $paginaInicio = max(1, $this->pagina_atual - 2);
                $paginaFim = min($totalPaginas, $this->pagina_atual + 2);
                
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
 
        $styles = ['/vendor/legacy/Cadastro/Assets/Stylesheets/UnificaPessoa.css'];
        $scripts = ['/vendor/legacy/Portabilis/Assets/Javascripts/ClientApi.js'];
        Portabilis_View_Helper_Application::loadStylesheet(viewInstance: $this, files: $styles);
        Portabilis_View_Helper_Application::loadJavascript(viewInstance: $this, files: $scripts);
    }
    
    private function gerarCardGrupoAcordeon($grupo, $indice)
    {
        $grupoId = 'grupo_' . $indice;
        $primeiraPessoa = $grupo[0];
        $nomeGrupo = $primeiraPessoa['nome'];
        $dataNasc = $primeiraPessoa['data_nascimento'];
        $quantidade = count($grupo);
        
        echo "
        <div id='{$grupoId}' class='accordion-item'>
            <div class='accordion-header' onclick='toggleAccordion(\"{$grupoId}\")'>
                <div class='titulo'>
                    <span class='icone' id='icone-{$grupoId}'>▶</span>
                    <span>📋 Grupo " . ($indice + 1) . ": <strong>{$nomeGrupo}</strong> - Nascimento: {$dataNasc}</span>
                    <span class='badge'>{$quantidade} pessoas</span>
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
        
        foreach ($grupo as $pessoa) {
            $nomeEscapado = addslashes($pessoa['nome']);
            echo "
                <tr id='row_{$grupoId}_{$pessoa['idpes']}' class='linha_listagem_grupo' data-idpes='{$pessoa['idpes']}'>
                    <td style='text-align: center;'>
                        <input type='radio' class='radio-principal' name='principal_{$grupoId}' value='{$pessoa['idpes']}' onchange='confirmaAnaliseDoGrupo(\"{$grupoId}\")'>
                    </td>
                    <td><a target='_blank' href='/intranet/atendidos_det.php?cod_pessoa={$pessoa['idpes']}'>{$pessoa['idpes']}</a></td>
                    <td>{$pessoa['nome']}</td>
                    <td>{$pessoa['data_nascimento']}</td>
                    <td>{$pessoa['cpf']}</td>
                    <td>{$pessoa['rg']}</td>
                    <td>{$pessoa['nome_mae']}</td>
                    <td><button class='btn-visualizar' onclick='visualizarDadosPessoa({$pessoa['idpes']}, \"{$nomeEscapado}\")'>👁️ Visualizar</button></td>
                    <td><a class='link_remove' onclick='removerPessoaDoGrupo(\"{$grupoId}\", {$pessoa['idpes']})'><b><u>EXCLUIR</u></b></a></td>
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
                        🔄 Unificar este grupo ({$quantidade} pessoas)
                    </button>
                </div>
            </div>
        </div>
        ";
    }
    
    private function buscarPossiveisDuplicatas()
    {
        $db = new clsBanco();
        $sql = "
            SELECT 
                f.idpes,
                p.nome,
                COALESCE(to_char(f.data_nasc, 'dd/mm/yyyy'), '') AS data_nascimento,
                COALESCE(f.cpf::varchar, 'Não consta') AS cpf,
                COALESCE(d.rg, 'Não consta') AS rg,
                COALESCE(f.nome_mae, 'Não consta') AS nome_mae
            FROM cadastro.fisica f
            JOIN cadastro.pessoa p ON p.idpes = f.idpes
            LEFT JOIN cadastro.documento d ON d.idpes = f.idpes
            WHERE f.idpes IN (
                SELECT DISTINCT ref_idpes FROM pmieducar.aluno WHERE ativo = 1
                UNION
                SELECT DISTINCT cod_servidor FROM pmieducar.servidor WHERE ativo = 1
            )
            AND (p.nome, f.data_nasc) IN (
                SELECT p2.nome, f2.data_nasc
                FROM cadastro.fisica f2
                JOIN cadastro.pessoa p2 ON p2.idpes = f2.idpes
                WHERE f2.idpes IN (
                    SELECT DISTINCT ref_idpes FROM pmieducar.aluno WHERE ativo = 1
                    UNION
                    SELECT DISTINCT cod_servidor FROM pmieducar.servidor WHERE ativo = 1
                )
                GROUP BY p2.nome, f2.data_nasc
                HAVING COUNT(*) > 1
            )
            ORDER BY p.nome, f.data_nasc, f.idpes
        ";
        
        $db->Consulta($sql);
        
        $pessoas = [];
        while ($db->ProximoRegistro()) {
            $row = $db->Tupla();
            $pessoas[] = [
                'idpes'           => (int) $row['idpes'],
                'nome'            => $row['nome'],
                'data_nascimento' => $row['data_nascimento'],
                'cpf'             => $row['cpf'],
                'rg'              => $row['rg'],
                'nome_mae'        => $row['nome_mae']
            ];
        }
        
        if (empty($pessoas)) {
            return [];
        }
        
        $grupos = [];
        foreach ($pessoas as $pessoa) {
            $chave = $pessoa['nome'] . '|' . $pessoa['data_nascimento'];
            if (!isset($grupos[$chave])) {
                $grupos[$chave] = [];
            }
            $grupos[$chave][] = $pessoa;
        }
        
        $grupos = array_filter($grupos, function($grupo) {
            return count($grupo) > 1;
        });
        
        return array_values($grupos);
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
        return array_map(callback: static fn ($item) => (int) $item['idpes'],
            array: array_filter(array: $pessoas, callback: static fn ($pessoas) => $pessoas['pessoa_principal'] === false)
        );
    }

    private function buscaPessoaPrincipal($pessoas)
    {
        $pessoas = array_values(array_filter(array: $pessoas,
            callback: static fn ($pessoas) => $pessoas['pessoa_principal'] === true)
        );

        return current($pessoas)['idpes'];
    }

    private function createLog($mainId, $duplicatesId, $createdBy)
    {
        $log = new LogUnification;
        $log->type = PersonLogUnification::getType();
        $log->main_id = $mainId;
        $log->duplicates_id = json_encode(array_values($duplicatesId));
        $log->created_by = $createdBy;
        $log->updated_by = $createdBy;
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
        return '';
    }
};