<?php

use iEducar\Legacy\Model;

class clsModulesProfessorTurma extends Model
{
    public $id;

    public $ano;

    public $instituicao_id;

    public $servidor_id;

    public $turma_id;

    public $funcao_exercida;

    public $tipo_vinculo;

    public $permite_lancar_faltas_componente;

    public $turno_id;

    public $codUsuario;

    public $data_inicial;

    public $data_fim;

    public $leciona_itinerario_tecnico_profissional;

    public $area_itinerario;

    /**
     * Construtor.
     *
     * @param null $id
     * @param null $ano
     * @param null $instituicao_id
     * @param null $servidor_id
     * @param null $turma_id
     * @param null $funcao_exercida
     * @param null $tipo_vinculo
     * @param null $permite_lancar_faltas_componente
     * @param null $turno_id
     * @param null $data_inicial
     * @param null $data_fim
     * @param null $leciona_itinerario_tecnico_profissional
     * @param null $area_itinerario
     */
    public function __construct(
        $id = null,
        $ano = null,
        $instituicao_id = null,
        $servidor_id = null,
        $turma_id = null,
        $funcao_exercida = null,
        $tipo_vinculo = null,
        $permite_lancar_faltas_componente = null,
        $turno_id = null,
        $data_inicial = null,
        $data_fim = null,
        $leciona_itinerario_tecnico_profissional = null,
        $area_itinerario = null
    ) {
        $this->_schema = 'modules.';
        $this->_tabela = "{$this->_schema}professor_turma";

        $this->_campos_lista = $this->_todos_campos = ' pt.id, pt.ano, pt.instituicao_id, pt.servidor_id, pt.turma_id, pt.funcao_exercida, pt.tipo_vinculo, pt.permite_lancar_faltas_componente, pt.turno_id, pt.data_inicial, pt.data_fim, pt.leciona_itinerario_tecnico_profissional, pt.area_itinerario';

        if (is_numeric($id)) {
            $this->id = $id;
        }

        if (is_numeric($turma_id)) {
            $this->turma_id = $turma_id;
        }

        if (is_numeric($ano)) {
            $this->ano = $ano;
        }

        if (is_numeric($instituicao_id)) {
            $this->instituicao_id = $instituicao_id;
        }

        if (is_numeric($servidor_id)) {
            $this->servidor_id = $servidor_id;
        }

        if (is_numeric($funcao_exercida)) {
            $this->funcao_exercida = $funcao_exercida;
        }

        if (is_numeric($tipo_vinculo)) {
            $this->tipo_vinculo = $tipo_vinculo;
        }

        if (is_numeric($turno_id)) {
            $this->turno_id = $turno_id;
        }

        if (isset($permite_lancar_faltas_componente)) {
            $this->permite_lancar_faltas_componente = '1';
        } else {
            $this->permite_lancar_faltas_componente = '0';
        }

        if (is_string($data_inicial)) {
            $this->data_inicial = $data_inicial;
        }

        if (is_string($data_fim)) {
            $this->data_fim = $data_fim;
        }

        if (is_numeric($leciona_itinerario_tecnico_profissional)) {
            $this->leciona_itinerario_tecnico_profissional = $leciona_itinerario_tecnico_profissional;
        }

        if (is_array($area_itinerario)) {
            $this->area_itinerario = $area_itinerario;
        }
    }

