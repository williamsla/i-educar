addEmailEdit();
var $submitButton      = $j('#btn_enviar');
var $escolaInepIdField = $j('#escola_inep_id');
var $escolaIdField     = $j('#cod_escola');

const DEPENDENCIA_ADMINISTRATIVA = {
  FEDERAL: 1,
  ESTADUAL: 2,
  MUNICIPAL: 3,
  PRIVADA: 4
}

const SITUACAO_FUNCIONAMENTO = {
  EM_ATIVIDADE : 1,
  PARALISADA : 2,
  EXTINTA : 3
}

const UNIDADE_VINCULADA = {
  SEM_VINCULO : 0,
  EDUCACAO_BASICA : 1,
  ENSINO_SUPERIOR : 2
}

const MANTENEDORA_ESCOLA_PRIVADA = {
  GRUPOS_EMPRESARIAIS : 1,
  SINDICATOS_TRABALHISTAS : 2,
  ORGANIZACOES_NAO_GOVERNAMENTAIS : 3,
  INSTITUICOES_SIM_FINS_LUCRATIVOS : 4,
  SISTEMA_S : 5,
  OSCIP : 6
}

const SCHOOL_MANAGER_ROLE = {
    DIRETOR: 1,
}

const SCHOOL_MANAGER_ACCESS_CRITERIA = {
    OUTRO: 7,
}

const LOCAL_FUNCIONAMENTO = {
    PREDIO_ESCOLAR: 3
}

const USO_INTERNET = {
    NAO_POSSUI: 1,
    ALUNOS: 4
};

const EQUIPAMENTOS = {
    COMPUTADORES: 1
};

const EQUIPAMENTOS_ACESSO_INTERNET = {
  COMPUTADORES: '1'
};

const PODER_PUBLICO_PARCERIA_CONVENIO = {
  SECRETARIA_ESTADUAL: 1,
  SECRETARIA_MUNICIPAL: 2,
  NAO_POSSUI_PARCERIA_OU_CONVENIO: 3
};

function validaEspacoEscolares() {
  const espacos = $j('tr.tr_espacos');
  let validacaoPassa = true;

  espacos.each(function () {
    const nomeInput = $j(this).find('input[name^="espaco_escolar_nome"]');
    const tamanhoInput = $j(this).find('input[name^="espaco_escolar_tamanho"]');
    nomeInput.removeClass('error');
    tamanhoInput.removeClass('error');

    const nome = nomeInput.val().trim();
    const tamanho = tamanhoInput.val().trim();

    if (nome !== '' || tamanho !== '') {
      if (nome === '') {
        messageUtils.error('O campo: <b>Espaço Escolar</b> deve ser preenchido', nomeInput);
        nomeInput.addClass('error');
        validacaoPassa = false;
      }
      if (tamanho === '') {
        messageUtils.error('O campo: <b>Tamanho do espaço</b> deve ser preenchido', tamanhoInput);
        tamanhoInput.addClass('error');
        validacaoPassa = false;
      } else if(isNaN(tamanho)) {
        messageUtils.error('O campo: <b>Tamanho do espaço</b> deve conter um valor númerico', tamanhoInput);
        tamanhoInput.addClass('error');
        validacaoPassa = false;
      }
    }
  });

  return validacaoPassa;
}

var submitForm = function() {
  var canSubmit = validationUtils.validatesFields(true) && validaEspacoEscolares();

  // O campo escolaInepId somente é atualizado ao cadastrar escola,  uma vez que este
  // é atualizado via ajax, e durante o (novo) cadastro a escola ainda não possui id.
  //
  // #TODO refatorar cadastro de escola para que todos campos sejam enviados via ajax,
  // podendo então definir o código escolaInepId ao cadastrar a escola.

  if (canSubmit) {
    acao();
  }
}

function addEmailEdit() {
  let pessoaId = $j('#pessoaj_id').val();
  let url = '"' + '/intranet/empresas_cad.php?idpes=' + pessoaId + '#email ' + '"';
  let editEmail =
  '<span>' +
    '<a href=' + url + 'target="_blank" class="span-busca-cep" style="color: blue; margin-left: 10px;">Clique aqui para editar o e-mail</a>' +
  '</span>';

  $j('#tr_p_email td:last-child').append(editEmail)
}

var handleGetEscola = function(dataResponse) {
  handleMessages(dataResponse.msgs);

  $escolaInepIdField.val(dataResponse.escola_inep_id);
}

var getEscola = function(escolaId) {
  var data = {
    id : escolaId
  };

  var options = {
    url      : getResourceUrlBuilder.buildUrl('/module/Api/escola', 'escola'),
    dataType : 'json',
    data     : data,
    success  : handleGetEscola
  };

  getResource(options);
}

if ($escolaIdField.val()) {
  getEscola($escolaIdField.val());
}

// unbind events
$submitButton.removeAttr('onclick');
$j(document.formcadastro).removeAttr('onsubmit');

// bind events
$submitButton.click(submitForm);

let obrigarCamposCenso = $j('#obrigar_campos_censo').val() == '1';

window.addEventListener(
  'load', function () {
    obrigaCampoFormaDeContratacao();
    obrigaCampoFormaDeContratacaoEscolaSecretariaEstadual()
    obrigaCampoFormaDeContratacaoEscolaSecretariaMunicipal()
    habilitaCampoFormaDeContratacao();
    habilitaCampoFormaDeContratacaoEscolaSecretariaEstadual();
    habilitaCampoFormaDeContratacaoEscolaSecretariaMunicipal();
    habilitaAbaMatriculasAtendidas();
    obrigarCnpjMantenedora();
  },false
);

