<?php

use iEducar\Legacy\Model;

class clsPmieducarEscola extends Model
{
    public $cod_escola;

    public $ref_usuario_cad;

    public $ref_usuario_exc;

    public $ref_cod_instituicao;

    public $zona_localizacao;

    public $ref_idpes;

    public $sigla;

    public $data_cadastro;

    public $data_exclusao;

    public $ativo;

    public $situacao_funcionamento;

    public $dependencia_administrativa;

    public $latitude;

    public $longitude;

    public $regulamentacao;

    public $acesso;

    public $ref_idpes_gestor;

    public $cargo_gestor;

    public $email_gestor;

    public $local_funcionamento;

    public $condicao;

    public $predio_compartilhado_outra_escola;

    public $codigo_inep_escola_compartilhada;

    public $codigo_inep_escola_compartilhada2;

    public $codigo_inep_escola_compartilhada3;

    public $codigo_inep_escola_compartilhada4;

    public $codigo_inep_escola_compartilhada5;

    public $codigo_inep_escola_compartilhada6;

    public $decreto_criacao;

    public $abastecimento_agua = false;

    public $abastecimento_energia = false;

    public $esgoto_sanitario = false;

    public $destinacao_lixo = false;

    public $tratamento_lixo = false;

    public $agua_potavel_consumo = false;

    public $alimentacao_escolar_alunos = false;

    public $compartilha_espacos_atividades_integracao = false;

    public $usa_espacos_equipamentos_atividades_regulares = false;

    public $salas_funcionais = false;

    public $salas_gerais = false;

    public $banheiros = false;

    public $laboratorios = false;

    public $salas_atividades = false;

    public $dormitorios = false;

    public $areas_externas = false;

    public $recursos_acessibilidade = false;

    public $possui_dependencias = false;

    public $numero_salas_utilizadas_dentro_predio = false;

    public $numero_salas_utilizadas_fora_predio = false;

    public $numero_salas_climatizadas = false;

    public $numero_salas_acessibilidade = false;

    public $total_funcionario;

    public $atendimento_aee;

    public $atividade_complementar;

    public $fundamental_ciclo;

    public $organizacao_ensino = false;

    public $instrumentos_pedagogicos = false;

    public $orgaos_colegiados = false;

    public $exame_selecao_ingresso = false;

    public $reserva_vagas_cotas = false;

    public $acao_area_ambiental = false;

    public $acoes_area_ambiental = false;

    public $projeto_politico_pedagogico = false;

    public $localizacao_diferenciada;

    public $materiais_didaticos_especificos;

    public $educacao_indigena;

    public $lingua_ministrada;

    public $codigo_lingua_indigena;

    public $equipamentos = false;

    public $uso_internet = false;

    public $rede_local = false;

    public $equipamentos_acesso_internet = false;

    public $quantidade_computadores_alunos_mesa = false;

    public $quantidade_computadores_alunos_portateis = false;

    public $quantidade_computadores_alunos_tablets = false;

    public $lousas_digitais = false;

    public $televisoes = false;

    public $videocassetes;

    public $dvds = false;

    public $antenas_parabolicas;

    public $copiadoras;

    public $retroprojetores;

    public $impressoras;

    public $aparelhos_de_som = false;

    public $projetores_digitais = false;

    public $faxs;

    public $maquinas_fotograficas;

    public $computadores;

    public $computadores_administrativo;

    public $computadores_alunos;

    public $impressoras_multifuncionais;

    public $acesso_internet;

    public $ato_criacao;

    public $ato_autorizativo;

    public $ref_idpes_secretario_escolar;

    public $utiliza_regra_diferenciada;

    public $categoria_escola_privada;

    public $conveniada_com_poder_publico;

    public $mantenedora_escola_privada;

    public $cnpj_mantenedora_principal;

    public $orgao_vinculado_escola;

    public $unidade_vinculada_outra_instituicao;

    public $inep_escola_sede;

    public $codigo_ies;

    public $codUsuario;

    public $esfera_administrativa;

    public $qtd_secretario_escolar;

    public $qtd_auxiliar_administrativo;

    public $qtd_agronomos_horticultores;

    public $qtd_apoio_pedagogico;

    public $qtd_coordenador_turno;

    public $qtd_tecnicos;

    public $qtd_bibliotecarios;

    public $qtd_segurancas;

    public $qtd_auxiliar_servicos_gerais;

    public $qtd_nutricionistas;

    public $qtd_profissionais_preparacao;

    public $qtd_bombeiro;

    public $qtd_psicologo;

    public $qtd_fonoaudiologo;

    public $qtd_vice_diretor;

    public $qtd_orientador_comunitario;

    public $qtd_tradutor_interprete_libras_outro_ambiente;

    public $qtd_revisor_braile;

    public $nao_ha_funcionarios_para_funcoes;

    public $iddis;

    public $poder_publico_parceria_convenio;

    public $formas_contratacao_parceria_escola_secretaria_estadual;

    public $formas_contratacao_parceria_escola_secretaria_municipal;

    public $qtd_matriculas_atividade_complementar;

    public $qtd_atendimento_educacional_especializado;

    public $qtd_ensino_regular_creche_par;

    public $qtd_ensino_regular_creche_int;

    public $qtd_ensino_regular_pre_escola_par;

    public $qtd_ensino_regular_pre_escola_int;

    public $qtd_ensino_regular_ensino_fund_anos_iniciais_par;

    public $qtd_ensino_regular_ensino_fund_anos_iniciais_int;

    public $qtd_ensino_regular_ensino_fund_anos_finais_par;

    public $qtd_ensino_regular_ensino_fund_anos_finais_int;

    public $qtd_ensino_regular_ensino_med_anos_iniciais_par;

    public $qtd_ensino_regular_ensino_med_anos_iniciais_int;

    public $qtd_edu_especial_classe_especial_par;

    public $qtd_edu_especial_classe_especial_int;

    public $qtd_edu_eja_ensino_fund;

    public $qtd_edu_eja_ensino_med;

    public function __construct(
        $cod_escola = null,
        $ref_usuario_cad = null,
        $ref_usuario_exc = null,
        $ref_cod_instituicao = null,
        $zona_localizacao = null,
        $ref_idpes = null,
        $sigla = null,
        $data_cadastro = null,
        $data_exclusao = null,
        $ativo = null,
        $bloquear_lancamento_diario_anos_letivos_encerrados = null,
        $utiliza_regra_diferenciada = false
    ) {

        $this->_schema = 'pmieducar.';
        $this->_tabela = $this->_schema . 'escola';

        $this->_campos_lista = $this->_todos_campos = 'e.cod_escola, e.ref_usuario_cad, e.ref_usuario_exc, e.ref_cod_instituicao, e.zona_localizacao, e.ref_idpes, e.sigla, e.data_cadastro,
            e.data_exclusao, e.ativo, e.bloquear_lancamento_diario_anos_letivos_encerrados, e.situacao_funcionamento, e.dependencia_administrativa, e.latitude, e.longitude, e.regulamentacao, e.acesso, e.cargo_gestor, e.ref_idpes_gestor,
            e.condicao, e.predio_compartilhado_outra_escola, e.decreto_criacao, e.agua_potavel_consumo, e.abastecimento_agua, e.abastecimento_energia, e.esgoto_sanitario, e.destinacao_lixo, e.tratamento_lixo,
            e.alimentacao_escolar_alunos, e.compartilha_espacos_atividades_integracao, e.usa_espacos_equipamentos_atividades_regulares,
            e.salas_gerais, e.salas_funcionais, e.banheiros, e.laboratorios, e.salas_atividades, e.dormitorios, e.areas_externas, e.recursos_acessibilidade, e.possui_dependencias, e.numero_salas_utilizadas_dentro_predio,
            e.numero_salas_utilizadas_fora_predio, e.numero_salas_climatizadas, e.numero_salas_acessibilidade,
            e.total_funcionario, e.atendimento_aee, e.fundamental_ciclo, e.organizacao_ensino, e.instrumentos_pedagogicos, e.orgaos_colegiados, e.exame_selecao_ingresso, e.reserva_vagas_cotas, e.acao_area_ambiental, e.acoes_area_ambiental, e.projeto_politico_pedagogico, e.localizacao_diferenciada, e.materiais_didaticos_especificos, e.educacao_indigena, e.lingua_ministrada,
            e.codigo_lingua_indigena, e.atividade_complementar, e.local_funcionamento, e.codigo_inep_escola_compartilhada, e.codigo_inep_escola_compartilhada2, e.codigo_inep_escola_compartilhada3, e.codigo_inep_escola_compartilhada4,
            e.codigo_inep_escola_compartilhada5, e.codigo_inep_escola_compartilhada6, e.equipamentos, e.uso_internet, e.rede_local, e.equipamentos_acesso_internet, e.televisoes, e.videocassetes, e.dvds, e.antenas_parabolicas, e.copiadoras, e.retroprojetores, e.impressoras, e.aparelhos_de_som,
            e.quantidade_computadores_alunos_mesa, e.quantidade_computadores_alunos_portateis, e.quantidade_computadores_alunos_tablets,
            e.lousas_digitais, e.projetores_digitais, e.faxs, e.maquinas_fotograficas, e.computadores, e.computadores_administrativo, e.computadores_alunos, e.impressoras_multifuncionais, e.acesso_internet, e.ato_criacao,
            e.ato_autorizativo, e.ref_idpes_secretario_escolar, e.utiliza_regra_diferenciada, e.categoria_escola_privada, e.conveniada_com_poder_publico, e.mantenedora_escola_privada, e.cnpj_mantenedora_principal,
            e.email_gestor, e.orgao_vinculado_escola, e.esfera_administrativa, e.unidade_vinculada_outra_instituicao, e.inep_escola_sede, e.codigo_ies,
            e.qtd_secretario_escolar,
            e.qtd_auxiliar_administrativo,
            e.qtd_apoio_pedagogico,
            e.qtd_coordenador_turno,
            e.qtd_tecnicos,
            e.qtd_bibliotecarios,
            e.qtd_segurancas,
            e.qtd_auxiliar_servicos_gerais,
            e.qtd_agronomos_horticultores,
            e.qtd_nutricionistas,
            e.qtd_profissionais_preparacao,
            e.qtd_bombeiro,
            e.qtd_psicologo,
            e.qtd_fonoaudiologo,
            e.qtd_vice_diretor,
            e.qtd_orientador_comunitario,
            e.qtd_tradutor_interprete_libras_outro_ambiente,
            e.qtd_revisor_braile,
            e.nao_ha_funcionarios_para_funcoes,
            e.iddis,
            e.poder_publico_parceria_convenio,
            e.formas_contratacao_parceria_escola_secretaria_estadual,
            e.formas_contratacao_parceria_escola_secretaria_municipal,
            e.qtd_matriculas_atividade_complementar,
            e.qtd_atendimento_educacional_especializado,
            e.qtd_ensino_regular_creche_par,
            e.qtd_ensino_regular_creche_int,
            e.qtd_ensino_regular_pre_escola_par,
            e.qtd_ensino_regular_pre_escola_int,
            e.qtd_ensino_regular_ensino_fund_anos_iniciais_par,
            e.qtd_ensino_regular_ensino_fund_anos_iniciais_int,
            e.qtd_ensino_regular_ensino_fund_anos_finais_par,
            e.qtd_ensino_regular_ensino_fund_anos_finais_int,
            e.qtd_ensino_regular_ensino_med_anos_iniciais_par,
            e.qtd_ensino_regular_ensino_med_anos_iniciais_int,
            e.qtd_edu_especial_classe_especial_par,
            e.qtd_edu_especial_classe_especial_int,
            e.qtd_edu_eja_ensino_fund,
            e.qtd_edu_eja_ensino_med
          ';

        if (is_numeric($ref_usuario_cad)) {
            $this->ref_usuario_cad = $ref_usuario_cad;
        }

        if (is_numeric($ref_usuario_exc)) {
            $this->ref_usuario_exc = $ref_usuario_exc;
        }

        if (is_numeric($ref_cod_instituicao)) {
            $this->ref_cod_instituicao = $ref_cod_instituicao;
        }

        if (is_numeric($zona_localizacao)) {
            $this->zona_localizacao = $zona_localizacao;
        }

        if (is_numeric($ref_idpes)) {
            $this->ref_idpes = $ref_idpes;
        }

        if (is_numeric($cod_escola)) {
            $this->cod_escola = $cod_escola;
        }

        if (is_string($sigla)) {
            $this->sigla = $sigla;
        }

        if (is_string($data_cadastro)) {
            $this->data_cadastro = $data_cadastro;
        }

        if (is_string($data_exclusao)) {
            $this->data_exclusao = $data_exclusao;
        }

        if (is_numeric($ativo)) {
            $this->ativo = $ativo;
        }

        $this->bloquear_lancamento_diario_anos_letivos_encerrados = $bloquear_lancamento_diario_anos_letivos_encerrados;
        $this->utiliza_regra_diferenciada = $utiliza_regra_diferenciada;
    }