    /**
     * Cria um novo registro.
     *
     * @return int|bool
     *
     * @throws Exception
     */
    public function cadastra()
    {
        if (
            is_numeric($this->turma_id)
            && is_numeric($this->funcao_exercida)
            && is_numeric($this->ano)
            && is_numeric($this->servidor_id)
            && is_numeric($this->instituicao_id)
        ) {
            $db = new clsBanco;
            $campos = '';
            $valores = '';
            $gruda = '';

            if (is_numeric($this->instituicao_id)) {
                $campos .= "{$gruda}instituicao_id";
                $valores .= "{$gruda}'{$this->instituicao_id}'";
                $gruda = ', ';
            }

            if (is_numeric($this->ano)) {
                $campos .= "{$gruda}ano";
                $valores .= "{$gruda}'{$this->ano}'";
                $gruda = ', ';
            }

            if (is_numeric($this->servidor_id)) {
                $campos .= "{$gruda}servidor_id";
                $valores .= "{$gruda}'{$this->servidor_id}'";
                $gruda = ', ';
            }

            if (is_numeric($this->turma_id)) {
                $campos .= "{$gruda}turma_id";
                $valores .= "{$gruda}'{$this->turma_id}'";
                $gruda = ', ';
            }

            if (is_numeric($this->funcao_exercida)) {
                $campos .= "{$gruda}funcao_exercida";
                $valores .= "{$gruda}'{$this->funcao_exercida}'";
                $gruda = ', ';
            }

            if (is_numeric($this->tipo_vinculo)) {
                $campos .= "{$gruda}tipo_vinculo";
                $valores .= "{$gruda}'{$this->tipo_vinculo}'";
                $gruda = ', ';
            }

            if (is_numeric($this->permite_lancar_faltas_componente)) {
                $campos .= "{$gruda}permite_lancar_faltas_componente";
                $valores .= "{$gruda}'{$this->permite_lancar_faltas_componente}'";
                $gruda = ', ';
            }

            if (is_numeric($this->turno_id)) {
                $campos .= "{$gruda}turno_id";
                $valores .= "{$gruda}'{$this->turno_id}'";
                $gruda = ', ';
            }

            if (is_string($this->data_inicial) && !empty($this->data_inicial)) {
                $campos .= "{$gruda}data_inicial";
                $valores .= "{$gruda}'{$this->data_inicial}'";
                $gruda = ', ';
            }

            if (is_string($this->data_fim) && !empty($this->data_fim)) {
                $campos .= "{$gruda}data_fim";
                $valores .= "{$gruda}'{$this->data_fim}'";
                $gruda = ', ';
            }

            if (is_numeric($this->leciona_itinerario_tecnico_profissional)) {
                $campos .= "{$gruda}leciona_itinerario_tecnico_profissional";
                $valores .= "{$gruda}'{$this->leciona_itinerario_tecnico_profissional}'";
                $gruda = ', ';
            }

            if (is_array($this->area_itinerario)) {
                $campos .= "{$gruda}area_itinerario";
                $valores .= "{$gruda} " . Portabilis_Utils_Database::arrayToPgArray($this->area_itinerario) . ' ';
                $gruda = ', ';
            }

            $campos .= "{$gruda}updated_at";
            $valores .= "{$gruda} CURRENT_TIMESTAMP";

            $db->Consulta("INSERT INTO {$this->_tabela} ( $campos ) VALUES( $valores )");

            $id = $db->InsertId("{$this->_tabela}_id_seq");
            $this->id = $id;

            // Após criar o vínculo, verifica e cria alocação se necessário
            $this->verificarECriarAlocacao();

            return $id;
        }

        return false;
    }