function obrigarCnpjMantenedora() {
  dependenciaPrivada = $j('#dependencia_administrativa').val() == DEPENDENCIA_ADMINISTRATIVA.PRIVADA;
  mantenedoraSemFinsLucrativos = $j.inArray(MANTENEDORA_ESCOLA_PRIVADA.INSTITUICOES_SIM_FINS_LUCRATIVOS.toString(), $j('#mantenedora_escola_privada').val()) != -1;
  escolaRegulamentada = $j('#regulamentacao').val() == 1;
  emAtividade = $j('#situacao_funcionamento').val() == SITUACAO_FUNCIONAMENTO.EM_ATIVIDADE;

  $j('#cnpj_mantenedora_principal').makeUnrequired();
  if (obrigarCamposCenso && dependenciaPrivada && mantenedoraSemFinsLucrativos && escolaRegulamentada && emAtividade) {
    $j('#cnpj_mantenedora_principal').makeRequired();
  }
}

$j('#local_funcionamento').on('change', function () {
    changeLocalFuncionamento();
    changeNumeroDeSalas();
});

$j('#nao_ha_funcionarios_para_funcoes').on('change', function () {
    habilitaRecuros()
});

$j('#predio_compartilhado_outra_escola').on('change', function () {
    changePredioCompartilhadoEscola()
});

$j('#poder_publico_parceria_convenio').on('change', function () {
  obrigaCampoFormaDeContratacao();
  obrigaCampoFormaDeContratacaoEscolaSecretariaEstadual();
  obrigaCampoFormaDeContratacaoEscolaSecretariaMunicipal();
  habilitaCampoFormaDeContratacao();
  habilitaCampoFormaDeContratacaoEscolaSecretariaEstadual();
  habilitaCampoFormaDeContratacaoEscolaSecretariaMunicipal();
});

function habilitaRecuros() {

  const camposDosRecuros = [
    $j('#qtd_secretario_escolar'),
    $j('#qtd_auxiliar_administrativo'),
    $j('#qtd_apoio_pedagogico'),
    $j('#qtd_coordenador_turno'),
    $j('#qtd_tecnicos'),
    $j('#qtd_bibliotecarios'),
    $j('#qtd_segurancas'),
    $j('#qtd_auxiliar_servicos_gerais'),
    $j('#qtd_agronomos_horticultores'),
    $j('#qtd_nutricionistas'),
    $j('#qtd_profissionais_preparacao'),
    $j('#qtd_bombeiro'),
    $j('#qtd_psicologo'),
    $j('#qtd_fonoaudiologo'),
    $j('#qtd_vice_diretor'),
    $j('#qtd_orientador_comunitario'),
    $j('#qtd_tradutor_interprete_libras_outro_ambiente'),
    $j('#qtd_revisor_braile'),
  ];

  const isChecked = $j('#nao_ha_funcionarios_para_funcoes').is(':checked');
  if (isChecked) {
    camposDosRecuros.forEach(function (campo) {
      campo.val('');
      campo.prop('disabled', true);
    });
    return;
  }

  camposDosRecuros.forEach(function (campo) {
    campo.prop('disabled', false);
  });
}

function obrigaCampoRegulamentacao() {
  escolaEmAtividade = $j('#situacao_funcionamento').val() == SITUACAO_FUNCIONAMENTO.EM_ATIVIDADE;

  if (escolaEmAtividade) {
    $j('#regulamentacao').makeRequired();
    $j("#regulamentacao").prop('disabled', false);
  } else {
    $j('#regulamentacao').makeUnrequired();
    $j("#regulamentacao").prop('disabled', true);
  }
}

function obrigaCampoFormaDeContratacaoEscolaSecretariaEstadual() {
  const secretariaEstadual = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.SECRETARIA_ESTADUAL.toString(), $j('#poder_publico_parceria_convenio').val()) != -1
  const naoPossueParceriaOuConvenio = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.NAO_POSSUI_PARCERIA_OU_CONVENIO.toString(), $j('#poder_publico_parceria_convenio').val()) != -1
  const obj = $j('#formas_contratacao_parceria_escola_secretaria_estadual');

  if (obrigarCamposCenso && secretariaEstadual) {
    obj.makeRequired();
    obj.prop('disabled', false);
  } else {
    obj.makeUnrequired();
    obj.prop('disabled', true);
  }

  if (naoPossueParceriaOuConvenio) {
    obj.makeUnrequired();
    obj.prop('disabled', true);
  }
}

function obrigaCampoFormaDeContratacaoEscolaSecretariaMunicipal() {
  const secretariaMunicipal = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.SECRETARIA_MUNICIPAL.toString(), $j('#poder_publico_parceria_convenio').val()) != -1
  const naoPossueParceriaOuConvenio = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.NAO_POSSUI_PARCERIA_OU_CONVENIO.toString(), $j('#poder_publico_parceria_convenio').val()) != -1
  const obj = $j('#formas_contratacao_parceria_escola_secretaria_municipal');

  if (obrigarCamposCenso && secretariaMunicipal) {
    obj.makeRequired();
    obj.prop('disabled', false);
  } else {
    obj.makeUnrequired();
    obj.prop('disabled', true);
  }

  if (naoPossueParceriaOuConvenio) {
    obj.makeUnrequired();
    obj.prop('disabled', true);
  }
}

