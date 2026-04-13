<?php

use App\Models\Employee;

return new class extends clsListagem
{
    public $limite;

    public $offset;

    public $cod_servidor;

    public $ref_idesco;

    public $ref_cod_funcao;

    public $carga_horaria;

    public $data_cadastro;

    public $data_exclusao;

    public $ativo;

    public $nome;

    public $matricula_servidor;

    public $ref_cod_escola;

    public $ref_cod_instituicao;

    public $servidor_sem_alocacao;

    public $ano_letivo;

    public function Gerar()
    {
        $this->titulo = 'Servidor - Listagem';

        foreach ($_GET as $var => $val) {
            $this->$var = ($val === '') ? null : $val;
        }

        $this->addCabecalhos(coluna: [
            'Nome do Servidor',
            'Matrícula',
            'CPF',
            'Instituição',
        ]);

        $this->inputsHelper()->dynamic(helperNames: ['instituicao', 'escola', 'anoLetivo'], helperOptions: ['options' => ['required' => false]]);

        $parametros = new clsParametrosPesquisas;
        $parametros->setSubmit(submit: 0);
        $this->campoTexto(nome: 'nome', campo: 'Nome do servidor', valor: $this->nome, tamanhovisivel: 50, tamanhomaximo: 255);
        $this->campoTexto(nome: 'matricula_servidor', campo: 'Matrícula', valor: $this->matricula_servidor, tamanhovisivel: 50, tamanhomaximo: 255);
        $this->inputsHelper()->dynamic(helperNames: 'escolaridade', inputOptions: ['required' => false]);
        $this->campoCheck(nome: 'servidor_sem_alocacao', campo: 'Incluir servidores sem alocação', valor: isset($_GET['servidor_sem_alocacao']));

        // Paginador
        $this->limite = 20;

        if (!$this->ref_idesco && $_GET['idesco']) {
            $this->ref_idesco = $_GET['idesco'];
        }

        // Busca os servidores que têm alerta (vinculados a turma com alocação automática 00:00 no ano vigente)
        $anoVigente = date('Y');
        $servidoresComAlerta = $this->getServidoresComAlertaAlocacao($anoVigente);

        // Monta a consulta de servidores - se não tiver filtros, mostra todos
        $query = Employee::join(table: 'cadastro.pessoa', first: 'cod_servidor', operator: 'idpes')
            ->with([
                'institution:cod_instituicao,nm_instituicao',
                'individual:idpes,cpf',
                'employeeRoles:ref_cod_servidor,matricula',
            ])
            ->active();

        // Aplica filtros apenas se fornecidos
        if (!empty($this->ref_cod_instituicao)) {
            $query->where('ref_cod_instituicao', $this->ref_cod_instituicao);
        }

        if (!empty($this->nome)) {
            $query->where('pessoa.nome', 'ILIKE', "%{$this->nome}%");
        }

        if (!empty($this->matricula_servidor)) {
            $query->whereHas('employeeRoles', function($q) {
                $q->where('matricula', 'ILIKE', "%{$this->matricula_servidor}%");
            });
        }

        if (!empty($this->ref_idesco)) {
            $query->where('ref_idesco', $this->ref_idesco);
        }

        // Filtro de alocação (se necessário)
        if (request()->has('servidor_sem_alocacao') && !empty($this->ref_cod_escola) && !empty($this->ano_letivo)) {
            $query->whereDoesntHave('allocations', function($q) {
                $q->where('ref_cod_escola', $this->ref_cod_escola)
                  ->where('ano', $this->ano_letivo)
                  ->where('ativo', 1);
            });
        } elseif (!empty($this->ref_cod_escola) && !empty($this->ano_letivo)) {
            // Filtro normal por escola e ano
            $query->whereHas('allocations', function($q) {
                $q->where('ref_cod_escola', $this->ref_cod_escola)
                  ->where('ano', $this->ano_letivo)
                  ->where('ativo', 1);
            });
        }

        $lista = $query->orderBy('pessoa.nome')
            ->paginate($this->limite, [
                'pessoa.nome as name',
                'ref_cod_instituicao',
                'cod_servidor',
            ], 'pagina_formulario');

        // Debug
        if (isset($_GET['debug']) && $_GET['debug'] == 1) {
            echo "<div style='background: #ffffcc; padding: 10px; margin: 10px; border: 1px solid #ff9900;'>";
            echo "<strong>DEBUG:</strong><br>";
            echo "Ano Vigente: {$anoVigente}<br>";
            echo "Escola selecionada no filtro: " . ($this->ref_cod_escola ?? 'Nenhuma') . "<br>";
            echo "Ano selecionado no filtro: " . ($this->ano_letivo ?? 'Nenhum') . "<br>";
            echo "Servidores com alerta encontrados (ano vigente): " . count($servidoresComAlerta) . "<br>";
            echo "IDs: " . implode(', ', $servidoresComAlerta) . "<br>";
            echo "</div>";
        }

        // UrlHelper
        $url = CoreExt_View_Helper_UrlHelper::getInstance();

        // Monta a lista
        if ($lista->isNotEmpty()) {
            foreach ($lista as $registro) {
                $path = 'educar_servidor_det.php';
                $options = [
                    'query' => [
                        'cod_servidor' => $registro->id,
                        'ref_cod_instituicao' => $registro->institution->id,
                    ],
                ];

                $nomeServidor = $registro->name;
                
                // Verifica se o servidor está com alerta (apenas no ano vigente)
                if (in_array($registro->id, $servidoresComAlerta)) {
                    $nomeServidor .= ' <span style="color: #ff6600; font-weight: bold; cursor: help; font-size: 16px;" title="⚠️ ATENÇÃO: Este servidor foi vinculado a uma turma e o sistema criou automaticamente uma alocação com carga horária 00:00. Por favor, defina a carga horária correta na alocação do servidor.">⚠️</span>';
                }

                $this->addLinhas(linha: [
                    $url->l(text: $nomeServidor, path: $path, options: $options),
                    $url->l(text: $registro->employeeRoles->unique('matricula')->implode('matricula', ', '), path: $path, options: $options),
                    $url->l(text: $registro->individual->cpf, path: $path, options: $options),
                    $url->l(text: $registro->institution->name, path: $path, options: $options),
                ]);
            }
        } else {
            $this->addLinhas(linha: [
                'Nenhum servidor encontrado.',
            ]);
        }

        $this->addPaginador2(
            strUrl: 'educar_servidor_lst.php',
            intTotalRegistros: $lista->total(),
            mixVariaveisMantidas: $_GET,
            intResultadosPorPagina: $this->limite
        );
        $obj_permissoes = new clsPermissoes;

        if ($obj_permissoes->permissao_cadastra(int_processo_ap: 635, int_idpes_usuario: $this->pessoa_logada, int_soma_nivel_acesso: 7)) {
            $this->acao = 'go("educar_servidor_cad.php")';
            $this->nome_acao = 'Novo';
        }

        $this->largura = '100%';

        $this->breadcrumb(currentPage: 'Funções do servidor', breadcrumbs: [
            url(path: 'intranet/educar_servidores_index.php') => 'Servidores',
        ]);
    }

    /**
     * Obtém a lista de servidores que têm alerta:
     * - Vinculados a turma
     * - Com alocação automática criada pelo sistema
     * - Carga horária '00:00:00'
     * - No ano vigente
     *
     * @param int $anoVigente
     * @return array
     */
    private function getServidoresComAlertaAlocacao($anoVigente)
    {
        try {
            // Busca servidores que têm alocação com carga horária '00:00:00' no ano vigente
            // E que estão vinculados a alguma turma
            $sql = "SELECT DISTINCT s.cod_servidor
                    FROM pmieducar.servidor s
                    INNER JOIN pmieducar.servidor_alocacao sa ON 
                        sa.ref_cod_servidor = s.cod_servidor
                        AND sa.ativo = 1
                        AND sa.carga_horaria = '00:00:00'
                        AND sa.ano = {$anoVigente}
                    INNER JOIN modules.professor_turma pt ON 
                        pt.servidor_id = s.cod_servidor
                        AND pt.ano = {$anoVigente}
                    INNER JOIN pmieducar.turma t ON 
                        pt.turma_id = t.cod_turma
                        AND t.ano = {$anoVigente}";
            
            $db = new clsBanco();
            $db->Consulta($sql);
            
            $servidores = [];
            while ($db->ProximoRegistro()) {
                $tupla = $db->Tupla();
                $servidores[] = (int) $tupla['cod_servidor'];
            }
            
            return $servidores;
        } catch (Exception $e) {
            if (isset($_GET['debug']) && $_GET['debug'] == 1) {
                echo "<div style='background: #ffcccc; padding: 10px; margin: 10px; border: 1px solid #ff0000;'>";
                echo "<strong>ERRO na consulta:</strong><br>";
                echo $e->getMessage();
                echo "</div>";
            }
            return [];
        }
    }

    public function Formular()
    {
        $this->title = 'Servidor';
        $this->processoAp = 635;
    }
};