    /**
     * Verifica se o professor possui alocação na escola e cria automaticamente se necessário
     * Também cria a função de professor se não existir
     *
     * @return bool
     */
    private function verificarECriarAlocacao()
    {
        // Obtém a escola da turma
        $db = new clsBanco;
        $sql = "SELECT ref_ref_cod_escola 
                FROM pmieducar.turma 
                WHERE cod_turma = {$this->turma_id}";
        $escola_id = $db->UnicoCampo($sql);

        if (!$escola_id) {
            return false;
        }

        // Verifica se o professor já possui alocação ativa na escola
        $sqlAlocacao = "SELECT cod_servidor_alocacao 
                        FROM pmieducar.servidor_alocacao 
                        WHERE ref_cod_servidor = {$this->servidor_id}
                        AND ref_cod_escola = {$escola_id}
                        AND ativo = 1";
        $alocacaoExistente = $db->UnicoCampo($sqlAlocacao);

        // Se já existe alocação, não faz nada
        if ($alocacaoExistente) {
            return true;
        }

        // Obtém o código da instituição
        $sqlInstituicao = "SELECT ref_cod_instituicao 
                           FROM pmieducar.escola 
                           WHERE cod_escola = {$escola_id}";
        $instituicao_id = $db->UnicoCampo($sqlInstituicao);
        
        if (!$instituicao_id) {
            return false;
        }

        // Obtém o usuário atual para registrar como usuário de cadastro
        $usuario_cad = isset($_SESSION['id_pessoa']) ? $_SESSION['id_pessoa'] : 1;
        
        $anoAtual = $this->ano ? $this->ano : date('Y');
        $dataCadastro = date('Y-m-d H:i:s');
        
        // Primeiro, cria a função do servidor (se não existir)
        $cod_servidor_funcao = $this->criarFuncaoProfessor($this->servidor_id, $instituicao_id, $anoAtual);
        
        // Insere nova alocação com os campos obrigatórios
        $sqlInsert = "INSERT INTO pmieducar.servidor_alocacao 
                      (ref_ref_cod_instituicao, ref_usuario_cad, ref_cod_escola, ref_cod_servidor, 
                       data_cadastro, ativo, carga_horaria, ano, ref_cod_servidor_funcao)
                      VALUES 
                      ({$instituicao_id}, {$usuario_cad}, {$escola_id}, {$this->servidor_id}, 
                       '{$dataCadastro}', 1, '00:00:00', {$anoAtual}, {$cod_servidor_funcao})";
        
        $db->Consulta($sqlInsert);
        
        // Obtém o ID da alocação criada
        $alocacao_id = $db->InsertId("pmieducar.servidor_alocacao_cod_servidor_alocacao_seq");
        
        // Registra o alerta na sessão para exibição na interface
        if (session_id() == '') {
            session_start();
        }
        
        if (!isset($_SESSION['alerta_professor_sem_alocacao'])) {
            $_SESSION['alerta_professor_sem_alocacao'] = [];
        }
        
        $_SESSION['alerta_professor_sem_alocacao'][$this->servidor_id] = [
            'servidor_id' => $this->servidor_id,
            'escola_id' => $escola_id,
            'alocacao_id' => $alocacao_id,
            'data_criacao' => $dataCadastro
        ];
        
        return true;
    }

    /**
     * Cria a função de professor para o servidor
     * 
     * @param int $servidor_id ID do servidor
     * @param int $instituicao_id ID da instituição
     * @param int $ano Ano letivo
     * @return int ID da função criada ou existente
     */
    private function criarFuncaoProfessor($servidor_id, $instituicao_id, $ano)
    {
        $db = new clsBanco();
        
        // Verifica se o servidor já tem uma função ativa na instituição
        $sqlVerifica = "SELECT cod_servidor_funcao 
                        FROM pmieducar.servidor_funcao 
                        WHERE ref_cod_servidor = {$servidor_id}
                        AND ref_ref_cod_instituicao = {$instituicao_id}";
        $funcaoExistente = $db->UnicoCampo($sqlVerifica);
        
        // Se já existe função, retorna o ID
        if ($funcaoExistente) {
            return $funcaoExistente;
        }
        
        // Obtém o código da função de Professor (DOCENTE)
        $sqlFuncao = "SELECT cod_funcao 
                      FROM pmieducar.funcao 
                      WHERE (nm_funcao ILIKE '%professor%' OR nm_funcao ILIKE '%docente%')
                      AND ativo = 1
                      LIMIT 1";
        $cod_funcao = $db->UnicoCampo($sqlFuncao);
        
        // Se não encontrar, tenta pegar qualquer função ativa
        if (!$cod_funcao) {
            $sqlFuncao = "SELECT cod_funcao 
                          FROM pmieducar.funcao 
                          WHERE ativo = 1
                          LIMIT 1";
            $cod_funcao = $db->UnicoCampo($sqlFuncao);
        }
        
        // Se ainda não encontrar, usa o código padrão 1
        if (!$cod_funcao) {
            $cod_funcao = 1;
        }
        
        $dataCadastro = date('Y-m-d H:i:s');
        
        // Insere a função do servidor
        $sqlInsert = "INSERT INTO pmieducar.servidor_funcao 
                      (ref_ref_cod_instituicao, ref_cod_servidor, ref_cod_funcao)
                      VALUES 
                      ({$instituicao_id}, {$servidor_id}, {$cod_funcao})";
        
        $db->Consulta($sqlInsert);
        
        // Obtém o ID da função criada
        $cod_servidor_funcao = $db->InsertId("pmieducar.servidor_funcao_seq");
        
        return $cod_servidor_funcao;
    }