function obrigaCampoFormaDeContratacao() {
  const secretariaEstadual = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.SECRETARIA_ESTADUAL.toString(), $j('#poder_publico_parceria_convenio').val()) != -1
  const secretariaMunicipal = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.SECRETARIA_MUNICIPAL.toString(), $j('#poder_publico_parceria_convenio').val()) != -1
  const naoPossueParceriaOuConvenio = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.NAO_POSSUI_PARCERIA_OU_CONVENIO.toString(), $j('#poder_publico_parceria_convenio').val()) != -1

  if (obrigarCamposCenso && (secretariaEstadual || secretariaMunicipal)) {
    $j('#formas_contratacao_adm_publica_e_outras_instituicoes').makeRequired();
    $j("#formas_contratacao_adm_publica_e_outras_instituicoes").prop('disabled', false);
  } else {
    $j('#formas_contratacao_adm_publica_e_outras_instituicoes').makeUnrequired();
    $j("#formas_contratacao_adm_publica_e_outras_instituicoes").prop('disabled', true);
  }

  if (naoPossueParceriaOuConvenio) {
    $j('#formas_contratacao_adm_publica_e_outras_instituicoes').makeUnrequired();
    $j("#formas_contratacao_adm_publica_e_outras_instituicoes").prop('disabled', true);
  }
}

function habilitaCampoFormaDeContratacaoEscolaSecretariaEstadual() {
  const poderPublico = $j('#poder_publico_parceria_convenio').val();
  const secretariaEstadual = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.SECRETARIA_ESTADUAL.toString(), $j('#poder_publico_parceria_convenio').val()) != -1

  if (!poderPublico) {
    $j("#formas_contratacao_parceria_escola_secretaria_estadual").prop('disabled', true);
    $j("#formas_contratacao_parceria_escola_secretaria_estadual").val('');
    $j("#formas_contratacao_parceria_escola_secretaria_estadual").trigger("chosen:updated");
    return;
  }

  if (!secretariaEstadual) {
    $j("#formas_contratacao_parceria_escola_secretaria_estadual").prop('disabled', true);
    $j("#formas_contratacao_parceria_escola_secretaria_estadual").val('');
    $j("#formas_contratacao_parceria_escola_secretaria_estadual").trigger("chosen:updated");
    return;
  }

  $j("#formas_contratacao_parceria_escola_secretaria_estadual").prop('disabled', false);
  $j("#formas_contratacao_parceria_escola_secretaria_estadual").trigger("chosen:updated");
}

function habilitaCampoFormaDeContratacaoEscolaSecretariaMunicipal() {
  const poderPublico = $j('#poder_publico_parceria_convenio').val();
  const secretariaMunicipal = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.SECRETARIA_MUNICIPAL.toString(), $j('#poder_publico_parceria_convenio').val()) != -1

  if (!poderPublico) {
    $j("#formas_contratacao_parceria_escola_secretaria_municipal").prop('disabled', true);
    $j("#formas_contratacao_parceria_escola_secretaria_municipal").val('');
    $j("#formas_contratacao_parceria_escola_secretaria_municipal").trigger("chosen:updated");
    return;
  }

  if (!secretariaMunicipal) {
    $j("#formas_contratacao_parceria_escola_secretaria_municipal").prop('disabled', true);
    $j("#formas_contratacao_parceria_escola_secretaria_municipal").val('');
    $j("#formas_contratacao_parceria_escola_secretaria_municipal").trigger("chosen:updated");
    return;
  }

  $j("#formas_contratacao_parceria_escola_secretaria_municipal").prop('disabled', false);
  $j("#formas_contratacao_parceria_escola_secretaria_municipal").trigger("chosen:updated");
}

function habilitaCampoFormaDeContratacao() {
  const poderPublico = $j('#poder_publico_parceria_convenio').val();
  const naoPossueParceriaOuConvenio = $j.inArray(PODER_PUBLICO_PARCERIA_CONVENIO.NAO_POSSUI_PARCERIA_OU_CONVENIO.toString(), $j('#poder_publico_parceria_convenio').val()) != -1

  if (!poderPublico) {
    $j("#formas_contratacao_adm_publica_e_outras_instituicoes").prop('disabled', true);
    $j("#formas_contratacao_adm_publica_e_outras_instituicoes").val('');
    $j("#formas_contratacao_adm_publica_e_outras_instituicoes").trigger("chosen:updated");
    return;
  }

  if (naoPossueParceriaOuConvenio) {
    $j("#formas_contratacao_adm_publica_e_outras_instituicoes").prop('disabled', true);
    $j("#formas_contratacao_adm_publica_e_outras_instituicoes").val('');
    $j("#formas_contratacao_adm_publica_e_outras_instituicoes").trigger("chosen:updated");
    return;
  }

  $j("#formas_contratacao_adm_publica_e_outras_instituicoes").prop('disabled', false);
  $j("#formas_contratacao_adm_publica_e_outras_instituicoes").trigger("chosen:updated");
}

function habilitaCampoOrgaoVinculadoEscola() {
  if ($j('#dependencia_administrativa').val() != DEPENDENCIA_ADMINISTRATIVA.PRIVADA) {
    $j("#orgao_vinculado_escola").prop('disabled', false);
    $j("#orgao_vinculado_escola").trigger("chosen:updated");
  } else {
    $j("#orgao_vinculado_escola").prop('disabled', true);
    $j("#orgao_vinculado_escola").trigger("chosen:updated");
  }
}

function obrigaCampoOrgaoVinculadoEscola() {
  if (obrigarCamposCenso && $j('#dependencia_administrativa').val() != DEPENDENCIA_ADMINISTRATIVA.PRIVADA) {
    $j("#orgao_vinculado_escola").makeUnrequired();
    $j("#orgao_vinculado_escola").makeRequired();
  } else {
    $j("#orgao_vinculado_escola").makeUnrequired();
  }
}

