<?php

/**
 * Classe para unificação de pessoas
 * 
 * @package App_Unificacao
 * @version 2.0.0
 */
class App_Unificacao_Pessoa extends App_Unificacao_Base
{
    /**
     * Tabelas que devem manter o primeiro vínculo (DELETE + UPDATE)
     * ORDEM CORRETA: Filhos primeiro, pais depois
     */
    protected $chavesManterPrimeiroVinculo = [
        // ===== FILHOS DE cadastro.fisica (devem ser processados ANTES) =====
        [
            'tabela' => 'cadastro.fisica_deficiencia',
            'coluna' => 'ref_idpes',
        ],
        [
            'tabela' => 'cadastro.fisica_raca',
            'coluna' => 'ref_idpes',
        ],
        [
            'tabela' => 'cadastro.fisica_foto',
            'coluna' => 'idpes',
        ],
        [
            'tabela' => 'cadastro.fone_pessoa',
            'coluna' => 'idpes',
        ],
        [
            'tabela' => 'cadastro.documento',
            'coluna' => 'idpes',
        ],
        [
            'tabela' => 'public.person_has_place',
            'coluna' => 'person_id',
        ],
        
        // ===== cadastro.fisica (pode ser deletado agora) =====
        [
            'tabela' => 'cadastro.fisica',
            'coluna' => 'idpes',
        ],
        
        // ===== TABELAS QUE REFERENCIAM cadastro.pessoa =====
        [
            'tabela' => 'pmieducar.escola_usuario',
            'coluna' => 'ref_cod_usuario',
        ],
        [
            'tabela' => 'pmieducar.usuario',
            'coluna' => 'cod_usuario',
        ],
        [
            'tabela' => 'modules.pessoa_transporte',
            'coluna' => 'cod_pessoa_transporte',
        ],
        [
            'tabela' => 'pmieducar.aluno',
            'coluna' => 'ref_idpes',
        ],
        
        // ===== cadastro.pessoa (RAIZ - DEVE SER A ÚLTIMA) =====
        [
            'tabela' => 'cadastro.pessoa',
            'coluna' => 'idpes',
        ],
    ];

    /**
     * Tabelas que devem manter TODOS os vínculos (apenas UPDATE)
     */
    protected $chavesManterTodosVinculos = [
        // ===== RELAÇÕES DE cadastro.fisica =====
        [
            'tabela' => 'cadastro.fisica',
            'coluna' => 'idpes_mae',
        ],
        [
            'tabela' => 'cadastro.fisica',
            'coluna' => 'idpes_pai',
        ],
        [
            'tabela' => 'cadastro.fisica',
            'coluna' => 'idpes_responsavel',
        ],
        [
            'tabela' => 'cadastro.fisica',
            'coluna' => 'idpes_con',
        ],
        [
            'tabela' => 'cadastro.fisica',
            'coluna' => 'idpes_rev',
        ],
        [
            'tabela' => 'cadastro.fisica',
            'coluna' => 'idpes_cad',
        ],
        
        // ===== RELAÇÕES DE cadastro.fone_pessoa =====
        [
            'tabela' => 'cadastro.fone_pessoa',
            'coluna' => 'idpes_rev',
        ],
        [
            'tabela' => 'cadastro.fone_pessoa',
            'coluna' => 'idpes_cad',
        ],
        
        // ===== RELAÇÕES DE cadastro.raca =====
        [
            'tabela' => 'cadastro.raca',
            'coluna' => 'idpes_exc',
        ],
        [
            'tabela' => 'cadastro.raca',
            'coluna' => 'idpes_cad',
        ],
        
        // ===== RELAÇÕES DE cadastro.juridica =====
        [
            'tabela' => 'cadastro.juridica',
            'coluna' => 'idpes',
        ],
        [
            'tabela' => 'cadastro.juridica',
            'coluna' => 'idpes_rev',
        ],
        [
            'tabela' => 'cadastro.juridica',
            'coluna' => 'idpes_cad',
        ],
        
        // ===== MÓDULOS DE TRANSPORTE =====
        [
            'tabela' => 'modules.motorista',
            'coluna' => 'ref_idpes',
        ],
        [
            'tabela' => 'modules.empresa_transporte_escolar',
            'coluna' => 'ref_idpes',
        ],
        [
            'tabela' => 'modules.empresa_transporte_escolar',
            'coluna' => 'ref_resp_idpes',
        ],
        [
            'tabela' => 'modules.pessoa_transporte',
            'coluna' => 'ref_idpes',
        ],
        [
            'tabela' => 'modules.pessoa_transporte',
            'coluna' => 'ref_idpes_destino',
        ],
        [
            'tabela' => 'modules.rota_transporte_escolar',
            'coluna' => 'ref_idpes_destino',
        ],
        
        // ===== RELAÇÕES DE cadastro.documento =====
        [
            'tabela' => 'cadastro.documento',
            'coluna' => 'idpes_rev',
        ],
        [
            'tabela' => 'cadastro.documento',
            'coluna' => 'idpes_cad',
        ],
        
        // ===== RELAÇÕES DE pmieducar.escola =====
        [
            'tabela' => 'pmieducar.escola',
            'coluna' => 'ref_idpes',
        ],
        [
            'tabela' => 'pmieducar.escola',
            'coluna' => 'ref_idpes_gestor',
        ],
        [
            'tabela' => 'pmieducar.escola',
            'coluna' => 'ref_idpes_secretario_escolar',
        ],
        
        // ===== RELAÇÕES DE cadastro.pessoa =====
        [
            'tabela' => 'cadastro.pessoa',
            'coluna' => 'idpes_cad',
        ],
        [
            'tabela' => 'cadastro.pessoa',
            'coluna' => 'idpes_rev',
        ],
        
        // ===== PORTAL =====
        [
            'tabela' => 'portal.acesso',
            'coluna' => 'cod_pessoa',
        ],
        [
            'tabela' => 'portal.agenda_compromisso',
            'coluna' => 'ref_ref_cod_pessoa_cad',
        ],
        [
            'tabela' => 'portal.agenda',
            'coluna' => 'ref_ref_cod_pessoa_own',
        ],
        [
            'tabela' => 'portal.agenda',
            'coluna' => 'ref_ref_cod_pessoa_cad',
        ],
        [
            'tabela' => 'portal.agenda',
            'coluna' => 'ref_ref_cod_pessoa_exc',
        ],
        [
            'tabela' => 'portal.agenda_responsavel',
            'coluna' => 'ref_ref_cod_pessoa_fj',
        ],
        [
            'tabela' => 'portal.funcionario',
            'coluna' => 'ref_ref_cod_pessoa_fj',
        ],
        
        // ===== OUTROS =====
        [
            'tabela' => 'pmieducar.aluno_excluidos',
            'coluna' => 'ref_idpes',
        ],
        [
            'tabela' => 'public.school_managers',
            'coluna' => 'employee_id',
        ],
    ];