    /**
     * Cria vínculo do professor com a turma (se não existir) e cria alocação automática
     * e função de professor
     *
     * @param int $servidor_id ID do servidor/professor
     * @param int $turma_id ID da turma
     * @param int $ano Ano letivo
     * @param int $funcao_exercida Função exercida (docente, etc)
     * @param int $instituicao_id ID da instituição
     * @param array $params Parâmetros adicionais
     * @return int|bool ID do vínculo criado ou false em caso de erro
     */
    public static function criarVinculoComAlocacao($servidor_id, $turma_id, $ano, $funcao_exercida, $instituicao_id, $params = [])
    {
        $db = new clsBanco();
        
        // Verifica se já existe vínculo
        $sqlVerifica = "SELECT id 
                        FROM modules.professor_turma 
                        WHERE servidor_id = {$servidor_id}
                        AND turma_id = {$turma_id}
                        AND ano = {$ano}";
        $vinculoExistente = $db->UnicoCampo($sqlVerifica);
        
        if ($vinculoExistente) {
            return $vinculoExistente;
        }
        
        // Obtém a escola da turma
        $sqlEscola = "SELECT ref_ref_cod_escola, ref_cod_curso, ref_ref_cod_serie
                      FROM pmieducar.turma 
                      WHERE cod_turma = {$turma_id}";
        $db->Consulta($sqlEscola);
        $db->ProximoRegistro();
        $turma = $db->Tupla();
        $escola_id = $turma['ref_ref_cod_escola'];
        
        // Obtém o código da instituição
        $sqlInstituicao = "SELECT ref_cod_instituicao 
                           FROM pmieducar.escola 
                           WHERE cod_escola = {$escola_id}";
        $instituicao_id_aloc = $db->UnicoCampo($sqlInstituicao);
        
        // Verifica se o professor já possui alocação na escola
        $sqlAlocacao = "SELECT cod_servidor_alocacao 
                        FROM pmieducar.servidor_alocacao 
                        WHERE ref_cod_servidor = {$servidor_id}
                        AND ref_cod_escola = {$escola_id}
                        AND ativo = 1";
        $alocacaoExistente = $db->UnicoCampo($sqlAlocacao);
        
        // Se não tem alocação, cria automaticamente
        if (!$alocacaoExistente && $instituicao_id_aloc) {
            // Primeiro, cria a função do servidor
            $cod_servidor_funcao = self::criarFuncaoProfessorEstatico($servidor_id, $instituicao_id_aloc, $ano);
            
            $usuario_cad = isset($_SESSION['id_pessoa']) ? $_SESSION['id_pessoa'] : 1;
            $dataCadastro = date('Y-m-d H:i:s');
            
            $sqlInsertAlocacao = "INSERT INTO pmieducar.servidor_alocacao 
                                  (ref_ref_cod_instituicao, ref_usuario_cad, ref_cod_escola, ref_cod_servidor, 
                                   data_cadastro, ativo, carga_horaria, ano, ref_cod_servidor_funcao)
                                  VALUES 
                                  ({$instituicao_id_aloc}, {$usuario_cad}, {$escola_id}, {$servidor_id}, 
                                   '{$dataCadastro}', 1, '00:00:00', {$ano}, {$cod_servidor_funcao})";
            $db->Consulta($sqlInsertAlocacao);
        }
        
        // Cria o vínculo do professor com a turma
        $campos = "ano, instituicao_id, servidor_id, turma_id, funcao_exercida, updated_at";
        $valores = "'{$ano}', '{$instituicao_id}', '{$servidor_id}', '{$turma_id}', '{$funcao_exercida}', CURRENT_TIMESTAMP";
        
        // Adiciona campos opcionais se fornecidos
        if (isset($params['tipo_vinculo']) && is_numeric($params['tipo_vinculo'])) {
            $campos .= ", tipo_vinculo";
            $valores .= ", '{$params['tipo_vinculo']}'";
        }
        
        if (isset($params['permite_lancar_faltas_componente'])) {
            $campos .= ", permite_lancar_faltas_componente";
            $valores .= ", '" . ($params['permite_lancar_faltas_componente'] ? '1' : '0') . "'";
        }
        
        if (isset($params['turno_id']) && is_numeric($params['turno_id'])) {
            $campos .= ", turno_id";
            $valores .= ", '{$params['turno_id']}'";
        }
        
        if (isset($params['data_inicial']) && !empty($params['data_inicial'])) {
            $campos .= ", data_inicial";
            $valores .= ", '{$params['data_inicial']}'";
        }
        
        if (isset($params['data_fim']) && !empty($params['data_fim'])) {
            $campos .= ", data_fim";
            $valores .= ", '{$params['data_fim']}'";
        }
        
        $sqlInsert = "INSERT INTO modules.professor_turma ( {$campos} ) VALUES ( {$valores} )";
        $db->Consulta($sqlInsert);
        
        $vinculo_id = $db->InsertId("modules.professor_turma_id_seq");
        
        // Registra alerta na sessão se a alocação foi criada automaticamente
        if (!$alocacaoExistente) {
            if (session_id() == '') {
                session_start();
            }
            
            if (!isset($_SESSION['alerta_professor_sem_alocacao'])) {
                $_SESSION['alerta_professor_sem_alocacao'] = [];
            }
            
            $_SESSION['alerta_professor_sem_alocacao'][$servidor_id] = [
                'servidor_id' => $servidor_id,
                'escola_id' => $escola_id,
                'data_criacao' => date('Y-m-d H:i:s')
            ];
        }
        
        return $vinculo_id;
    }