function habilitaCampoEsferaAdministrativa() {
  let regulamentacao = $j('#regulamentacao').val();

  if (regulamentacao === '0') {
    $j("#esfera_administrativa").prop('disabled', true);
    $j('#esfera_administrativa').makeUnrequired();
    $j("#esfera_administrativa").val('');
  } else {
    $j("#esfera_administrativa").prop('disabled', false);
    if (obrigarCamposCenso) {
      $j('#esfera_administrativa').makeRequired();
    }
  }
}
function changeNumeroDeSalas() {
  const containsPredioEscolar = $j.inArray(LOCAL_FUNCIONAMENTO.PREDIO_ESCOLAR.toString(), $j('#local_funcionamento').val()) > -1;

  $j('#numero_salas_utilizadas_dentro_predio').prop('disabled', !containsPredioEscolar);
  if (obrigarCamposCenso) {
    if (containsPredioEscolar) {
      $j('#numero_salas_utilizadas_dentro_predio').makeRequired();
      $j('#numero_salas_utilizadas_fora_predio').makeUnrequired();
    } else {
      $j('#numero_salas_utilizadas_dentro_predio').makeUnrequired();
      $j('#numero_salas_utilizadas_fora_predio').makeRequired();
      $j('#numero_salas_utilizadas_dentro_predio').val('');
    }
  }
}

function changeLocalFuncionamento(){
    var disabled = $j.inArray(LOCAL_FUNCIONAMENTO.PREDIO_ESCOLAR.toString(), $j('#local_funcionamento').val()) == -1;
    $j('#condicao').prop("disabled",disabled);
    $j('#predio_compartilhado_outra_escola').prop("disabled",disabled);
    $j('#condicao').makeUnrequired();
    $j('#predio_compartilhado_outra_escola').makeUnrequired();
    $j('#dependencia_numero_salas_existente').makeUnrequired();
    $j('#codigo_inep_escola_compartilhada').makeUnrequired();
    if (!disabled && obrigarCamposCenso) {
        $j('#condicao').makeRequired();
        $j('#predio_compartilhado_outra_escola').makeRequired();
        $j('#dependencia_numero_salas_existente').makeRequired();
        $j('#codigo_inep_escola_compartilhada').makeRequired();
    }
}

function changePredioCompartilhadoEscola() {
    var disabled = $j('#predio_compartilhado_outra_escola').val() != 1;
    $j('#codigo_inep_escola_compartilhada').prop("disabled",disabled);
    $j('#codigo_inep_escola_compartilhada2').prop("disabled",disabled);
    $j('#codigo_inep_escola_compartilhada3').prop("disabled",disabled);
    $j('#codigo_inep_escola_compartilhada4').prop("disabled",disabled);
    $j('#codigo_inep_escola_compartilhada5').prop("disabled",disabled);
    $j('#codigo_inep_escola_compartilhada6').prop("disabled",disabled);
}

function changePossuiDependencias() {
    var disabled = $j('#possui_dependencias').val() != 1;
    $j('#salas_gerais').prop("disabled",disabled);
    $j('#salas_funcionais').prop("disabled",disabled);
    $j('#banheiros').prop("disabled",disabled);
    $j('#laboratorios').prop("disabled",disabled);
    $j('#salas_atividades').prop("disabled",disabled);
    $j('#dormitorios').prop("disabled",disabled);
    $j('#areas_externas').prop("disabled",disabled);
    $j("#salas_gerais,#salas_funcionais,#banheiros,#laboratorios,#salas_atividades,#dormitorios,#areas_externas").trigger("chosen:updated");
}

const link = '<span> Caso não encontre a pessoa jurídica, cadastre em </span><a href="empresas_cad.php" target="_blank">Pessoas > Cadastros > Pessoas jurídicas.</a>';
$j('#pessoaj_idpes').after(link);

//abas

// hide nos campos das outras abas (deixando só os campos da primeira aba)
if (!$j('#pessoaj_idpes').is(':visible')) {

  $j('td .formdktd:first').append(
    '<div id="tabControl"><ul>' +
    '<li><div id="tab1" class="escolaTab"><span class="tabText">Dados gerais</span></div></li>' +
    '<li><div id="tab3" class="escolaTab"> <span class="tabText">Infraestrutura</span></div></li>' +
    '<li><div id="tab4" class="escolaTab"> <span class="tabText">Dependências</span></div></li>' +
    '<li><div id="tab5" class="escolaTab"> <span class="tabText">Equipamentos</span></div></li>' +
    '<li><div id="tab6" class="escolaTab"> <span class="tabText">Recursos</span></div></li>' +
    '<li><div id="tab7" class="escolaTab"><span class="tabText">Dados do ensino</span></div></li>' +
    '<li><div id="tab8" class="escolaTab"><span class="tabText">Espaços Escolares</span></div></li>' +
    '</ul></div>');
  $j('td .formdktd b').remove();
  $j('#tab1').addClass('escolaTab-active').removeClass('escolaTab');

  // Atribui um id a linha, para identificar até onde/a partir de onde esconder os campos
  $j('#local_funcionamento').closest('tr').attr('id','tlocal_funcionamento');
  $j('#atendimento_aee').closest('tr').attr('id','tatendimento_aee');
  $j('#espacos').closest('tr').attr('id','tespacos');

  // Pega o número dessa linha
  linha_inicial_infra = $j('#tlocal_funcionamento').index()-2;
  linha_inicial_dependencia = $j('#tr_possui_dependencias').index()-2;
  linha_inicial_equipamento = $j('#tr_equipamentos').index()-2;
  linha_inicial_recursos = $j('#tr_quantidade_profissionais').index()-3;
  linha_inicial_dados = $j('#tatendimento_aee').index()-2;
  linha_inicial_espacos = $j('#tespacos').index()-2;

  // Adiciona um ID à linha que termina o formulário para parar de esconder os campos
  $j('.tableDetalheLinhaSeparador').closest('tr').attr('id','stop');
  $j('.tablecadastro > tbody > tr').each(function(index, row) {
    if ( index >= linha_inicial_infra){
      if (row.id !== 'stop') {
        row.hide();
      } else {
        return false;
      }
    }
  });
}