    /**
     * Cria um novo registro.
     *
     * @return bool
     */
    public function cadastra()
    {
        if (is_numeric($this->ref_usuario_cad) && is_numeric($this->ref_cod_instituicao) &&
            is_numeric($this->zona_localizacao) &&
            is_string($this->sigla)
        ) {
            $db = new clsBanco();

            $campos = '';
            $valores = '';
            $gruda = '';

            if (is_numeric($this->ref_usuario_cad)) {
                $campos .= "{$gruda}ref_usuario_cad";
                $valores .= "{$gruda}'{$this->ref_usuario_cad}'";
                $gruda = ', ';
            }

            if (is_numeric($this->ref_usuario_exc)) {
                $campos .= "{$gruda}ref_usuario_exc";
                $valores .= "{$gruda}'{$this->ref_usuario_exc}'";
                $gruda = ', ';
            }

            if (is_numeric($this->ref_cod_instituicao)) {
                $campos .= "{$gruda}ref_cod_instituicao";
                $valores .= "{$gruda}'{$this->ref_cod_instituicao}'";
                $gruda = ', ';
            }

            if (is_numeric($this->zona_localizacao)) {
                $campos .= "{$gruda}zona_localizacao";
                $valores .= "{$gruda}{$this->zona_localizacao}";
                $gruda = ', ';
            }

            if (is_numeric($this->ref_idpes)) {
                $campos .= "{$gruda}ref_idpes";
                $valores .= "{$gruda}'{$this->ref_idpes}'";
                $gruda = ', ';
            }

            if (is_string($this->sigla)) {
                $sigla = $db->escapeString($this->sigla);
                $campos .= "{$gruda}sigla";
                $valores .= "{$gruda}'{$sigla}'";
                $gruda = ', ';
            }

            if (is_numeric($this->bloquear_lancamento_diario_anos_letivos_encerrados)) {
                $campos .= "{$gruda}bloquear_lancamento_diario_anos_letivos_encerrados";
                $valores .= "{$gruda}'{$this->bloquear_lancamento_diario_anos_letivos_encerrados}'";
                $gruda = ', ';
            }

            $campos .= "{$gruda}utiliza_regra_diferenciada";

            if ($this->utiliza_regra_diferenciada) {
                $valores .= "{$gruda}'t'";
            } else {
                $valores .= "{$gruda}'f'";
            }

            $gruda = ', ';

            if (is_numeric($this->situacao_funcionamento)) {
                $campos .= "{$gruda}situacao_funcionamento";
                $valores .= "{$gruda}'{$this->situacao_funcionamento}'";
                $gruda = ', ';
            }

            if (is_numeric($this->dependencia_administrativa)) {
                $campos .= "{$gruda}dependencia_administrativa";
                $valores .= "{$gruda}'{$this->dependencia_administrativa}'";
                $gruda = ', ';
            }

            if (is_string($this->orgao_vinculado_escola)) {
                $campos .= "{$gruda}orgao_vinculado_escola";
                $valores .= "{$gruda}'{{" . $this->orgao_vinculado_escola . '}}\'';
                $gruda = ', ';
            }

            if (is_numeric($this->unidade_vinculada_outra_instituicao)) {
                $campos .= "{$gruda}unidade_vinculada_outra_instituicao";
                $valores .= "{$gruda}{$this->unidade_vinculada_outra_instituicao}";
                $gruda = ', ';
            }

            if (is_numeric($this->inep_escola_sede)) {
                $campos .= "{$gruda}inep_escola_sede";
                $valores .= "{$gruda}{$this->inep_escola_sede}";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_ies)) {
                $campos .= "{$gruda}codigo_ies";
                $valores .= "{$gruda}{$this->codigo_ies}";
                $gruda = ', ';
            }

            if ($this->latitude) {
                $campos .= "{$gruda}latitude";
                $valores .= "{$gruda}'{$this->latitude}'";
                $gruda = ', ';
            }

            if ($this->longitude) {
                $campos .= "{$gruda}longitude";
                $valores .= "{$gruda}'{$this->longitude}'";
                $gruda = ', ';
            }

            if (is_numeric($this->regulamentacao)) {
                $campos .= "{$gruda}regulamentacao";
                $valores .= "{$gruda}'{$this->regulamentacao}'";
                $gruda = ', ';
            }

            if (is_numeric($this->acesso)) {
                $campos .= "{$gruda}acesso";
                $valores .= "{$gruda}'{$this->acesso}'";
                $gruda = ', ';
            }

            if (is_numeric($this->ref_idpes_gestor)) {
                $campos .= "{$gruda}ref_idpes_gestor";
                $valores .= "{$gruda}'{$this->ref_idpes_gestor}'";
                $gruda = ', ';
            }

            if (is_numeric($this->cargo_gestor)) {
                $campos .= "{$gruda}cargo_gestor";
                $valores .= "{$gruda}'{$this->cargo_gestor}'";
                $gruda = ', ';
            }

            if (is_string($this->email_gestor)) {
                $campos .= "{$gruda}email_gestor";
                $valores .= "{$gruda}'{$this->email_gestor}'";
                $gruda = ', ';
            }

            if (is_string($this->local_funcionamento)) {
                $campos .= "{$gruda}local_funcionamento";
                $valores .= "{$gruda}'{{$this->local_funcionamento}}'";
                $gruda = ', ';
            }

            if (is_numeric($this->condicao)) {
                $campos .= "{$gruda}condicao";
                $valores .= "{$gruda}'{$this->condicao}'";
                $gruda = ', ';
            }

            if (is_numeric($this->predio_compartilhado_outra_escola)) {
                $campos .= "{$gruda}predio_compartilhado_outra_escola";
                $valores .= "{$gruda}'{$this->predio_compartilhado_outra_escola}'";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada)) {
                $campos .= "{$gruda}codigo_inep_escola_compartilhada";
                $valores .= "{$gruda}'{$this->codigo_inep_escola_compartilhada}'";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada2)) {
                $campos .= "{$gruda}codigo_inep_escola_compartilhada2";
                $valores .= "{$gruda}'{$this->codigo_inep_escola_compartilhada2}'";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada3)) {
                $campos .= "{$gruda}codigo_inep_escola_compartilhada3";
                $valores .= "{$gruda}'{$this->codigo_inep_escola_compartilhada3}'";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada4)) {
                $campos .= "{$gruda}codigo_inep_escola_compartilhada4";
                $valores .= "{$gruda}'{$this->codigo_inep_escola_compartilhada4}'";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada5)) {
                $campos .= "{$gruda}codigo_inep_escola_compartilhada5";
                $valores .= "{$gruda}'{$this->codigo_inep_escola_compartilhada5}'";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada6)) {
                $campos .= "{$gruda}codigo_inep_escola_compartilhada6";
                $valores .= "{$gruda}'{$this->codigo_inep_escola_compartilhada6}'";
                $gruda = ', ';
            }

            if (is_string($this->decreto_criacao)) {
                $campos .= "{$gruda}decreto_criacao";
                $valores .= "{$gruda}'{$this->decreto_criacao}'";
                $gruda = ', ';
            }

            if (is_numeric($this->agua_potavel_consumo)) {
                $campos .= "{$gruda}agua_potavel_consumo";
                $valores .= "{$gruda}'{$this->agua_potavel_consumo}'";
                $gruda = ', ';
            }

            if (is_string($this->abastecimento_agua)) {
                $campos .= "{$gruda}abastecimento_agua";
                $valores .= "{$gruda}'{{$this->abastecimento_agua}}'";
                $gruda = ', ';
            }

            if (is_string($this->abastecimento_energia)) {
                $campos .= "{$gruda}abastecimento_energia";
                $valores .= "{$gruda}'{{$this->abastecimento_energia}}'";
                $gruda = ', ';
            }

            if (is_string($this->esgoto_sanitario)) {
                $campos .= "{$gruda}esgoto_sanitario";
                $valores .= "{$gruda}'{{$this->esgoto_sanitario}}'";
                $gruda = ', ';
            }

            if (is_string($this->destinacao_lixo)) {
                $campos .= "{$gruda}destinacao_lixo";
                $valores .= "{$gruda}'{{$this->destinacao_lixo}}'";
                $gruda = ', ';
            }

            if (is_string($this->tratamento_lixo)) {
                $campos .= "{$gruda}tratamento_lixo";
                $valores .= "{$gruda}'{{$this->tratamento_lixo}}'";
                $gruda = ', ';
            }

            if (is_numeric($this->alimentacao_escolar_alunos)) {
                $campos .= "{$gruda}alimentacao_escolar_alunos";
                $valores .= "{$gruda}'{$this->alimentacao_escolar_alunos}'";
                $gruda = ', ';
            }

            if (is_numeric($this->compartilha_espacos_atividades_integracao)) {
                $campos .= "{$gruda}compartilha_espacos_atividades_integracao";
                $valores .= "{$gruda}'{$this->compartilha_espacos_atividades_integracao}'";
                $gruda = ', ';
            }

            if (is_numeric($this->usa_espacos_equipamentos_atividades_regulares)) {
                $campos .= "{$gruda}usa_espacos_equipamentos_atividades_regulares";
                $valores .= "{$gruda}'{$this->usa_espacos_equipamentos_atividades_regulares}'";
                $gruda = ', ';
            }

            if (is_string($this->salas_funcionais)) {
                $campos .= "{$gruda}salas_funcionais";
                $valores .= "{$gruda}'{{$this->salas_funcionais}}'";
                $gruda = ', ';
            }

            if (is_string($this->salas_gerais)) {
                $campos .= "{$gruda}salas_gerais";
                $valores .= "{$gruda}'{{$this->salas_gerais}}'";
                $gruda = ', ';
            }

            if (is_string($this->banheiros)) {
                $campos .= "{$gruda}banheiros";
                $valores .= "{$gruda}'{{$this->banheiros}}'";
                $gruda = ', ';
            }

            if (is_string($this->laboratorios)) {
                $campos .= "{$gruda}laboratorios";
                $valores .= "{$gruda}'{{$this->laboratorios}}'";
                $gruda = ', ';
            }

            if (is_string($this->salas_atividades)) {
                $campos .= "{$gruda}salas_atividades";
                $valores .= "{$gruda}'{{$this->salas_atividades}}'";
                $gruda = ', ';
            }

            if (is_string($this->dormitorios)) {
                $campos .= "{$gruda}dormitorios";
                $valores .= "{$gruda}'{{$this->dormitorios}}'";
                $gruda = ', ';
            }

            if (is_string($this->areas_externas)) {
                $campos .= "{$gruda}areas_externas";
                $valores .= "{$gruda}'{{$this->areas_externas}}'";
                $gruda = ', ';
            }

            if (is_string($this->recursos_acessibilidade)) {
                $campos .= "{$gruda}recursos_acessibilidade";
                $valores .= "{$gruda}'{{$this->recursos_acessibilidade}}'";
                $gruda = ', ';
            }

            if (is_numeric($this->possui_dependencias)) {
                $campos .= "{$gruda}possui_dependencias";
                $valores .= "{$gruda}'{$this->possui_dependencias}'";
                $gruda = ', ';
            }

            if (is_numeric($this->numero_salas_utilizadas_dentro_predio)) {
                $campos .= "{$gruda}numero_salas_utilizadas_dentro_predio";
                $valores .= "{$gruda}{$this->numero_salas_utilizadas_dentro_predio}";
                $gruda = ', ';
            }

            if (is_numeric($this->numero_salas_utilizadas_fora_predio)) {
                $campos .= "{$gruda}numero_salas_utilizadas_fora_predio";
                $valores .= "{$gruda}{$this->numero_salas_utilizadas_fora_predio}";
                $gruda = ', ';
            }

            if (is_numeric($this->numero_salas_climatizadas)) {
                $campos .= "{$gruda}numero_salas_climatizadas";
                $valores .= "{$gruda}{$this->numero_salas_climatizadas}";
                $gruda = ', ';
            }

            if (is_numeric($this->numero_salas_acessibilidade)) {
                $campos .= "{$gruda}numero_salas_acessibilidade";
                $valores .= "{$gruda}{$this->numero_salas_acessibilidade}";
                $gruda = ', ';
            }

            if (is_numeric($this->total_funcionario)) {
                $campos .= "{$gruda}total_funcionario";
                $valores .= "{$gruda}'{$this->total_funcionario}'";
                $gruda = ', ';
            }

            if (is_numeric($this->atendimento_aee)) {
                $campos .= "{$gruda}atendimento_aee";
                $valores .= "{$gruda}'{$this->atendimento_aee}'";
                $gruda = ', ';
            }

            if (is_numeric($this->atividade_complementar)) {
                $campos .= "{$gruda}atividade_complementar";
                $valores .= "{$gruda}'{$this->atividade_complementar}'";
                $gruda = ', ';
            }

            if (is_numeric($this->fundamental_ciclo)) {
                $campos .= "{$gruda}fundamental_ciclo";
                $valores .= "{$gruda}'{$this->fundamental_ciclo}'";
                $gruda = ', ';
            }

            if (is_string($this->organizacao_ensino)) {
                $campos .= "{$gruda}organizacao_ensino";
                $valores .= "{$gruda}'{{$this->organizacao_ensino}}'";
                $gruda = ', ';
            }

            if (is_string($this->instrumentos_pedagogicos)) {
                $campos .= "{$gruda}instrumentos_pedagogicos";
                $valores .= "{$gruda}'{{$this->instrumentos_pedagogicos}}'";
                $gruda = ', ';
            }

            if (is_string($this->orgaos_colegiados)) {
                $campos .= "{$gruda}orgaos_colegiados";
                $valores .= "{$gruda}'{{$this->orgaos_colegiados}}'";
                $gruda = ', ';
            }

            if (is_numeric($this->exame_selecao_ingresso)) {
                $campos .= "{$gruda}exame_selecao_ingresso";
                $valores .= "{$gruda}'{$this->exame_selecao_ingresso}'";
                $gruda = ', ';
            }

            if (is_string($this->reserva_vagas_cotas)) {
                $campos .= "{$gruda}reserva_vagas_cotas";
                $valores .= "{$gruda}'{{$this->reserva_vagas_cotas}}'";
                $gruda = ', ';
            }

            if (is_numeric($this->acao_area_ambiental)) {
                $campos .= "{$gruda}acao_area_ambiental";
                $valores .= "{$gruda}'{$this->acao_area_ambiental}'";
                $gruda = ', ';
            }

            if (is_string($this->acoes_area_ambiental)) {
                $campos .= "{$gruda}acoes_area_ambiental";
                $valores .= "{$gruda}'{{$this->acoes_area_ambiental}}'";
                $gruda = ', ';
            }

            if (is_numeric($this->projeto_politico_pedagogico)) {
                $campos .= "{$gruda}projeto_politico_pedagogico";
                $valores .= "{$gruda}'{$this->projeto_politico_pedagogico}'";
                $gruda = ', ';
            }

            if (is_numeric($this->localizacao_diferenciada)) {
                $campos .= "{$gruda}localizacao_diferenciada";
                $valores .= "{$gruda}'{$this->localizacao_diferenciada}'";
                $gruda = ', ';
            }

            if (is_numeric($this->materiais_didaticos_especificos)) {
                $campos .= "{$gruda}materiais_didaticos_especificos";
                $valores .= "{$gruda}'{$this->materiais_didaticos_especificos}'";
                $gruda = ', ';
            }

            if (is_numeric($this->educacao_indigena)) {
                $campos .= "{$gruda}educacao_indigena";
                $valores .= "{$gruda}'{$this->educacao_indigena}'";
                $gruda = ', ';
            }

            if (is_numeric($this->lingua_ministrada)) {
                $campos .= "{$gruda}lingua_ministrada";
                $valores .= "{$gruda}'{$this->lingua_ministrada}'";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_lingua_indigena)) {
                $campos .= "{$gruda}codigo_lingua_indigena";
                $valores .= "{$gruda}'{{$this->codigo_lingua_indigena}}'";
                $gruda = ', ';
            }

            if (is_string($this->equipamentos)) {
                $campos .= "{$gruda}equipamentos";
                $valores .= "{$gruda}'{{$this->equipamentos}}'";
                $gruda = ', ';
            }

            if (is_string($this->uso_internet)) {
                $campos .= "{$gruda}uso_internet";
                $valores .= "{$gruda}'{{$this->uso_internet}}'";
                $gruda = ', ';
            }

            if (is_string($this->rede_local)) {
                $campos .= "{$gruda}rede_local";
                $valores .= "{$gruda}'{{$this->rede_local}}'";
                $gruda = ', ';
            }

            if (is_string($this->equipamentos_acesso_internet)) {
                $campos .= "{$gruda}equipamentos_acesso_internet";
                $valores .= "{$gruda}'{{$this->equipamentos_acesso_internet}}'";
                $gruda = ', ';
            }

            if (is_numeric($this->quantidade_computadores_alunos_mesa)) {
                $campos .= "{$gruda}quantidade_computadores_alunos_mesa";
                $valores .= "{$gruda}'{$this->quantidade_computadores_alunos_mesa}'";
                $gruda = ', ';
            }

            if (is_numeric($this->quantidade_computadores_alunos_portateis)) {
                $campos .= "{$gruda}quantidade_computadores_alunos_portateis";
                $valores .= "{$gruda}'{$this->quantidade_computadores_alunos_portateis}'";
                $gruda = ', ';
            }

            if (is_numeric($this->quantidade_computadores_alunos_tablets)) {
                $campos .= "{$gruda}quantidade_computadores_alunos_tablets";
                $valores .= "{$gruda}'{$this->quantidade_computadores_alunos_tablets}'";
                $gruda = ', ';
            }

            if (is_numeric($this->lousas_digitais)) {
                $campos .= "{$gruda}lousas_digitais";
                $valores .= "{$gruda}'{$this->lousas_digitais}'";
                $gruda = ', ';
            }

            if (is_numeric($this->televisoes)) {
                $campos .= "{$gruda}televisoes";
                $valores .= "{$gruda}'{$this->televisoes}'";
                $gruda = ', ';
            }

            if (is_numeric($this->videocassetes)) {
                $campos .= "{$gruda}videocassetes";
                $valores .= "{$gruda}'{$this->videocassetes}'";
                $gruda = ', ';
            }

            if (is_numeric($this->dvds)) {
                $campos .= "{$gruda}dvds";
                $valores .= "{$gruda}'{$this->dvds}'";
                $gruda = ', ';
            }

            if (is_numeric($this->antenas_parabolicas)) {
                $campos .= "{$gruda}antenas_parabolicas";
                $valores .= "{$gruda}'{$this->antenas_parabolicas}'";
                $gruda = ', ';
            }

            if (is_numeric($this->copiadoras)) {
                $campos .= "{$gruda}copiadoras";
                $valores .= "{$gruda}'{$this->copiadoras}'";
                $gruda = ', ';
            }

            if (is_numeric($this->retroprojetores)) {
                $campos .= "{$gruda}retroprojetores";
                $valores .= "{$gruda}'{$this->retroprojetores}'";
                $gruda = ', ';
            }

            if (is_numeric($this->impressoras)) {
                $campos .= "{$gruda}impressoras";
                $valores .= "{$gruda}'{$this->impressoras}'";
                $gruda = ', ';
            }

            if (is_numeric($this->aparelhos_de_som)) {
                $campos .= "{$gruda}aparelhos_de_som";
                $valores .= "{$gruda}'{$this->aparelhos_de_som}'";
                $gruda = ', ';
            }

            if (is_numeric($this->projetores_digitais)) {
                $campos .= "{$gruda}projetores_digitais";
                $valores .= "{$gruda}'{$this->projetores_digitais}'";
                $gruda = ', ';
            }

            if (is_numeric($this->faxs)) {
                $campos .= "{$gruda}faxs";
                $valores .= "{$gruda}'{$this->faxs}'";
                $gruda = ', ';
            }

            if (is_numeric($this->maquinas_fotograficas)) {
                $campos .= "{$gruda}maquinas_fotograficas";
                $valores .= "{$gruda}'{$this->maquinas_fotograficas}'";
                $gruda = ', ';
            }

            if (is_numeric($this->computadores)) {
                $campos .= "{$gruda}computadores";
                $valores .= "{$gruda}'{$this->computadores}'";
                $gruda = ', ';
            }

            if (is_numeric($this->computadores_administrativo)) {
                $campos .= "{$gruda}computadores_administrativo";
                $valores .= "{$gruda}'{$this->computadores_administrativo}'";
                $gruda = ', ';
            }

            if (is_numeric($this->computadores_alunos)) {
                $campos .= "{$gruda}computadores_alunos";
                $valores .= "{$gruda}'{$this->computadores_alunos}'";
                $gruda = ', ';
            }

            if (is_numeric($this->impressoras_multifuncionais)) {
                $campos .= "{$gruda}impressoras_multifuncionais";
                $valores .= "{$gruda}'{$this->impressoras_multifuncionais}'";
                $gruda = ', ';
            }

            if (is_numeric($this->acesso_internet)) {
                $campos .= "{$gruda}acesso_internet";
                $valores .= "{$gruda}'{$this->acesso_internet}'";
                $gruda = ', ';
            }

            if (is_string($this->ato_criacao)) {
                $campos .= "{$gruda}ato_criacao";
                $valores .= "{$gruda}'{$this->ato_criacao}'";
                $gruda = ', ';
            }

            if (is_string($this->ato_autorizativo)) {
                $campos .= "{$gruda}ato_autorizativo";
                $valores .= "{$gruda}'{$this->ato_autorizativo}'";
                $gruda = ', ';
            }

            if (is_numeric($this->ref_idpes_secretario_escolar)) {
                $campos .= "{$gruda}ref_idpes_secretario_escolar";
                $valores .= "{$gruda}'{$this->ref_idpes_secretario_escolar}'";
                $gruda = ', ';
            }

            if (is_numeric($this->categoria_escola_privada)) {
                $campos .= "{$gruda}categoria_escola_privada";
                $valores .= "{$gruda}'{$this->categoria_escola_privada}'";
                $gruda = ', ';
            }

            if (is_numeric($this->conveniada_com_poder_publico)) {
                $campos .= "{$gruda}conveniada_com_poder_publico";
                $valores .= "{$gruda}'{$this->conveniada_com_poder_publico}'";
                $gruda = ', ';
            }

            if (is_string($this->mantenedora_escola_privada)) {
                $campos .= "{$gruda}mantenedora_escola_privada";
                $valores .= "{$gruda}'{" . $this->mantenedora_escola_privada . '}\'';
                $gruda = ', ';
            }

            if (is_numeric($this->cnpj_mantenedora_principal)) {
                $campos .= "{$gruda}cnpj_mantenedora_principal";
                $valores .= "{$gruda}'{$this->cnpj_mantenedora_principal}'";
                $gruda = ', ';
            }

            if (is_numeric($this->esfera_administrativa)) {
                $campos .= "{$gruda}esfera_administrativa";
                $valores .= "{$gruda}'{$this->esfera_administrativa}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_secretario_escolar)) {
                $campos .= "{$gruda}qtd_secretario_escolar";
                $valores .= "{$gruda}$this->qtd_secretario_escolar";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_auxiliar_administrativo)) {
                $campos .= "{$gruda}qtd_auxiliar_administrativo";
                $valores .= "{$gruda}$this->qtd_auxiliar_administrativo";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_agronomos_horticultores)) {
                $campos .= "{$gruda}qtd_agronomos_horticultores";
                $valores .= "{$gruda}$this->qtd_agronomos_horticultores";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_apoio_pedagogico)) {
                $campos .= "{$gruda}qtd_apoio_pedagogico";
                $valores .= "{$gruda}$this->qtd_apoio_pedagogico";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_coordenador_turno)) {
                $campos .= "{$gruda}qtd_coordenador_turno";
                $valores .= "{$gruda}$this->qtd_coordenador_turno";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_tecnicos)) {
                $campos .= "{$gruda}qtd_tecnicos";
                $valores .= "{$gruda}$this->qtd_tecnicos";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_bibliotecarios)) {
                $campos .= "{$gruda}qtd_bibliotecarios";
                $valores .= "{$gruda}$this->qtd_bibliotecarios";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_segurancas)) {
                $campos .= "{$gruda}qtd_segurancas";
                $valores .= "{$gruda}$this->qtd_segurancas";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_auxiliar_servicos_gerais)) {
                $campos .= "{$gruda}qtd_auxiliar_servicos_gerais";
                $valores .= "{$gruda}$this->qtd_auxiliar_servicos_gerais";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_nutricionistas)) {
                $campos .= "{$gruda}qtd_nutricionistas";
                $valores .= "{$gruda}$this->qtd_nutricionistas";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_profissionais_preparacao)) {
                $campos .= "{$gruda}qtd_profissionais_preparacao";
                $valores .= "{$gruda}$this->qtd_profissionais_preparacao";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_bombeiro)) {
                $campos .= "{$gruda}qtd_bombeiro";
                $valores .= "{$gruda}$this->qtd_bombeiro";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_psicologo)) {
                $campos .= "{$gruda}qtd_psicologo";
                $valores .= "{$gruda}$this->qtd_psicologo";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_fonoaudiologo)) {
                $campos .= "{$gruda}qtd_fonoaudiologo";
                $valores .= "{$gruda}$this->qtd_fonoaudiologo";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_vice_diretor)) {
                $campos .= "{$gruda}qtd_vice_diretor";
                $valores .= "{$gruda}$this->qtd_vice_diretor";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_orientador_comunitario)) {
                $campos .= "{$gruda}qtd_orientador_comunitario";
                $valores .= "{$gruda}$this->qtd_orientador_comunitario";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_tradutor_interprete_libras_outro_ambiente)) {
                $campos .= "{$gruda}qtd_tradutor_interprete_libras_outro_ambiente";
                $valores .= "{$gruda}$this->qtd_tradutor_interprete_libras_outro_ambiente";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_revisor_braile)) {
                $campos .= "{$gruda}qtd_revisor_braile";
                $valores .= "{$gruda}$this->qtd_revisor_braile";
                $gruda = ', ';
            }

            if (is_int($this->iddis) && $this->iddis > 0) {
                $campos .= "{$gruda}iddis";
                $valores .= "{$gruda}$this->iddis";
                $gruda = ', ';
            }

            if ($this->nao_ha_funcionarios_para_funcoes) {
                $campos .= "{$gruda}nao_ha_funcionarios_para_funcoes";
                $valores .= "{$gruda}true";
                $gruda = ', ';
            } else {
                $campos .= "{$gruda}nao_ha_funcionarios_para_funcoes";
                $valores .= "{$gruda}false";
                $gruda = ', ';
            }

            $campos .= "{$gruda}data_cadastro";
            $valores .= "{$gruda}NOW()";
            $gruda = ', ';

            $campos .= "{$gruda}ativo";
            $valores .= "{$gruda}'1'";

            if (is_string($this->poder_publico_parceria_convenio)) {
                $campos .= "{$gruda}poder_publico_parceria_convenio";
                $valores .= "{$gruda}'{{$this->poder_publico_parceria_convenio}}'";
            }

            if (is_string($this->formas_contratacao_parceria_escola_secretaria_estadual)) {
                $campos .= "{$gruda}formas_contratacao_parceria_escola_secretaria_estadual";
                $valores .= "{$gruda}'{{$this->formas_contratacao_parceria_escola_secretaria_estadual}}'";
            }

            if (is_string($this->formas_contratacao_parceria_escola_secretaria_municipal)) {
                $campos .= "{$gruda}formas_contratacao_parceria_escola_secretaria_municipal";
                $valores .= "{$gruda}'{{$this->formas_contratacao_parceria_escola_secretaria_municipal}}'";
            }

            if (is_numeric($this->qtd_matriculas_atividade_complementar)) {
                $campos .= "{$gruda}qtd_matriculas_atividade_complementar";
                $valores .= "{$gruda}'{$this->qtd_matriculas_atividade_complementar}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_atendimento_educacional_especializado)) {
                $campos .= "{$gruda}qtd_atendimento_educacional_especializado";
                $valores .= "{$gruda}'{$this->qtd_atendimento_educacional_especializado}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_creche_par)) {
                $campos .= "{$gruda}qtd_ensino_regular_creche_par";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_creche_par}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_creche_int)) {
                $campos .= "{$gruda}qtd_ensino_regular_creche_int";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_creche_int}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_pre_escola_par)) {
                $campos .= "{$gruda}qtd_ensino_regular_pre_escola_par";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_pre_escola_par}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_pre_escola_int)) {
                $campos .= "{$gruda}qtd_ensino_regular_pre_escola_int";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_pre_escola_int}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_fund_anos_iniciais_par)) {
                $campos .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_iniciais_par";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_ensino_fund_anos_iniciais_par}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_fund_anos_iniciais_int)) {
                $campos .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_iniciais_int";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_ensino_fund_anos_iniciais_int}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_fund_anos_finais_par)) {
                $campos .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_finais_par";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_ensino_fund_anos_finais_par}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_fund_anos_finais_int)) {
                $campos .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_finais_int";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_ensino_fund_anos_finais_int}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_med_anos_iniciais_par)) {
                $campos .= "{$gruda}qtd_ensino_regular_ensino_med_anos_iniciais_par";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_ensino_med_anos_iniciais_par}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_med_anos_iniciais_int)) {
                $campos .= "{$gruda}qtd_ensino_regular_ensino_med_anos_iniciais_int";
                $valores .= "{$gruda}'{$this->qtd_ensino_regular_ensino_med_anos_iniciais_int}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_edu_especial_classe_especial_par)) {
                $campos .= "{$gruda}qtd_edu_especial_classe_especial_par";
                $valores .= "{$gruda}'{$this->qtd_edu_especial_classe_especial_par}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_edu_especial_classe_especial_int)) {
                $campos .= "{$gruda}qtd_edu_especial_classe_especial_int";
                $valores .= "{$gruda}'{$this->qtd_edu_especial_classe_especial_int}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_edu_eja_ensino_fund)) {
                $campos .= "{$gruda}qtd_edu_eja_ensino_fund";
                $valores .= "{$gruda}'{$this->qtd_edu_eja_ensino_fund}'";
                $gruda = ', ';
            }

            if (is_numeric($this->qtd_edu_eja_ensino_med)) {
                $campos .= "{$gruda}qtd_edu_eja_ensino_med";
                $valores .= "{$gruda}'{$this->qtd_edu_eja_ensino_med}'";
                $gruda = ', ';
            }

            $db->Consulta("INSERT INTO {$this->_tabela} ($campos) VALUES ($valores)");

            return $db->InsertId("{$this->_tabela}_cod_escola_seq");
        } else {
            echo "<Hbr><br>is_numeric($this->ref_usuario_cad) && is_numeric($this->ref_cod_instituicao) && is_numeric($this->zona_localizacao) && is_string($this->sigla )";
        }

        return false;
    }

    /**
     * Edita os dados de um registro.
     *
     * @return bool
     */
    public function edita()
    {
        if (is_numeric($this->cod_escola)) {
            $db = new clsBanco();
            $gruda = '';
            $set = '';

            if (is_numeric($this->ref_usuario_cad)) {
                $set .= "{$gruda}ref_usuario_cad = '{$this->ref_usuario_cad}'";
                $gruda = ', ';
            }

            if (is_numeric($this->ref_usuario_exc)) {
                $set .= "{$gruda}ref_usuario_exc = '{$this->ref_usuario_exc}'";
                $gruda = ', ';
            }

            if (is_numeric($this->ref_cod_instituicao)) {
                $set .= "{$gruda}ref_cod_instituicao = '{$this->ref_cod_instituicao}'";
                $gruda = ', ';
            }

            if (is_numeric($this->zona_localizacao)) {
                $set .= "{$gruda}zona_localizacao = '{$this->zona_localizacao}'";
                $gruda = ', ';
            }

            if (is_numeric($this->ref_idpes)) {
                $set .= "{$gruda}ref_idpes = '{$this->ref_idpes}'";
                $gruda = ', ';
            }

            if (is_string($this->sigla)) {
                $sigla = $db->escapeString($this->sigla);
                $set .= "{$gruda}sigla = '{$sigla}'";
                $gruda = ', ';
            }

            if (is_string($this->data_cadastro)) {
                $set .= "{$gruda}data_cadastro = '{$this->data_cadastro}'";
                $gruda = ', ';
            }

            $set .= "{$gruda}data_exclusao = NOW()";
            $gruda = ', ';

            if (is_numeric($this->ativo)) {
                $set .= "{$gruda}ativo = '{$this->ativo}'";
                $gruda = ', ';
            }

            if (is_numeric($this->bloquear_lancamento_diario_anos_letivos_encerrados)) {
                $set .= "{$gruda}bloquear_lancamento_diario_anos_letivos_encerrados = '{$this->bloquear_lancamento_diario_anos_letivos_encerrados}'";
                $gruda = ', ';
            }

            if ($this->nao_ha_funcionarios_para_funcoes) {
                $set .= "{$gruda}nao_ha_funcionarios_para_funcoes = true";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}nao_ha_funcionarios_para_funcoes = false";
                $gruda = ', ';
            }

            if ($this->utiliza_regra_diferenciada) {
                $set .= "{$gruda}utiliza_regra_diferenciada = 't'";
            } else {
                $set .= "{$gruda}utiliza_regra_diferenciada = 'f' ";
            }

            $gruda = ', ';

            if (is_numeric($this->situacao_funcionamento)) {
                $set .= "{$gruda}situacao_funcionamento = '{$this->situacao_funcionamento}'";
                $gruda = ', ';
            }

            if (is_numeric($this->dependencia_administrativa)) {
                $set .= "{$gruda}dependencia_administrativa = '{$this->dependencia_administrativa}'";
                $gruda = ', ';
            }

            if (is_string($this->orgao_vinculado_escola)) {
                $set .= "{$gruda}orgao_vinculado_escola = '{{$this->orgao_vinculado_escola}}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}orgao_vinculado_escola = null";
                $gruda = ', ';
            }

            if (is_numeric($this->unidade_vinculada_outra_instituicao)) {
                $set .= "{$gruda}unidade_vinculada_outra_instituicao = {$this->unidade_vinculada_outra_instituicao}";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}unidade_vinculada_outra_instituicao = null";
                $gruda = ', ';
            }

            if (is_numeric($this->inep_escola_sede)) {
                $set .= "{$gruda}inep_escola_sede = {$this->inep_escola_sede}";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}inep_escola_sede = null";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_ies)) {
                $set .= "{$gruda}codigo_ies = {$this->codigo_ies}";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}codigo_ies = null";
                $gruda = ', ';
            }

            if (is_numeric($this->latitude)) {
                $set .= "{$gruda}latitude = '{$this->latitude}'";
                $gruda = ', ';
            } elseif (is_null($this->latitude) || $this->latitude == '') {
                $set .= "{$gruda}latitude = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->longitude)) {
                $set .= "{$gruda}longitude = '{$this->longitude}'";
                $gruda = ', ';
            } elseif (is_null($this->longitude) || $this->longitude == '') {
                $set .= "{$gruda}longitude = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->regulamentacao)) {
                $set .= "{$gruda}regulamentacao = '{$this->regulamentacao}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}regulamentacao = null";
                $gruda = ', ';
            }

            if (is_numeric($this->acesso)) {
                $set .= "{$gruda}acesso = '{$this->acesso}'";
                $gruda = ', ';
            }

            // if (is_null($this->ref_idpes_gestor)){
            //   echo "oi '" . $this->ref_idpes_gestor . "'"; die;
            // }else{
            //   echo "tchau '" . $this->ref_idpes_gestor . "'"; die;
            // }

            if (is_numeric($this->ref_idpes_gestor)) {
                $set .= "{$gruda}ref_idpes_gestor = '{$this->ref_idpes_gestor}'";
                $gruda = ', ';
            } elseif (is_null($this->ref_idpes_gestor) || $this->ref_idpes_gestor == '') {
                $set .= "{$gruda}ref_idpes_gestor = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->cargo_gestor)) {
                $set .= "{$gruda}cargo_gestor = '{$this->cargo_gestor}'";
                $gruda = ', ';
            }

            if (is_string($this->email_gestor)) {
                $set .= "{$gruda}email_gestor = '{$this->email_gestor}'";
                $gruda = ', ';
            }

            if (is_string($this->local_funcionamento)) {
                $set .= "{$gruda}local_funcionamento = '{{$this->local_funcionamento}}'";
            } else {
                $set .= "{$gruda}local_funcionamento = '{}'";
            }

            $gruda = ', ';
            if (is_numeric($this->condicao)) {
                $set .= "{$gruda}condicao = '{$this->condicao}'";
            } else {
                $set .= "{$gruda}condicao = NULL ";
            }

            $gruda = ', ';
            if (is_numeric($this->predio_compartilhado_outra_escola)) {
                $set .= "{$gruda}predio_compartilhado_outra_escola = '{$this->predio_compartilhado_outra_escola}'";
            } else {
                $set .= "{$gruda}predio_compartilhado_outra_escola = NULL ";
            }

            $gruda = ', ';
            if (is_numeric($this->codigo_inep_escola_compartilhada)) {
                $set .= "{$gruda}codigo_inep_escola_compartilhada = '{$this->codigo_inep_escola_compartilhada}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}codigo_inep_escola_compartilhada = NULL ";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada2)) {
                $set .= "{$gruda}codigo_inep_escola_compartilhada2 = '{$this->codigo_inep_escola_compartilhada2}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}codigo_inep_escola_compartilhada2 = NULL ";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada3)) {
                $set .= "{$gruda}codigo_inep_escola_compartilhada3 = '{$this->codigo_inep_escola_compartilhada3}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}codigo_inep_escola_compartilhada3 = NULL ";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada4)) {
                $set .= "{$gruda}codigo_inep_escola_compartilhada4 = '{$this->codigo_inep_escola_compartilhada4}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}codigo_inep_escola_compartilhada4 = NULL ";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada5)) {
                $set .= "{$gruda}codigo_inep_escola_compartilhada5 = '{$this->codigo_inep_escola_compartilhada5}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}codigo_inep_escola_compartilhada5 = NULL ";
                $gruda = ', ';
            }

            if (is_numeric($this->codigo_inep_escola_compartilhada6)) {
                $set .= "{$gruda}codigo_inep_escola_compartilhada6 = '{$this->codigo_inep_escola_compartilhada6}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}codigo_inep_escola_compartilhada6 = NULL ";
                $gruda = ', ';
            }

            if (is_string($this->decreto_criacao)) {
                $set .= "{$gruda}decreto_criacao = '{$this->decreto_criacao}'";
                $gruda = ', ';
            }

            if (is_numeric($this->agua_potavel_consumo)) {
                $set .= "{$gruda}agua_potavel_consumo = '{$this->agua_potavel_consumo}'";
                $gruda = ', ';
            } elseif ($this->agua_potavel_consumo !== false) {
                $set .= "{$gruda}agua_potavel_consumo = NULL";
                $gruda = ', ';
            }

            if (is_string($this->abastecimento_agua)) {
                $set .= "{$gruda}abastecimento_agua = '{{$this->abastecimento_agua}}'";
                $gruda = ', ';
            } elseif ($this->abastecimento_agua !== false) {
                $set .= "{$gruda}abastecimento_agua = NULL";
                $gruda = ', ';
            }

            if (is_string($this->abastecimento_energia)) {
                $set .= "{$gruda}abastecimento_energia = '{{$this->abastecimento_energia}}'";
                $gruda = ', ';
            } elseif ($this->abastecimento_energia !== false) {
                $set .= "{$gruda}abastecimento_energia = NULL";
                $gruda = ', ';
            }

            if (is_string($this->esgoto_sanitario)) {
                $set .= "{$gruda}esgoto_sanitario = '{{$this->esgoto_sanitario}}'";
                $gruda = ', ';
            } elseif ($this->esgoto_sanitario !== false) {
                $set .= "{$gruda}esgoto_sanitario = NULL";
                $gruda = ', ';
            }

            if (is_string($this->destinacao_lixo)) {
                $set .= "{$gruda}destinacao_lixo = '{{$this->destinacao_lixo}}'";
                $gruda = ', ';
            } elseif ($this->destinacao_lixo !== false) {
                $set .= "{$gruda}destinacao_lixo = NULL";
                $gruda = ', ';
            }

            if (is_string($this->tratamento_lixo)) {
                $set .= "{$gruda}tratamento_lixo = '{{$this->tratamento_lixo}}'";
                $gruda = ', ';
            } elseif ($this->tratamento_lixo !== false) {
                $set .= "{$gruda}tratamento_lixo = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->alimentacao_escolar_alunos)) {
                $set .= "{$gruda}alimentacao_escolar_alunos = {$this->alimentacao_escolar_alunos}";
                $gruda = ', ';
            } elseif ($this->alimentacao_escolar_alunos !== false) {
                $set .= "{$gruda}alimentacao_escolar_alunos = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->compartilha_espacos_atividades_integracao)) {
                $set .= "{$gruda}compartilha_espacos_atividades_integracao = {$this->compartilha_espacos_atividades_integracao}";
                $gruda = ', ';
            } elseif ($this->compartilha_espacos_atividades_integracao !== false) {
                $set .= "{$gruda}compartilha_espacos_atividades_integracao = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->usa_espacos_equipamentos_atividades_regulares)) {
                $set .= "{$gruda}usa_espacos_equipamentos_atividades_regulares = {$this->usa_espacos_equipamentos_atividades_regulares}";
                $gruda = ', ';
            } elseif ($this->usa_espacos_equipamentos_atividades_regulares !== false) {
                $set .= "{$gruda}usa_espacos_equipamentos_atividades_regulares = NULL";
                $gruda = ', ';
            }

            if (is_string($this->salas_funcionais)) {
                $set .= "{$gruda}salas_funcionais = '{{$this->salas_funcionais}}'";
                $gruda = ', ';
            } elseif ($this->salas_funcionais !== false) {
                $set .= "{$gruda}salas_funcionais = NULL";
                $gruda = ', ';
            }

            if (is_string($this->salas_gerais)) {
                $set .= "{$gruda}salas_gerais = '{{$this->salas_gerais}}'";
                $gruda = ', ';
            } elseif ($this->salas_gerais !== false) {
                $set .= "{$gruda}salas_gerais = NULL";
                $gruda = ', ';
            }

            if (is_string($this->banheiros)) {
                $set .= "{$gruda}banheiros = '{{$this->banheiros}}'";
                $gruda = ', ';
            } elseif ($this->banheiros !== false) {
                $set .= "{$gruda}banheiros = NULL";
                $gruda = ', ';
            }

            if (is_string($this->laboratorios)) {
                $set .= "{$gruda}laboratorios = '{{$this->laboratorios}}'";
                $gruda = ', ';
            } elseif ($this->laboratorios !== false) {
                $set .= "{$gruda}laboratorios = NULL";
                $gruda = ', ';
            }

            if (is_string($this->salas_atividades)) {
                $set .= "{$gruda}salas_atividades = '{{$this->salas_atividades}}'";
                $gruda = ', ';
            } elseif ($this->salas_atividades !== false) {
                $set .= "{$gruda}salas_atividades = NULL";
                $gruda = ', ';
            }

            if (is_string($this->dormitorios)) {
                $set .= "{$gruda}dormitorios = '{{$this->dormitorios}}'";
                $gruda = ', ';
            } elseif ($this->dormitorios !== false) {
                $set .= "{$gruda}dormitorios = NULL";
                $gruda = ', ';
            }

            if (is_string($this->areas_externas)) {
                $set .= "{$gruda}areas_externas = '{{$this->areas_externas}}'";
                $gruda = ', ';
            } elseif ($this->areas_externas !== false) {
                $set .= "{$gruda}areas_externas = NULL";
                $gruda = ', ';
            }

            if (is_string($this->recursos_acessibilidade)) {
                $set .= "{$gruda}recursos_acessibilidade = '{{$this->recursos_acessibilidade}}'";
                $gruda = ', ';
            } elseif ($this->recursos_acessibilidade !== false) {
                $set .= "{$gruda}recursos_acessibilidade = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->possui_dependencias)) {
                $set .= "{$gruda}possui_dependencias = '{$this->possui_dependencias}'";
                $gruda = ', ';
            } elseif ($this->possui_dependencias !== false) {
                $set .= "{$gruda}possui_dependencias = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->numero_salas_utilizadas_dentro_predio)) {
                $set .= "{$gruda}numero_salas_utilizadas_dentro_predio = {$this->numero_salas_utilizadas_dentro_predio}";
                $gruda = ', ';
            } elseif ($this->numero_salas_utilizadas_dentro_predio !== false) {
                $set .= "{$gruda}numero_salas_utilizadas_dentro_predio = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->numero_salas_utilizadas_fora_predio)) {
                $set .= "{$gruda}numero_salas_utilizadas_fora_predio = {$this->numero_salas_utilizadas_fora_predio}";
                $gruda = ', ';
            } elseif ($this->numero_salas_utilizadas_fora_predio !== false) {
                $set .= "{$gruda}numero_salas_utilizadas_fora_predio = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->numero_salas_climatizadas)) {
                $set .= "{$gruda}numero_salas_climatizadas = {$this->numero_salas_climatizadas}";
                $gruda = ', ';
            } elseif ($this->numero_salas_climatizadas !== false) {
                $set .= "{$gruda}numero_salas_climatizadas = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->numero_salas_acessibilidade)) {
                $set .= "{$gruda}numero_salas_acessibilidade = {$this->numero_salas_acessibilidade}";
                $gruda = ', ';
            } elseif ($this->numero_salas_acessibilidade !== false) {
                $set .= "{$gruda}numero_salas_acessibilidade = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->total_funcionario)) {
                $set .= "{$gruda}total_funcionario = '{$this->total_funcionario}'";
                $gruda = ', ';
            }

            if (is_numeric($this->atendimento_aee)) {
                $set .= "{$gruda}atendimento_aee = '{$this->atendimento_aee}'";
            } else {
                $set .= "{$gruda}atendimento_aee = NULL ";
            }

            $gruda = ', ';
            if (is_numeric($this->atividade_complementar)) {
                $set .= "{$gruda}atividade_complementar = '{$this->atividade_complementar}'";
                $gruda = ', ';
            }

            if (is_numeric($this->fundamental_ciclo)) {
                $set .= "{$gruda}fundamental_ciclo = '{$this->fundamental_ciclo}'";
            } else {
                $set .= "{$gruda}fundamental_ciclo = NULL ";
            }

            $gruda = ', ';
            if (is_string($this->organizacao_ensino)) {
                $set .= "{$gruda}organizacao_ensino = '{{$this->organizacao_ensino}}'";
                $gruda = ', ';
            } elseif ($this->organizacao_ensino !== false) {
                $set .= "{$gruda}organizacao_ensino = NULL";
                $gruda = ', ';
            }

            if (is_string($this->instrumentos_pedagogicos)) {
                $set .= "{$gruda}instrumentos_pedagogicos = '{{$this->instrumentos_pedagogicos}}'";
                $gruda = ', ';
            } elseif ($this->instrumentos_pedagogicos !== false) {
                $set .= "{$gruda}instrumentos_pedagogicos = NULL";
                $gruda = ', ';
            }

            if (is_string($this->orgaos_colegiados)) {
                $set .= "{$gruda}orgaos_colegiados = '{{$this->orgaos_colegiados}}'";
                $gruda = ', ';
            } elseif ($this->orgaos_colegiados !== false) {
                $set .= "{$gruda}orgaos_colegiados = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->exame_selecao_ingresso)) {
                $set .= "{$gruda}exame_selecao_ingresso = '{$this->exame_selecao_ingresso}'";
                $gruda = ', ';
            } elseif ($this->exame_selecao_ingresso !== false) {
                $set .= "{$gruda}exame_selecao_ingresso = NULL";
                $gruda = ', ';
            }

            if (is_string($this->reserva_vagas_cotas)) {
                $set .= "{$gruda}reserva_vagas_cotas = '{{$this->reserva_vagas_cotas}}'";
                $gruda = ', ';
            } elseif ($this->reserva_vagas_cotas !== false) {
                $set .= "{$gruda}reserva_vagas_cotas = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->acao_area_ambiental)) {
                $set .= "{$gruda}acao_area_ambiental = '{$this->acao_area_ambiental}'";
                $gruda = ', ';
            } elseif ($this->acao_area_ambiental !== false) {
                $set .= "{$gruda}acao_area_ambiental = NULL";
                $gruda = ', ';
            }

            if (is_string($this->acoes_area_ambiental)) {
                $set .= "{$gruda}acoes_area_ambiental = '{{$this->acoes_area_ambiental}}'";
                $gruda = ', ';
            } elseif ($this->acoes_area_ambiental !== false) {
                $set .= "{$gruda}acoes_area_ambiental = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->projeto_politico_pedagogico)) {
                $set .= "{$gruda}projeto_politico_pedagogico = '{$this->projeto_politico_pedagogico}'";
                $gruda = ', ';
            } elseif ($this->projeto_politico_pedagogico !== false) {
                $set .= "{$gruda}projeto_politico_pedagogico = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->localizacao_diferenciada)) {
                $set .= "{$gruda}localizacao_diferenciada = '{$this->localizacao_diferenciada}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}localizacao_diferenciada = null";
                $gruda = ', ';
            }

            if (is_numeric($this->materiais_didaticos_especificos)) {
                $set .= "{$gruda}materiais_didaticos_especificos = '{$this->materiais_didaticos_especificos}'";
                $gruda = ', ';
            }

            if (is_numeric($this->educacao_indigena)) {
                $set .= "{$gruda}educacao_indigena = '{$this->educacao_indigena}'";
            } else {
                $set .= "{$gruda}educacao_indigena = NULL ";
            }

            $gruda = ', ';
            if (is_numeric($this->lingua_ministrada)) {
                $set .= "{$gruda}lingua_ministrada = '{$this->lingua_ministrada}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}lingua_ministrada = NULL";
                $gruda = ', ';
            }

            if (is_string($this->codigo_lingua_indigena)) {
                $set .= "{$gruda}codigo_lingua_indigena = '{{$this->codigo_lingua_indigena}}'";
                $gruda = ', ';
            } elseif ($this->codigo_lingua_indigena !== false) {
                $set .= "{$gruda}codigo_lingua_indigena = NULL";
                $gruda = ', ';
            }

            if (is_string($this->equipamentos)) {
                $set .= "{$gruda}equipamentos = '{{$this->equipamentos}}'";
                $gruda = ', ';
            } elseif ($this->equipamentos !== false) {
                $set .= "{$gruda}equipamentos = NULL";
                $gruda = ', ';
            }

            if (is_string($this->uso_internet)) {
                $set .= "{$gruda}uso_internet = '{{$this->uso_internet}}'";
                $gruda = ', ';
            } elseif ($this->uso_internet !== false) {
                $set .= "{$gruda}uso_internet = NULL";
                $gruda = ', ';
            }

            if (is_string($this->rede_local)) {
                $set .= "{$gruda}rede_local = '{{$this->rede_local}}'";
                $gruda = ', ';
            } elseif ($this->rede_local !== false) {
                $set .= "{$gruda}rede_local = NULL";
                $gruda = ', ';
            }

            if (is_string($this->equipamentos_acesso_internet)) {
                $set .= "{$gruda}equipamentos_acesso_internet = '{{$this->equipamentos_acesso_internet}}'";
                $gruda = ', ';
            } elseif ($this->equipamentos_acesso_internet !== false) {
                $set .= "{$gruda}equipamentos_acesso_internet = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->quantidade_computadores_alunos_mesa)) {
                $set .= "{$gruda}quantidade_computadores_alunos_mesa = {$this->quantidade_computadores_alunos_mesa}";
                $gruda = ', ';
            } elseif ($this->quantidade_computadores_alunos_mesa !== false) {
                $set .= "{$gruda}quantidade_computadores_alunos_mesa = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->quantidade_computadores_alunos_portateis)) {
                $set .= "{$gruda}quantidade_computadores_alunos_portateis = {$this->quantidade_computadores_alunos_portateis}";
                $gruda = ', ';
            } elseif ($this->quantidade_computadores_alunos_portateis !== false) {
                $set .= "{$gruda}quantidade_computadores_alunos_portateis = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->quantidade_computadores_alunos_tablets)) {
                $set .= "{$gruda}quantidade_computadores_alunos_tablets = {$this->quantidade_computadores_alunos_tablets}";
                $gruda = ', ';
            } elseif ($this->quantidade_computadores_alunos_tablets !== false) {
                $set .= "{$gruda}quantidade_computadores_alunos_tablets = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->lousas_digitais)) {
                $set .= "{$gruda}lousas_digitais = {$this->lousas_digitais}";
                $gruda = ', ';
            } elseif ($this->lousas_digitais !== false) {
                $set .= "{$gruda}lousas_digitais = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->televisoes)) {
                $set .= "{$gruda}televisoes = {$this->televisoes}";
                $gruda = ', ';
            } elseif ($this->televisoes !== false) {
                $set .= "{$gruda}televisoes = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->videocassetes)) {
                $set .= "{$gruda}videocassetes = '{$this->videocassetes}'";
                $gruda = ', ';
            }

            if (is_numeric($this->dvds)) {
                $set .= "{$gruda}dvds = {$this->dvds}";
                $gruda = ', ';
            } elseif ($this->dvds !== false) {
                $set .= "{$gruda}dvds = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->antenas_parabolicas)) {
                $set .= "{$gruda}antenas_parabolicas = '{$this->antenas_parabolicas}'";
                $gruda = ', ';
            }

            if (is_numeric($this->copiadoras)) {
                $set .= "{$gruda}copiadoras = '{$this->copiadoras}'";
                $gruda = ', ';
            }

            if (is_numeric($this->retroprojetores)) {
                $set .= "{$gruda}retroprojetores = '{$this->retroprojetores}'";
                $gruda = ', ';
            }

            if (is_numeric($this->impressoras)) {
                $set .= "{$gruda}impressoras = '{$this->impressoras}'";
                $gruda = ', ';
            }

            if (is_numeric($this->aparelhos_de_som)) {
                $set .= "{$gruda}aparelhos_de_som = {$this->aparelhos_de_som}";
                $gruda = ', ';
            } elseif ($this->aparelhos_de_som !== false) {
                $set .= "{$gruda}aparelhos_de_som = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->projetores_digitais)) {
                $set .= "{$gruda}projetores_digitais = {$this->projetores_digitais}";
                $gruda = ', ';
            } elseif ($this->projetores_digitais !== false) {
                $set .= "{$gruda}projetores_digitais = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->faxs)) {
                $set .= "{$gruda}faxs = '{$this->faxs}'";
                $gruda = ', ';
            }

            if (is_numeric($this->maquinas_fotograficas)) {
                $set .= "{$gruda}maquinas_fotograficas = '{$this->maquinas_fotograficas}'";
                $gruda = ', ';
            }

            if (is_numeric($this->computadores)) {
                $set .= "{$gruda}computadores = '{$this->computadores}'";
                $gruda = ', ';
            }

            if (is_numeric($this->computadores_administrativo)) {
                $set .= "{$gruda}computadores_administrativo = '{$this->computadores_administrativo}'";
                $gruda = ', ';
            }

            if (is_numeric($this->computadores_alunos)) {
                $set .= "{$gruda}computadores_alunos = '{$this->computadores_alunos}'";
                $gruda = ', ';
            }

            if (is_numeric($this->impressoras_multifuncionais)) {
                $set .= "{$gruda}impressoras_multifuncionais = '{$this->impressoras_multifuncionais}'";
                $gruda = ', ';
            }

            if (is_numeric($this->acesso_internet)) {
                $set .= "{$gruda}acesso_internet = '{$this->acesso_internet}'";
            } else {
                $set .= "{$gruda}acesso_internet = NULL ";
            }

            $gruda = ', ';
            if (is_string($this->ato_criacao)) {
                $set .= "{$gruda}ato_criacao = '{$this->ato_criacao}'";
                $gruda = ', ';
            }

            if (is_string($this->ato_autorizativo)) {
                $set .= "{$gruda}ato_autorizativo = '{$this->ato_autorizativo}'";
                $gruda = ', ';
            }

            if (is_numeric($this->ref_idpes_secretario_escolar)) {
                $set .= "{$gruda}ref_idpes_secretario_escolar = '{$this->ref_idpes_secretario_escolar}'";
                $gruda = ', ';
            } elseif (is_null($this->ref_idpes_secretario_escolar) || $this->ref_idpes_secretario_escolar == '') {
                $set .= "{$gruda}ref_idpes_secretario_escolar = NULL";
                $gruda = ', ';
            }

            if (is_numeric($this->categoria_escola_privada)) {
                $set .= "{$gruda}categoria_escola_privada = '{$this->categoria_escola_privada}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}categoria_escola_privada = NULL ";
                $gruda = ', ';
            }

            if (is_numeric($this->conveniada_com_poder_publico)) {
                $set .= "{$gruda}conveniada_com_poder_publico = '{$this->conveniada_com_poder_publico}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}conveniada_com_poder_publico = NULL ";
                $gruda = ', ';
            }

            if (is_string($this->mantenedora_escola_privada) && $this->mantenedora_escola_privada != '{}') {
                $set .= "{$gruda}mantenedora_escola_privada = '{" . $this->mantenedora_escola_privada . '}\'';
                $gruda = ', ';
            } else {
                $set .= "{$gruda}mantenedora_escola_privada = NULL ";
                $gruda = ', ';
            }

            if (is_numeric($this->cnpj_mantenedora_principal)) {
                $set .= "{$gruda}cnpj_mantenedora_principal = '{$this->cnpj_mantenedora_principal}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}cnpj_mantenedora_principal = NULL ";
                $gruda = ', ';
            }

            if (is_numeric($this->esfera_administrativa)) {
                $gruda = ', ';
                $set .= "{$gruda}esfera_administrativa = '{$this->esfera_administrativa}'";
            } elseif (is_null($this->esfera_administrativa) || $this->esfera_administrativa == '') {
                $gruda = ', ';
                $set .= "{$gruda}esfera_administrativa = NULL ";
            }

            if (is_numeric($this->qtd_secretario_escolar) && $this->qtd_secretario_escolar > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_secretario_escolar = '{$this->qtd_secretario_escolar}'";
            } elseif (is_null($this->qtd_secretario_escolar) || $this->qtd_secretario_escolar == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_secretario_escolar = NULL ";
            }

            if (is_numeric($this->qtd_auxiliar_administrativo) && $this->qtd_auxiliar_administrativo > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_auxiliar_administrativo = '{$this->qtd_auxiliar_administrativo}'";
            } elseif (is_null($this->qtd_auxiliar_administrativo) || $this->qtd_auxiliar_administrativo == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_auxiliar_administrativo = NULL ";
            }

            if (is_numeric($this->qtd_apoio_pedagogico) && $this->qtd_apoio_pedagogico > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_apoio_pedagogico = '{$this->qtd_apoio_pedagogico}'";
            } elseif (is_null($this->qtd_apoio_pedagogico) || $this->qtd_apoio_pedagogico == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_apoio_pedagogico = NULL ";
            }

            if (is_numeric($this->qtd_coordenador_turno) && $this->qtd_coordenador_turno > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_coordenador_turno = '{$this->qtd_coordenador_turno}'";
            } elseif (is_null($this->qtd_coordenador_turno) || $this->qtd_coordenador_turno == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_coordenador_turno = NULL ";
            }

            if (is_numeric($this->qtd_tecnicos) && $this->qtd_tecnicos > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_tecnicos = '{$this->qtd_tecnicos}'";
            } elseif (is_null($this->qtd_tecnicos) || $this->qtd_tecnicos == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_tecnicos = NULL ";
            }

            if (is_numeric($this->qtd_bibliotecarios) && $this->qtd_bibliotecarios > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_bibliotecarios = '{$this->qtd_bibliotecarios}'";
            } elseif (is_null($this->qtd_bibliotecarios) || $this->qtd_bibliotecarios == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_bibliotecarios = NULL ";
            }

            if (is_numeric($this->qtd_segurancas) && $this->qtd_segurancas > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_segurancas = '{$this->qtd_segurancas}'";
            } elseif (is_null($this->qtd_segurancas) || $this->qtd_segurancas == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_segurancas = NULL ";
            }

            if (is_numeric($this->qtd_auxiliar_servicos_gerais) && $this->qtd_auxiliar_servicos_gerais > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_auxiliar_servicos_gerais = '{$this->qtd_auxiliar_servicos_gerais}'";
            } elseif (is_null($this->qtd_auxiliar_servicos_gerais) || $this->qtd_auxiliar_servicos_gerais == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_auxiliar_servicos_gerais = NULL ";
            }

            if (is_numeric($this->qtd_agronomos_horticultores) && $this->qtd_agronomos_horticultores > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_agronomos_horticultores = '{$this->qtd_agronomos_horticultores}'";
            } elseif (is_null($this->qtd_agronomos_horticultores) || $this->qtd_agronomos_horticultores == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_agronomos_horticultores = NULL ";
            }

            if (is_numeric($this->qtd_nutricionistas) && $this->qtd_nutricionistas > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_nutricionistas = '{$this->qtd_nutricionistas}'";
            } elseif (is_null($this->qtd_nutricionistas) || $this->qtd_nutricionistas == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_nutricionistas = NULL ";
            }

            if (is_numeric($this->qtd_profissionais_preparacao) && $this->qtd_profissionais_preparacao > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_profissionais_preparacao = '{$this->qtd_profissionais_preparacao}'";
            } elseif (is_null($this->qtd_profissionais_preparacao) || $this->qtd_profissionais_preparacao == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_profissionais_preparacao = NULL ";
            }

            if (is_numeric($this->qtd_bombeiro) && $this->qtd_bombeiro > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_bombeiro = '{$this->qtd_bombeiro}'";
            } elseif (is_null($this->qtd_bombeiro) || $this->qtd_bombeiro == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_bombeiro = NULL ";
            }

            if (is_numeric($this->qtd_psicologo) && $this->qtd_psicologo > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_psicologo = '{$this->qtd_psicologo}'";
            } elseif (is_null($this->qtd_psicologo) || $this->qtd_psicologo == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_psicologo = NULL ";
            }

            if (is_numeric($this->qtd_fonoaudiologo) && $this->qtd_fonoaudiologo > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_fonoaudiologo = '{$this->qtd_fonoaudiologo}'";
            } elseif (is_null($this->qtd_fonoaudiologo) || $this->qtd_fonoaudiologo == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_fonoaudiologo = NULL ";
            }

            if (is_numeric($this->qtd_vice_diretor) && $this->qtd_vice_diretor > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_vice_diretor = '{$this->qtd_vice_diretor}'";
            } elseif (is_null($this->qtd_vice_diretor) || $this->qtd_vice_diretor == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_vice_diretor = NULL ";
            }

            if (is_numeric($this->qtd_orientador_comunitario) && $this->qtd_orientador_comunitario > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_orientador_comunitario = '{$this->qtd_orientador_comunitario}'";
            } elseif (is_null($this->qtd_orientador_comunitario) || $this->qtd_orientador_comunitario == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_orientador_comunitario = NULL ";
            }

            if (is_numeric($this->qtd_tradutor_interprete_libras_outro_ambiente) && $this->qtd_tradutor_interprete_libras_outro_ambiente > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_tradutor_interprete_libras_outro_ambiente = '{$this->qtd_tradutor_interprete_libras_outro_ambiente}'";
            } elseif (is_null($this->qtd_tradutor_interprete_libras_outro_ambiente) || $this->qtd_tradutor_interprete_libras_outro_ambiente == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_tradutor_interprete_libras_outro_ambiente = NULL ";
            }

            if (is_numeric($this->qtd_revisor_braile) && $this->qtd_revisor_braile > 0) {
                $gruda = ', ';
                $set .= "{$gruda}qtd_revisor_braile = '{$this->qtd_revisor_braile}'";
            } elseif (is_null($this->qtd_revisor_braile) || $this->qtd_revisor_braile == '') {
                $gruda = ', ';
                $set .= "{$gruda}qtd_revisor_braile = NULL ";
            }

            if (is_int($this->iddis) && $this->iddis > 0) {
                $gruda = ', ';
                $set .= "{$gruda}iddis = '{$this->iddis}'";
            } elseif (is_null($this->iddis) || $this->iddis == '') {
                $gruda = ', ';
                $set .= "{$gruda}iddis = NULL ";
            }

            if (is_string($this->poder_publico_parceria_convenio)) {
                $gruda .= 'poder_publico_parceria_convenio';
                $set .= "{$gruda}='{{$this->poder_publico_parceria_convenio}}'";
            } else {
                $gruda .= 'poder_publico_parceria_convenio';
                $set .= "{$gruda}=NULL";
            }

            if (is_string($this->formas_contratacao_parceria_escola_secretaria_estadual)) {
                $gruda = ', ';
                $gruda .= 'formas_contratacao_parceria_escola_secretaria_estadual';
                $set .= "{$gruda}='{{$this->formas_contratacao_parceria_escola_secretaria_estadual}}'";
            } else {
                $gruda = ', ';
                $gruda .= 'formas_contratacao_parceria_escola_secretaria_estadual';
                $set .= "{$gruda}=NULL";
            }

            if (is_string($this->formas_contratacao_parceria_escola_secretaria_municipal)) {
                $gruda = ', ';
                $gruda .= 'formas_contratacao_parceria_escola_secretaria_municipal';
                $set .= "{$gruda}='{{$this->formas_contratacao_parceria_escola_secretaria_municipal}}'";
            } else {
                $gruda = ', ';
                $gruda .= 'formas_contratacao_parceria_escola_secretaria_municipal';
                $set .= "{$gruda}=NULL";
            }

            $gruda = ', ';
            if (is_numeric($this->qtd_matriculas_atividade_complementar)) {
                $set .= "{$gruda}qtd_matriculas_atividade_complementar = '{$this->qtd_matriculas_atividade_complementar}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_matriculas_atividade_complementar = NULL";
            }

            if (is_numeric($this->qtd_atendimento_educacional_especializado)) {
                $set .= "{$gruda}qtd_atendimento_educacional_especializado = '{$this->qtd_atendimento_educacional_especializado}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_atendimento_educacional_especializado = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_creche_par)) {
                $set .= "{$gruda}qtd_ensino_regular_creche_par = '{$this->qtd_ensino_regular_creche_par}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_creche_par = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_creche_int)) {
                $set .= "{$gruda}qtd_ensino_regular_creche_int = '{$this->qtd_ensino_regular_creche_int}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_creche_int = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_pre_escola_par)) {
                $set .= "{$gruda}qtd_ensino_regular_pre_escola_par = '{$this->qtd_ensino_regular_pre_escola_par}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_pre_escola_par = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_pre_escola_int)) {
                $set .= "{$gruda}qtd_ensino_regular_pre_escola_int = '{$this->qtd_ensino_regular_pre_escola_int}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_pre_escola_int = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_fund_anos_iniciais_par)) {
                $set .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_iniciais_par = '{$this->qtd_ensino_regular_ensino_fund_anos_iniciais_par}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_iniciais_par = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_fund_anos_iniciais_int)) {
                $set .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_iniciais_int = '{$this->qtd_ensino_regular_ensino_fund_anos_iniciais_int}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_iniciais_int = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_fund_anos_finais_par)) {
                $set .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_finais_par = '{$this->qtd_ensino_regular_ensino_fund_anos_finais_par}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_finais_par = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_fund_anos_finais_int)) {
                $set .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_finais_int = '{$this->qtd_ensino_regular_ensino_fund_anos_finais_int}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_ensino_fund_anos_finais_int = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_med_anos_iniciais_par)) {
                $set .= "{$gruda}qtd_ensino_regular_ensino_med_anos_iniciais_par = '{$this->qtd_ensino_regular_ensino_med_anos_iniciais_par}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_ensino_med_anos_iniciais_par = NULL";
            }

            if (is_numeric($this->qtd_ensino_regular_ensino_med_anos_iniciais_int)) {
                $set .= "{$gruda}qtd_ensino_regular_ensino_med_anos_iniciais_int = '{$this->qtd_ensino_regular_ensino_med_anos_iniciais_int}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_ensino_regular_ensino_med_anos_iniciais_int = NULL";
            }

            if (is_numeric($this->qtd_edu_especial_classe_especial_par)) {
                $set .= "{$gruda}qtd_edu_especial_classe_especial_par = '{$this->qtd_edu_especial_classe_especial_par}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_edu_especial_classe_especial_par = NULL";
            }

            if (is_numeric($this->qtd_edu_especial_classe_especial_int)) {
                $set .= "{$gruda}qtd_edu_especial_classe_especial_int = '{$this->qtd_edu_especial_classe_especial_int}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_edu_especial_classe_especial_int = NULL";
            }

            if (is_numeric($this->qtd_edu_eja_ensino_fund)) {
                $set .= "{$gruda}qtd_edu_eja_ensino_fund = '{$this->qtd_edu_eja_ensino_fund}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_edu_eja_ensino_fund = NULL";
            }

            if (is_numeric($this->qtd_edu_eja_ensino_med)) {
                $set .= "{$gruda}qtd_edu_eja_ensino_med = '{$this->qtd_edu_eja_ensino_med}'";
                $gruda = ', ';
            } else {
                $set .= "{$gruda}qtd_edu_eja_ensino_med = NULL";
            }

            if ($set) {
                $db->Consulta("UPDATE {$this->_tabela} SET $set WHERE cod_escola = '{$this->cod_escola}'");

                return true;
            }
        }

        return false;
    }

    /**
     * Retorna uma lista de registros filtrados de acordo com os parâmetros.
     *
     * @return array|false
     */
    public function lista(
        $int_cod_escola = null,
        $int_ref_usuario_cad = null,
        $int_ref_usuario_exc = null,
        $int_ref_cod_instituicao = null,
        $zona_localizacao = null,
        $int_ref_idpes = null,
        $str_sigla = null,
        $date_data_cadastro = null,
        $date_data_exclusao = null,
        $int_ativo = null,
        $str_nome = null,
        $escola_sem_avaliacao = null,
        $cod_usuario = null
    ) {
        $db = new clsBanco();

        $sql = "
          SELECT * FROM
          (
            SELECT j.fantasia AS nome, {$this->_campos_lista}, 1 AS tipo_cadastro
              FROM {$this->_tabela} e, cadastro.juridica j
              WHERE e.ref_idpes = j.idpes
            UNION
            SELECT c.nm_escola AS nome, {$this->_campos_lista}, 2 AS tipo_cadastro
              FROM {$this->_tabela} e, pmieducar.escola_complemento c
              WHERE e.cod_escola = c.ref_cod_escola
          ) AS sub";
        $filtros = '';

        $whereAnd = ' WHERE ';

        if (is_numeric($int_cod_escola)) {
            $filtros .= "{$whereAnd} cod_escola = '{$int_cod_escola}'";
            $whereAnd = ' AND ';
        } elseif ($this->codUsuario) {
            $filtros .= "{$whereAnd} EXISTS (SELECT 1
                                         FROM pmieducar.escola_usuario
                                        WHERE escola_usuario.ref_cod_escola = cod_escola
                                          AND escola_usuario.ref_cod_usuario = '{$this->codUsuario}')";
            $whereAnd = ' AND ';
        }

        if (is_numeric($int_ref_usuario_cad)) {
            $filtros .= "{$whereAnd} ref_usuario_cad = '{$int_ref_usuario_cad}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($int_ref_usuario_exc)) {
            $filtros .= "{$whereAnd} ref_usuario_exc = '{$int_ref_usuario_exc}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($int_ref_cod_instituicao)) {
            $filtros .= "{$whereAnd} ref_cod_instituicao = '{$int_ref_cod_instituicao}'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($zona_localizacao)) {
            $filtros .= "{$whereAnd} zona_localizacao = {$zona_localizacao}";
            $whereAnd = ' AND ';
        }

        if (is_numeric($int_ref_idpes)) {
            $filtros .= "{$whereAnd} ref_idpes = '{$int_ref_idpes}'";
            $whereAnd = ' AND ';
        }

        if (is_string($str_sigla)) {
            $filtros .= "{$whereAnd} sigla LIKE '%{$str_sigla}%'";
            $whereAnd = ' AND ';
        }

        if (is_numeric($int_ativo)) {
            $filtros .= "{$whereAnd} ativo = '{$int_ativo}'";
            $whereAnd = ' AND ';
        } else {
            $filtros .= "{$whereAnd} ativo = 1";
            $whereAnd = ' AND ';
        }

        if (is_string($str_nome)) {
            $str_nm = $db->escapeString($str_nome);
            $filtros .= "{$whereAnd} unaccent(nome) ILIKE unaccent('%{$str_nm}%')";
            $whereAnd = ' AND ';
        }

        if (is_bool($escola_sem_avaliacao)) {
            if (dbBool($escola_sem_avaliacao)) {
                $filtros .= "{$whereAnd} NOT EXISTS (SELECT 1 FROM pmieducar.escola_curso ec, pmieducar.curso c WHERE
                        ec.ref_cod_escola = cod_escola
                        AND ec.ref_cod_curso = c.cod_curso
                        AND ec.ativo = 1 AND c.ativo = 1)";
            } else {
                $filtros .= "{$whereAnd} EXISTS (SELECT 1 FROM pmieducar.escola_curso ec, pmieducar.curso c WHERE
                        ec.ref_cod_escola = cod_escola
                        AND ec.ref_cod_curso = c.cod_curso
                        AND ec.ativo = 1 AND c.ativo = 1)";
            }
        }

        if (is_numeric($cod_usuario)) {
            $permissao = new clsPermissoes();
            $nivel = $permissao->nivel_acesso($_SESSION['id_pessoa']);

            if ($nivel == App_Model_NivelTipoUsuario::ESCOLA ||
                $nivel == App_Model_NivelTipoUsuario::BIBLIOTECA) {
                $filtros .= "{$whereAnd} EXISTS (SELECT *
                                           FROM pmieducar.escola_usuario
                                          WHERE escola_usuario.ref_cod_escola = cod_escola
                                            AND ref_cod_usuario = '{$cod_usuario}')";
                $whereAnd = ' AND ';
            }
        }

        $countCampos = count(explode(',', $this->_campos_lista));
        $resultado = [];
        $sql .= $filtros . $this->getOrderby() . $this->getLimite();

        $db->Consulta("
        SELECT COUNT(0) FROM
        (
          SELECT j.fantasia AS nome, {$this->_campos_lista}, 1 AS tipo_cadastro
          FROM {$this->_tabela} e, cadastro.juridica j
          WHERE e.ref_idpes = j.idpes
        UNION
          SELECT c.nm_escola AS nome, {$this->_campos_lista}, 2 AS tipo_cadastro
          FROM {$this->_tabela} e, pmieducar.escola_complemento c
          WHERE e.cod_escola = c.ref_cod_escola
        ) AS sub
        {$filtros}
    ");

        $db->ProximoRegistro();
        [$this->_total] = $db->Tupla();
        $db->Consulta($sql);

        if ($countCampos > 1) {
            while ($db->ProximoRegistro()) {
                $tupla = $db->Tupla();
                $resultado[] = $tupla;
            }
        } else {
            while ($db->ProximoRegistro()) {
                $tupla = $db->Tupla();
                $resultado[] = $tupla[$this->_campos_lista];
                $this->_total = count($tupla);
            }
        }

        if (count($resultado)) {
            return $resultado;
        }

        return false;
    }

    public function lista_escola()
    {
        $db = new clsBanco();
        $resultado = [];
        $db->Consulta('SELECT COALESCE((SELECT COALESCE (fcn_upper(ps.nome),fcn_upper(juridica.fantasia))
                                      FROM cadastro.pessoa ps, cadastro.juridica
                                     WHERE escola.ref_idpes = juridica.idpes
                                       AND juridica.idpes = ps.idpes
                                       AND ps.idpes = escola.ref_idpes),
                                   (SELECT nm_escola
                                      FROM pmieducar.escola_complemento
                                    WHERE ref_cod_escola = escola.cod_escola)) as nome, escola.cod_escola
                     FROM pmieducar.escola
                    WHERE ativo = 1
                    ORDER BY nome
                 ');

        while ($db->ProximoRegistro()) {
            $tupla = $db->Tupla();
            $resultado[] = $tupla;
        }
        if (count($resultado)) {
            return $resultado;
        }
    }

    public function possuiTurmasDoEnsinoFundamentalEmCiclos()
    {
        $anoAtual = date('Y');
        $sql = "SELECT EXISTS (SELECT 1
                             FROM pmieducar.turma
                            WHERE ref_ref_cod_escola = {$this->cod_escola}
                              AND etapa_educacenso IN (4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,41,56)
                              AND ano = {$anoAtual})";
        $db = new clsBanco();

        return $db->CampoUnico($sql);
    }

    /**
     * Retorna um array com os dados de um registro.
     *
     * @return array|false
     */
    public function detalhe()
    {
        if (is_numeric($this->cod_escola)) {
            $db = new clsBanco();
            $db->Consulta(
                "
        SELECT * FROM
        (
          SELECT c.nm_escola AS nome, {$this->_todos_campos}, 2 AS tipo_cadastro
          FROM {$this->_tabela} e, pmieducar.escola_complemento c
          WHERE e.cod_escola = c.ref_cod_escola

        UNION

          SELECT j.fantasia AS nome, {$this->_todos_campos}, 1 AS tipo_cadastro
          FROM {$this->_tabela} e, cadastro.juridica j
          WHERE e.ref_idpes = j.idpes


        ) AS sub WHERE cod_escola = '{$this->cod_escola}'"
            );
            $db->ProximoRegistro();

            return $db->Tupla();
        }

        return false;
    }

    /**
     * Retorna um array com os dados de um registro.
     *
     * @return array|false
     */
    public function existe()
    {
        if (is_numeric($this->cod_escola)) {
            $db = new clsBanco();
            $db->Consulta("SELECT 1 FROM {$this->_tabela} WHERE cod_escola = '{$this->cod_escola}'");
            $db->ProximoRegistro();

            return $db->Tupla();
        }

        return false;
    }

    /**
     * Exclui um registro.
     *
     * @return bool
     */
    public function excluir()
    {
        if (is_numeric($this->cod_escola)) {
            $this->ativo = 0;

            return $this->edita();
        }

        return false;
    }
}