    /**
     * Cria a função de professor para o servidor (versão estática)
     * 
     * @param int $servidor_id ID do servidor
     * @param int $instituicao_id ID da instituição
     * @param int $ano Ano letivo
     * @return int ID da função criada ou existente
     */
    private static function criarFuncaoProfessorEstatico($servidor_id, $instituicao_id, $ano)
    {
        $db = new clsBanco();
        
        // Verifica se o servidor já tem uma função ativa na instituição
        $sqlVerifica = "SELECT cod_servidor_funcao 
                        FROM pmieducar.servidor_funcao 
                        WHERE ref_cod_servidor = {$servidor_id}
                        AND ref_ref_cod_instituicao = {$instituicao_id}";
        $funcaoExistente = $db->UnicoCampo($sqlVerifica);
        
        // Se já existe função, retorna o ID
        if ($funcaoExistente) {
            return $funcaoExistente;
        }
        
        // Obtém o código da função de Professor (DOCENTE)
        $sqlFuncao = "SELECT cod_funcao 
                      FROM pmieducar.funcao 
                      WHERE (nm_funcao ILIKE '%professor%' OR nm_funcao ILIKE '%docente%')
                      AND ativo = 1
                      LIMIT 1";
        $cod_funcao = $db->UnicoCampo($sqlFuncao);
        
        // Se não encontrar, tenta pegar qualquer função ativa
        if (!$cod_funcao) {
            $sqlFuncao = "SELECT cod_funcao 
                          FROM pmieducar.funcao 
                          WHERE ativo = 1
                          LIMIT 1";
            $cod_funcao = $db->UnicoCampo($sqlFuncao);
        }
        
        // Se ainda não encontrar, usa o código padrão 1
        if (!$cod_funcao) {
            $cod_funcao = 1;
        }
        
        // Insere a função do servidor
        $sqlInsert = "INSERT INTO pmieducar.servidor_funcao 
                      (ref_ref_cod_instituicao, ref_cod_servidor, ref_cod_funcao)
                      VALUES 
                      ({$instituicao_id}, {$servidor_id}, {$cod_funcao})";
        
        $db->Consulta($sqlInsert);
        
        // Obtém o ID da função criada
        $cod_servidor_funcao = $db->InsertId("pmieducar.servidor_funcao_seq");
        
        return $cod_servidor_funcao;
    }