$j(document).ready(function() {

  // on click das abas
  habilitaCampoPoderPublicoOuConvenio();
  // DADOS GERAIS
  $j('#tab1').click(
    function() {
      $j('.escolaTab-active').toggleClass('escolaTab-active escolaTab');
      $j('#tab1').toggleClass('escolaTab escolaTab-active')
      $j('.tablecadastro > tbody > tr').each(function(index, row) {
        if (index >= linha_inicial_infra) {
          if (row.id !== 'stop') {
            row.hide();
          } else {
            return false;
          }
        } else {
          row.show();
        }
      });
    }
  );

  // DEPENDENCIAS
  $j('#tab3').click(
    function(){
      $j('.escolaTab-active').toggleClass('escolaTab-active escolaTab');
      $j('#tab3').toggleClass('escolaTab escolaTab-active')
      $j('.tablecadastro > tbody > tr').each(function(index, row) {
        if (row.id !== 'stop') {
          if (index >= linha_inicial_infra && index < linha_inicial_dependencia) {
            row.show();
          } else if (index > 0) {
            row.hide();
          }
        } else {
          return false;
        }
      });
      changeLocalFuncionamento();
      changePredioCompartilhadoEscola();
      changeNumeroDeSalas();
    });

  // EQUIPAMENTOS
  $j('#tab4').click(
    function(){
      $j('.escolaTab-active').toggleClass('escolaTab-active escolaTab');
      $j('#tab4').toggleClass('escolaTab escolaTab-active')
      $j('.tablecadastro > tbody > tr').each(function(index, row) {
        if (row.id !== 'stop') {
          if (index >= linha_inicial_dependencia && index < linha_inicial_equipamento){
            row.show();
          } else if (index > 0) {
            row.hide();
          }
        } else {
          return false;
        }
      });
      habilitaCamposNumeroSalas();
    });

  // Dados educacionais
  $j('#tab5').click(
    function(){
      $j('.escolaTab-active').toggleClass('escolaTab-active escolaTab');
      $j('#tab5').toggleClass('escolaTab escolaTab-active')
      $j('.tablecadastro > tbody > tr').each(function(index, row) {
        if (row.id !== 'stop'){
          if (index >= linha_inicial_equipamento && index < linha_inicial_recursos){
            row.show();
          }else if ( index > 0){
            row.hide();
          }
        } else {
          return false;
        }
      });
      habilitaCampoAcessoInternet();
      habilitaCampoEquipamentosAcessoInternet();
      habilitaCamposQuantidadeComputadoresAlunos();
      obrigaEquipamentos();
    });

  function obrigaEquipamentos() {
    $j('#equipamentos').makeUnrequired();
    if(obrigarCamposCenso) {
      $j('#equipamentos').makeRequired();
    }
  }

  // Dados educacionais
  $j('#tab6').click(
    function() {
      $j('.escolaTab-active').toggleClass('escolaTab-active escolaTab');
      $j('#tab6').toggleClass('escolaTab escolaTab-active')
      $j('.tablecadastro > tbody > tr').each(function(index, row) {
        if (row.id !== 'stop'){
          if (index >= linha_inicial_recursos && index < linha_inicial_dados){
            row.show();
          } else if (index > 0) {
            row.hide();
          }
          habilitaRecuros();
        } else {
          return false;
        }
      });
    });

  // Dados educacionais
  $j('#tab7').click(
    function() {
      $j('.escolaTab-active').toggleClass('escolaTab-active escolaTab');
      $j('#tab7').toggleClass('escolaTab escolaTab-active')
      $j('.tablecadastro > tbody > tr').each(function(index, row) {
        if (row.id !== 'stop') {
          if (index >= linha_inicial_dados && index < linha_inicial_espacos){
            row.show();
          } else if (index > 0){
            row.hide();
          }
        } else {
          return false;
        }
      });

        habilitarCampoUnidadeVinculada();
        mostrarCamposDaUnidadeVinculada();
        obrigarCamposDaUnidadeVinculada();
        obrigarCnpjMantenedora();
        habilitaCampoEducacaoIndigena();
        habilitaCampoLinguaMinistrada();
        habilitaReservaVagasCotas();
        habilitaAcoesAmbientais();
        obrigraInstrumentosPedagogicos();
      });

  // Dados espaço escolares
  $j('#tab8').click(
    function() {
      $j('.escolaTab-active').toggleClass('escolaTab-active escolaTab');
      $j('#tab8').toggleClass('escolaTab escolaTab-active')
      $j('.tablecadastro > tbody > tr').each(function(index, row) {
        if (row.id !== 'stop') {
          if (index >= linha_inicial_espacos) {
            row.show();
          } else if (index > 0){
            row.hide();
          }
        } else {
          return false;
        }
      });
    });

  function  obrigraInstrumentosPedagogicos() {
    $j('#instrumentos_pedagogicos').makeUnrequired();
    if (obrigarCamposCenso) {
      $j('#instrumentos_pedagogicos').makeRequired();
    }
  }

  // fix checkboxs
  $j('input:checked').val('on');

  let verificaCamposDepAdm = () => {
    $j('#categoria_escola_privada').makeUnrequired();
    $j('#conveniada_com_poder_publico').makeUnrequired();
    $j('#mantenedora_escola_privada').makeUnrequired();
    $j('#categoria_escola_privada').prop('disabled', true);
    $j('#conveniada_com_poder_publico').prop('disabled', true);
    $j('#mantenedora_escola_privada').prop('disabled', true);
    $j("#mantenedora_escola_privada").trigger("chosen:updated");
    $j('#cnpj_mantenedora_principal').prop('disabled', true);

    if (obrigarCamposCenso && $j('#situacao_funcionamento').val() == '1' && $j('#dependencia_administrativa').val() == DEPENDENCIA_ADMINISTRATIVA.PRIVADA){
      $j('#conveniada_com_poder_publico').makeRequired();
      $j('#mantenedora_escola_privada').makeRequired();
    }

    if ($j('#situacao_funcionamento').val() == '1' && $j('#dependencia_administrativa').val() == DEPENDENCIA_ADMINISTRATIVA.PRIVADA){
      $j('#conveniada_com_poder_publico').prop('disabled', false);
      $j('#mantenedora_escola_privada').prop('disabled', false);
      $j("#mantenedora_escola_privada").trigger("chosen:updated");
      $j('#cnpj_mantenedora_principal').prop('disabled', false);
    }

    if (obrigarCamposCenso && $j('#dependencia_administrativa').val() == DEPENDENCIA_ADMINISTRATIVA.PRIVADA){
      $j('#categoria_escola_privada').makeRequired();
    }

    if ($j('#dependencia_administrativa').val() == DEPENDENCIA_ADMINISTRATIVA.PRIVADA){
      $j('#categoria_escola_privada').prop('disabled', false);
    }
  }

  $j('#dependencia_administrativa').change(
    function (){
      verificaCamposDepAdm();
      habilitaCampoOrgaoVinculadoEscola();
      obrigaCampoOrgaoVinculadoEscola();
    }
  );

  habilitaCampoOrgaoVinculadoEscola();
  obrigaCampoOrgaoVinculadoEscola();
  obrigaCampoRegulamentacao();
  changePossuiDependencias();

  $j('#possui_dependencias').change(
    function (){
        changePossuiDependencias();
    }
  );

  $j('#unidade_vinculada_outra_instituicao').change(
    function (){
      mostrarCamposDaUnidadeVinculada();
      obrigarCamposDaUnidadeVinculada();
    }
  );

  function mostrarCamposDaUnidadeVinculada() {
    if ($j('#unidade_vinculada_outra_instituicao').val() == UNIDADE_VINCULADA.EDUCACAO_BASICA) {
      $j('#inep_escola_sede').prop('disabled', false);
      $j('#codigo_ies').prop('disabled', true);
      $j('#codigo_ies').val('');
      $j('#codigo_ies_id').val('');
    } else if($j('#unidade_vinculada_outra_instituicao').val() == UNIDADE_VINCULADA.ENSINO_SUPERIOR) {
      $j('#codigo_ies').prop('disabled', false);
      $j('#inep_escola_sede').prop('disabled', true);
      $j('#inep_escola_sede').val('');
    } else {
      $j('#inep_escola_sede').prop('disabled', true);
      $j('#codigo_ies').prop('disabled', true);
      $j('#inep_escola_sede').val('');
      $j('#codigo_ies').val('');
      $j('#codigo_ies_id').val('');
    }
  }

  function habilitarCampoUnidadeVinculada() {
    escolaEmAtividade = $j('#situacao_funcionamento').val() == SITUACAO_FUNCIONAMENTO.EM_ATIVIDADE;

    if (escolaEmAtividade) {
      $j("#unidade_vinculada_outra_instituicao").prop('disabled', false);
      if (obrigarCamposCenso) {
        $j("#unidade_vinculada_outra_instituicao").makeRequired();
      }
    } else {
      $j("#unidade_vinculada_outra_instituicao").val('');
      $j("#unidade_vinculada_outra_instituicao").prop('disabled', true);
      $j("#unidade_vinculada_outra_instituicao").makeUnrequired();
    }
  }

  function obrigarCamposDaUnidadeVinculada() {
    if ($j('#unidade_vinculada_outra_instituicao').val() == UNIDADE_VINCULADA.EDUCACAO_BASICA && obrigarCamposCenso) {
      $j('#inep_escola_sede').makeRequired();
      $j('#codigo_ies').makeUnrequired();
    } else if($j('#unidade_vinculada_outra_instituicao').val() == UNIDADE_VINCULADA.ENSINO_SUPERIOR && obrigarCamposCenso) {
      $j('#codigo_ies').makeRequired();
      $j('#inep_escola_sede').makeUnrequired();
    } else {
      $j('#inep_escola_sede').makeUnrequired();
      $j('#codigo_ies').makeUnrequired();
    }
  }

  $j('#mantenedora_escola_privada').on('change', () => obrigarCnpjMantenedora());



  $j('#situacao_funcionamento').change(
    function(){
      verificaCamposDepAdm();
      obrigaCampoRegulamentacao();
      habilitarCampoUnidadeVinculada();
      habilitaCampoPoderPublicoOuConvenio();
      obrigaCampoFormaDeContratacao();
      obrigaCampoFormaDeContratacaoEscolaSecretariaEstadual();
      obrigaCampoFormaDeContratacaoEscolaSecretariaMunicipal();
      habilitaCampoFormaDeContratacao();
      habilitaCampoFormaDeContratacaoEscolaSecretariaEstadual();
      habilitaCampoFormaDeContratacaoEscolaSecretariaMunicipal();
    }
  );

  function habilitaCampoPoderPublicoOuConvenio() {

    let situacaoFuncionamento = $j('#situacao_funcionamento').val();

    if (obrigarCamposCenso && situacaoFuncionamento == SITUACAO_FUNCIONAMENTO.EM_ATIVIDADE) {
      $j('#poder_publico_parceria_convenio').makeRequired();
      $j("#poder_publico_parceria_convenio").val('');
      $j("#poder_publico_parceria_convenio").prop('disabled', false);
      $j("#poder_publico_parceria_convenio").trigger("chosen:updated");
      return;
    }

    if (obrigarCamposCenso && situacaoFuncionamento != SITUACAO_FUNCIONAMENTO.EM_ATIVIDADE) {
      $j('#poder_publico_parceria_convenio').makeUnrequired();
      $j("#poder_publico_parceria_convenio").val('disabled', true);
      $j("#poder_publico_parceria_convenio").prop('disabled', true);
      $j("#poder_publico_parceria_convenio").trigger("chosen:updated");
    }
  }

  $j('#regulamentacao').change(
    function(){
      habilitaCampoEsferaAdministrativa();
    }
  );

  verificaCamposDepAdm();
  habilitaCampoEsferaAdministrativa();

  let verificaLatitudeLongitude = () => {
    let regex = new RegExp('^(\\-?\\d+(\\.\\d+)?)\\.\\s*(\\-?\\d+(\\.\\d+)?)\$');

    let longitude = $j('#longitude').val();

    if (longitude && !regex.exec(longitude)) {
      messageUtils.error('Longitude informada inválida.');
      $j('#longitude').val('').focus();
      longitude = '';
    }

    let latitude = $j('#latitude').val();
    if (latitude && !regex.exec(latitude)) {
      messageUtils.error('Latitude informada inválida.');
      $j('#latitude').val('').focus();
      latitude = '';
    }
    $j('#latitude').makeUnrequired();
    $j('#longitude').makeUnrequired();

    if (obrigarCamposCenso && (latitude || longitude)) {
      $j('#latitude').makeRequired();
      $j('#longitude').makeRequired();
    }

  }

  $j('#latitude').on('change', verificaLatitudeLongitude);
  $j('#longitude').on('change', verificaLatitudeLongitude);
});