    /**
     * Tabelas que terão registros deletados
     */
    protected $chavesDeletarDuplicados = [
        [
            'tabela' => 'portal.funcionario',
            'coluna' => 'ref_cod_pessoa_fj',
        ],
    ];

    /**
     * Triggers que precisam ser habilitadas
     */
    protected $triggersNecessarias = [
        [
            'tabela' => 'pmieducar.aluno',
            'trigger' => 'trigger_when_deleted_pmieducar_aluno',
        ],
    ];

    /**
     * {@inheritdoc}
     * 
     * Sobrescreve o método da classe base com a assinatura correta
     * 
     * @return void
     * @throws CoreExt_Exception
     */
    public function unifica(): void
    {
        // 1. Primeiro unifica servidores (se houver)
        $unificadorServidor = new App_Unificacao_Servidor(
            $this->codigoUnificador, 
            $this->codigosDuplicados, 
            $this->codPessoaLogada, 
            $this->db, 
            $this->unificationId
        );
        $unificadorServidor->unifica();

        // 2. Validação específica de pessoas
        $this->validaPessoas();

        // 3. Executa a unificação base
        parent::unifica();
    }

    /**
     * Validações específicas para unificação de pessoas
     * 
     * @return void
     * @throws CoreExt_Exception
     */
    protected function validaPessoas(): void
    {
        $pessoas = implode(',', array_merge([$this->codigoUnificador], $this->codigosDuplicados));
        
        // Verifica se há mais de uma pessoa vinculada a alunos ativos
        $numeroAlunos = $this->db->CampoUnico(
            "SELECT COUNT(*) AS numero_alunos 
             FROM pmieducar.aluno 
             WHERE ref_idpes IN ({$pessoas}) 
             AND ativo = 1"
        );

        if ($numeroAlunos > 1) {
            throw new CoreExt_Exception(
                'Não é permitido unificar mais de uma pessoa vinculada com alunos. ' .
                'Efetue primeiro a unificação de alunos e tente novamente.'
            );
        }

        // Validação adicional: verifica se há conflitos de CPF
        $this->validaConflitoCpf($pessoas);
    }

    /**
     * Valida se há conflitos de CPF entre as pessoas
     * 
     * @param string $pessoas
     * @return void
     * @throws CoreExt_Exception
     */
    protected function validaConflitoCpf(string $pessoas): void
    {
        $sql = "
            SELECT 
                cpf, 
                COUNT(*) AS quantidade 
            FROM cadastro.fisica 
            WHERE idpes IN ({$pessoas}) 
            AND cpf IS NOT NULL 
            GROUP BY cpf 
            HAVING COUNT(*) > 1
        ";

        $this->db->Consulta($sql);
        if ($this->db->ProximoRegistro()) {
            throw new CoreExt_Exception(
                'Não é possível unificar pessoas com CPFs diferentes. ' .
                'Verifique os cadastros antes de prosseguir.'
            );
        }
    }

    /**
     * {@inheritdoc}
     * 
     * @return void
     * @throws CoreExt_Exception
     */
    protected function validaParametros(): void
    {
        parent::validaParametros();
        $this->validaPessoas();
    }
}