    /**
     * Edita os dados de um registro.
     *
     * @return bool
     *
     * @throws Exception
     */
    public function edita()
    {
        if (
            is_numeric($this->id)
            && is_numeric($this->turma_id)
            && is_numeric($this->funcao_exercida)
            && is_numeric($this->ano)
            && is_numeric($this->servidor_id)
            && is_numeric($this->instituicao_id)
        ) {
            $db = new clsBanco;
            $set = '';
            $gruda = '';

            if (is_numeric($this->ano)) {
                $set .= "{$gruda}ano = '{$this->ano}'";
                $gruda = ', ';
            }

            if (is_numeric($this->instituicao_id)) {
                $set .= "{$gruda}instituicao_id = '{$this->instituicao_id}'";
                $gruda = ', ';
            }

            if (is_numeric($this->servidor_id)) {
                $set .= "{$gruda}servidor_id = '{$this->servidor_id}'";
                $gruda = ', ';
            }

            if (is_numeric($this->turma_id)) {
                $set .= "{$gruda}turma_id = '{$this->turma_id}'";
                $gruda = ', ';
            }

            if (is_numeric($this->funcao_exercida)) {
                $set .= "{$gruda}funcao_exercida = '{$this->funcao_exercida}'";
                $gruda = ', ';
            }

            if (is_numeric($this->tipo_vinculo)) {
                $set .= "{$gruda}tipo_vinculo = '{$this->tipo_vinculo}'";
                $gruda = ', ';
            } elseif (is_null($this->tipo_vinculo)) {
                $set .= "{$gruda}tipo_vinculo = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->permite_lancar_faltas_componente)) {
                $set .= "{$gruda}permite_lancar_faltas_componente = '{$this->permite_lancar_faltas_componente}'";
                $gruda = ', ';
            }

            if (is_numeric($this->turno_id)) {
                $set .= "{$gruda}turno_id = '{$this->turno_id}'";
                $gruda = ', ';
            } elseif (is_null($this->turno_id)) {
                $set .= "{$gruda}turno_id = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->leciona_itinerario_tecnico_profissional)) {
                $set .= "{$gruda}leciona_itinerario_tecnico_profissional = '{$this->leciona_itinerario_tecnico_profissional}'";
                $gruda = ', ';
            } elseif (is_null($this->leciona_itinerario_tecnico_profissional)) {
                $set .= "{$gruda}leciona_itinerario_tecnico_profissional = NULL";
                $gruda = ', ';
            }

            if (is_array($this->area_itinerario)) {
                $set .= "{$gruda} area_itinerario = " . Portabilis_Utils_Database::arrayToPgArray($this->area_itinerario) . ' ';
                $gruda = ', ';
            } else {
                $set .= "{$gruda} area_itinerario = NULL";
                $gruda = ', ';
            }

            if (is_string($this->data_inicial) && !empty($this->data_inicial)) {
                $set .= "{$gruda}data_inicial = '{$this->data_inicial}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}data_inicial = NULL ";
                $gruda = ', ';
            }

            if (is_string($this->data_fim) && !empty($this->data_fim)) {
                $set .= "{$gruda}data_fim = '{$this->data_fim}'";
            } else {
                $set .= "{$gruda}data_fim = NULL ";
            }

            $set .= "{$gruda}updated_at = CURRENT_TIMESTAMP";
            $gruda = ', ';

            if ($set) {
                $this->detalhe();
                $db->Consulta("UPDATE {$this->_tabela} SET $set WHERE id = '{$this->id}'");
                $this->detalhe();

                return true;
            }
        }

        return false;
    }