const cnpj = document.getElementById('cnpj');

if (cnpj !== null) {
  document.getElementById('cnpj').readOnly = true;
}

function getCurso(cursos)
{
  const campoCurso = document.getElementById('ref_cod_curso');

  if(cursos.length)
    {
        setAttributes(campoCurso,'Selecione um curso',false);

        $j.each(cursos, function(i, item) {
            campoCurso.options[campoCurso.options.length] = new Option(item.name,item.id, false, false);
        });
    }
    else
        campoCurso.options[0].text = 'A instituição não possui nenhum curso';
}


if ( document.getElementById('ref_cod_instituicao') )
{
    document.getElementById('ref_cod_instituicao').onchange = function()
    {
        const campoInstituicao = document.getElementById('ref_cod_instituicao').value;

        const campoCurso = document.getElementById('ref_cod_curso');
        setAttributes(campoCurso,'Carregando curso',true);

        getApiResource("/api/resource/course",getCurso,{institution:campoInstituicao});

        if (this.value == '')
        {
            $('img_rede_ensino').style.display = 'none;';
        }
        else
        {
            $('img_rede_ensino').style.display = '';
        }

    }
}

var search = function (request, response) {
    var searchPath = '/module/Api/Servidor?oper=get&resource=servidor-search',
        params = {
            query: request.term
        };

    $j.get(searchPath, params, function (dataResponse) {
        simpleSearch.handleSearch(dataResponse, response);
    });
};