    /**
     * Retorna uma lista de registros filtrados de acordo com os parâmetros.
     *
     * @param null $servidor_id
     * @param null $instituicao_id
     * @param null $ano
     * @param null $ref_cod_escola
     * @param null $ref_cod_curso
     * @param null $ref_cod_serie
     * @param null $ref_cod_turma
     * @param null $funcao_exercida
     * @param null $tipo_vinculo
     * @return array|bool
     *
     * @throws Exception
     */
    public function lista(
        $servidor_id = null,
        $instituicao_id = null,
        $ano = null,
        $ref_cod_escola = null,
        $ref_cod_curso = null,
        $ref_cod_serie = null,
        $ref_cod_turma = null,
        $funcao_exercida = null,
        $tipo_vinculo = null
    ) {
        $sql = "

            SELECT
                {$this->_campos_lista},
                t.nm_turma,
                t.cod_turma as ref_cod_turma,
                t.ref_ref_cod_serie as ref_cod_serie,
                textcat_all(s.nm_serie) AS nm_serie,
                t.ref_cod_curso,
                textcat_all(DISTINCT c.nm_curso) AS nm_curso,
                t.ref_ref_cod_escola as ref_cod_escola,
                p.nome as nm_escola
            FROM {$this->_tabela} pt
        ";
        $filtros = '
            JOIN pmieducar.turma t ON pt.turma_id = t.cod_turma
            LEFT JOIN pmieducar.turma_serie ts ON ts.turma_id = t.cod_turma
            JOIN pmieducar.serie s ON s.cod_serie = coalesce(ts.serie_id, t.ref_ref_cod_serie)
            JOIN pmieducar.curso c ON s.ref_cod_curso = c.cod_curso
            JOIN pmieducar.escola e ON t.ref_ref_cod_escola = e.cod_escola
            JOIN cadastro.pessoa p ON e.ref_idpes = p.idpes
        WHERE true ';

        $whereAnd = ' AND ';

        if (is_numeric($servidor_id)) {
            $filtros .= "{$whereAnd} pt.servidor_id = '{$servidor_id}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($instituicao_id)) {
            $filtros .= "{$whereAnd} pt.instituicao_id = '{$instituicao_id}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($ano)) {
            $filtros .= "{$whereAnd} pt.ano = '{$ano}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($ref_cod_escola)) {
            $filtros .= "{$whereAnd} t.ref_ref_cod_escola = '{$ref_cod_escola}'";
            $whereAnd = ' AND ';
        } elseif ($this->codUsuario) {
            $filtros .= "{$whereAnd} EXISTS (SELECT 1
                                         FROM pmieducar.escola_usuario
                                        WHERE escola_usuario.ref_cod_escola = t.ref_ref_cod_escola
                                          AND escola_usuario.ref_cod_usuario = '{$this->codUsuario}')";
            $whereAnd = ' AND ';
        }

        if (is_numeric($ref_cod_curso)) {
            $filtros .= "{$whereAnd} t.ref_cod_curso = '{$ref_cod_curso}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($ref_cod_serie)) {
            $filtros .= "{$whereAnd} t.ref_ref_cod_serie = '{$ref_cod_serie}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($ref_cod_turma)) {
            $filtros .= "{$whereAnd} t.cod_turma = '{$ref_cod_turma}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($funcao_exercida)) {
            $filtros .= "{$whereAnd} pt.funcao_exercida = '{$funcao_exercida}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($tipo_vinculo)) {
            $filtros .= "{$whereAnd} pt.tipo_vinculo = '{$tipo_vinculo}'";
            $whereAnd = ' AND ';
        }

        $db = new clsBanco;
        $countCampos = count(explode(',', $this->_campos_lista)) + 8;
        $resultado = [];

        $groupBy = '
            GROUP BY
                pt.id,
                t.cod_turma,
                p.nome
        ';

        $sql .= $filtros . $groupBy . $this->getOrderby() . $this->getLimite();

        $this->_total = $db->CampoUnico("SELECT COUNT(0) FROM {$this->_tabela} pt {$filtros}");

        $db->Consulta($sql);

        if ($countCampos > 1) {
            while ($db->ProximoRegistro()) {
                $tupla = $db->Tupla();
                $tupla['_total'] = $this->_total;
                $resultado[] = $tupla;
            }
        } else {
            while ($db->ProximoRegistro()) {
                $tupla = $db->Tupla();
                $resultado[] = $tupla[$this->_campos_lista];
            }
        }
        if (count($resultado)) {
            return $resultado;
        }

        return false;
    }

    /**
     * Retorna um array com os dados de um registro.
     *
     * @return array|bool
     *
     * @throws Exception
     */
    public function detalhe()
    {
        if (is_numeric($this->id)) {
            $db = new clsBanco;
            $db->Consulta("SELECT {$this->_campos_lista}, t.nm_turma, s.nm_serie, c.nm_curso, p.nome as nm_escola
                     FROM {$this->_tabela} pt, pmieducar.turma t, pmieducar.serie s, pmieducar.curso c,
                     pmieducar.escola e, cadastro.pessoa p
                     WHERE pt.turma_id = t.cod_turma AND t.ref_ref_cod_serie = s.cod_serie AND s.ref_cod_curso = c.cod_curso
                     AND t.ref_ref_cod_escola = e.cod_escola AND e.ref_idpes = p.idpes AND id = '{$this->id}'");
            $db->ProximoRegistro();

            return $db->Tupla();
        }

        return false;
    }

    /**
     * Retorna um array com os dados de um registro.
     *
     * @return array|false
     *
     * @throws Exception
     */
    public function existe()
    {
        if (is_numeric($this->id)) {
            $db = new clsBanco;
            $db->Consulta("SELECT 1 FROM {$this->_tabela} pt WHERE id = '{$this->id}'");
            $db->ProximoRegistro();

            return $db->Tupla();
        }

        return false;
    }

    /**
     * @return int|bool
     */
    public function existe2()
    {
        if (
            is_numeric($this->ano)
            && is_numeric($this->instituicao_id)
            && is_numeric($this->servidor_id)
            && is_numeric($this->turma_id)
        ) {
            $db = new clsBanco;
            $sql = "SELECT id FROM {$this->_tabela} pt WHERE ano = '{$this->ano}' AND turma_id = '{$this->turma_id}'
               AND instituicao_id = '{$this->instituicao_id}' AND servidor_id = '{$this->servidor_id}' ";

            if (is_numeric($this->id)) {
                $sql .= " AND id <> {$this->id}";
            }

            return $db->UnicoCampo($sql);
        }

        return false;
    }

    /**
     * Exclui um registro.
     *
     * @return bool
     *
     * @throws Exception
     */
    public function excluir()
    {
        if (is_numeric($this->id)) {
            $this->detalhe();
            $sql = "DELETE FROM {$this->_tabela} pt WHERE id = '{$this->id}'";
            $db = new clsBanco;
            $db->Consulta($sql);

            return true;
        }

        return false;
    }

    public function gravaComponentes($professor_turma_id, $componentes)
    {
        $this->excluiComponentes($professor_turma_id);
        $db = new clsBanco;
        foreach ($componentes as $componente) {
            $db->Consulta("INSERT INTO modules.professor_turma_disciplina VALUES ({$professor_turma_id},{$componente})");
        }
    }

    public function excluiComponentes($professor_turma_id)
    {
        $db = new clsBanco;
        $db->Consulta("DELETE FROM modules.professor_turma_disciplina WHERE professor_turma_id = {$professor_turma_id}");
    }

    public function retornaComponentesVinculados($professor_turma_id)
    {
        $componentesVinculados = [];
        $sql = "SELECT componente_curricular_id
                  FROM modules.professor_turma_disciplina
                 WHERE professor_turma_id = {$professor_turma_id}";
        $db = new clsBanco;
        $db->Consulta($sql);
        while ($db->ProximoRegistro()) {
            $tupla = $db->Tupla();
            $componentesVinculados[] = $tupla['componente_curricular_id'];
        }

        return $componentesVinculados;
    }

    public function retornaNomeDoComponente($idComponente)
    {
        $mapperComponente = new ComponenteCurricular_Model_ComponenteDataMapper;
        $componente = $mapperComponente->find(['id' => $idComponente]);

        return $componente->nome;
    }
}