var handleSelect = function (event, ui) {
    var target = $j(event.target),
        id = target.attr('id'),
        idNum = id.match(/\[(\d+)\]/),
        refIdServidor = $j('input[id="servidor_id[' + idNum[1] + ']"]'),
        refInepServidor = $j('input[id="managers_inep_id[' + idNum[1] + ']"]'),
        refEmail = $j('input[id="managers_email[' + idNum[1] + ']"]');

    target.val(ui.item.label);
    refIdServidor.val(ui.item.value);

    var searchPath = '/module/Api/Servidor?oper=get&resource=dados-servidor',
        params = {
            servidor_id: ui.item.value
        };

    $j.get(searchPath, params, function (dataResponse) {
        refInepServidor.val(dataResponse.result.inep);
        refEmail.val(dataResponse.result.email);
    });

    return false;
};

function setAutoComplete() {
    $j.each($j('input[id^="servidor"]'), function (index, field) {
        $j(field).autocomplete({
            source: search,
            select: handleSelect,
            minLength: 1,
            autoFocus: true,
            autoSelect: true,
        });

        $j(field).attr('placeholder', 'Digite um nome para buscar');
    });

    $j('input[id^="servidor"]').blur(function() {
        validateServidor(this)
    });
};

setAutoComplete();

function validateServidor(field){
    var id = $j(field).attr('id'),
        idNum = id.match(/\[(\d+)\]/),
        refIdServidor = $j('input[id="servidor_id[' + idNum[1] + ']"]');

    if ($j(field).val() === '') {
        refIdServidor.val('')
    } else {
        if (refIdServidor.val() === '') {
            messageUtils.error('O campo: <b>Nome do(a) gestor(a)</b> deve ser preenchido com o cadastro de um servidor pré-cadastrado', field);
        }
    }
}

$j('#btn_add_tab_add_1').click(function () {
    setAutoComplete();
    addEventManegerInep();
});

$j.each($j('input[id^="managers_access_criteria_description"]'), function (index, field) {
    $j(field).val(decodeURIComponent($j(field).val().replace(/\+/g, ' ')));
});

$j.each($j('input[id^="managers_email"]'), function (index, field) {
    $j(field).val(decodeURIComponent($j(field).val().replace(/\+/g, ' ')));
});

$j('input[id^="managers_inep_id"]').keyup(function(){
    var oldValue = this.value;

    this.value = this.value.replace(/[^0-9\.]/g, '');
    this.value = this.value.replace('.', '');

    if (oldValue != this.value)
        messageUtils.error('Informe apenas números.', this);
});

addEventManegerInep();

function validateManagerInep(field) {
    if ($j(field).val().length != 12 && $j(field).val().length != 0) {
        messageUtils.error("O campo: Código INEP do gestor(a) deve conter 12 dígitos.");
        $j(field).addClass('error');
    }
}

function addEventManegerInep() {
    $j.each($j('input[id^="managers_inep_id"]'), function (index, field) {
        field.on('blur', function () {
            validateManagerInep(this);
        });
    });
}

function habilitaCamposNumeroSalas() {
    let disabled = $j('#numero_salas_utilizadas_dentro_predio').val() == '' &&
        $j('#numero_salas_utilizadas_fora_predio').val() == '';

    $j('#numero_salas_climatizadas').prop('disabled', disabled);
    $j('#numero_salas_acessibilidade').prop('disabled', disabled);
}

$j('#numero_salas_utilizadas_dentro_predio,#numero_salas_utilizadas_fora_predio').blur(function () {
    habilitaCamposNumeroSalas();
});

function habilitaCampoAcessoInternet() {
    let disabled = $j.inArray(USO_INTERNET.NAO_POSSUI.toString(), $j('#uso_internet').val()) != -1;
    $j('#acesso_internet').prop('disabled', disabled);

    if (!disabled && obrigarCamposCenso) {
        $j('#acesso_internet').makeRequired();
    } else {
        $j('#acesso_internet').makeUnrequired();
    }
}

function habilitaCampoEquipamentosAcessoInternet() {
    let disabled = $j.inArray(USO_INTERNET.ALUNOS.toString(), $j('#uso_internet').val()) == -1;

    $j('#equipamentos_acesso_internet').prop('disabled', disabled);
    $j("#equipamentos_acesso_internet").trigger("chosen:updated");

    if (disabled) {
        $j('#equipamentos_acesso_internet').makeUnrequired();
    } else if(obrigarCamposCenso) {
        $j('#equipamentos_acesso_internet').makeRequired();
    }
}

$j('#uso_internet').on('change', function () {
    habilitaCampoAcessoInternet();
    habilitaCampoEquipamentosAcessoInternet();
});

function habilitaCamposQuantidadeComputadoresAlunos() {
    let disabled = $j.inArray(EQUIPAMENTOS_ACESSO_INTERNET.COMPUTADORES, $j('#equipamentos_acesso_internet').val()) == -1;

    $j('#quantidade_computadores_alunos_mesa, #quantidade_computadores_alunos_portateis, #quantidade_computadores_alunos_tablets').prop('disabled', disabled);
    $j("#quantidade_computadores_alunos_mesa, #quantidade_computadores_alunos_portateis, #quantidade_computadores_alunos_tablets").trigger("chosen:updated");
}

$j('#equipamentos_acesso_internet').on('change', function () {
  habilitaCamposQuantidadeComputadoresAlunos();
});

function habilitaCampoEducacaoIndigena() {
    var escolaIndigena = $j('#educacao_indigena').val() == 1;
    if(escolaIndigena && obrigarCamposCenso){
        makeRequired('lingua_ministrada');
    }else{
        makeUnrequired('lingua_ministrada');
        makeUnrequired('codigo_lingua_indigena');
    }

    $j('#lingua_ministrada').prop('disabled', !escolaIndigena);
    habilitaCampoLinguaMinistrada();
}

function habilitaCampoLinguaMinistrada() {
    var linguaIndigena = $j('#lingua_ministrada').val() == 2;
    if(linguaIndigena && obrigarCamposCenso){
        makeRequired('codigo_lingua_indigena');
    }else{
        makeUnrequired('codigo_lingua_indigena');
    }

    $j('#codigo_lingua_indigena').prop('disabled', !linguaIndigena);
    $j("#codigo_lingua_indigena").trigger("chosen:updated");
}

$j('#educacao_indigena').on('change', function() {
    habilitaCampoEducacaoIndigena()
});

$j('#lingua_ministrada').on('change', function() {
    habilitaCampoLinguaMinistrada()
});

function habilitaReservaVagasCotas() {
    var fazExameSelecao = $j('#exame_selecao_ingresso').val() == 1;
    if(fazExameSelecao && obrigarCamposCenso){
        makeRequired('reserva_vagas_cotas');
    }else{
        makeUnrequired('reserva_vagas_cotas');
    }

    $j('#reserva_vagas_cotas').prop('disabled', !fazExameSelecao);
    $j("#reserva_vagas_cotas").trigger("chosen:updated");
}

$j('#exame_selecao_ingresso').on('change', function() {
    habilitaReservaVagasCotas()
});

function habilitaAcoesAmbientais() {
  var acaoAmbiental = $j('#acao_area_ambiental').val() == 1;
  if(acaoAmbiental && obrigarCamposCenso){
    makeRequired('acoes_area_ambiental');
  }else{
    makeUnrequired('acoes_area_ambiental');
  }

  $j('#acoes_area_ambiental').prop('disabled', !acaoAmbiental);
  $j("#acoes_area_ambiental").trigger("chosen:updated");
}

$j('#acao_area_ambiental').on('change', function() {
  habilitaAcoesAmbientais()
});
