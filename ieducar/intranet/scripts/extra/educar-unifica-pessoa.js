// ============================================
// FUNÇÕES ORIGINAIS DO SISTEMA (PRESERVADAS)
// ============================================

adicionaMaisUmaLinhaNaTabela();
ajustaTabelaDePessoasUnificadas();
ajustarUiBotao();
adicionaBotoesInicias();

function ajustarUiBotao() {
    $j('#btn_add_tab_add_1').addClass('button_center');
    document.getElementById("btn_add_tab_add_1").lastChild.textContent = 'ADICIONAR MAIS PESSOAS';
}

$j('#btn_add_tab_add_1').click(function() {
    ajustaTabelaDePessoasUnificadas();
    $j('a[id^="link_remove["').css('font-weight', 'bold');
    $j('a[id^="link_remove["').css('text-decoration', 'underline');
});

function adicionaMaisUmaLinhaNaTabela() {
    tab_add_1.addRow();
    setTimeout(function() {
        setAutoComplete();
    }, 100);
}

function carregaDadosPessoas() {
    let pessoas_duplicadas = [];
    let message = '';

    $j('input[id^="pessoa_duplicada["').each(function(id, input) {
        if (input.value != "") {
            pessoas_duplicadas.push(input.value.split(' ')[0]);
        }
    });

    let pessoas_sem_duplicidade = [...new Set(pessoas_duplicadas)];

    if (pessoas_duplicadas.length <= 1) {
        message = 'Informe pelo menos duas pessoas para unificar.'
        defaultModal(message);
        return;
    }

    if (pessoas_duplicadas.length != pessoas_sem_duplicidade.length) {
        message = 'Selecione pessoas diferentes.'
        defaultModal(message);
        return;
    }

    $j('#btn_carregar_dados').prop('disabled', true).text('Carregando...');

    var url = getResourceUrlBuilder.buildUrl(
        '/module/Api/Pessoa',
        'dadosUnificacaoPessoa',
        {
            pessoas_ids: pessoas_duplicadas
        }
    );

    var options = {
        url: url,
        dataType: 'json',
        success: function(response) {
            $j('#btn_carregar_dados').prop('disabled', false).text('📊 Carregar dados');
            if (response && response.pessoas && response.pessoas.length > 0) {
                $j('#adicionar_linha').hide();
                listaDadosPessoasUnificadas(response);
            } else {
                defaultModal('Nenhum dado encontrado para as pessoas informadas.');
            }
        },
        error: function(xhr, status, error) {
            $j('#btn_carregar_dados').prop('disabled', false).text('📊 Carregar dados');
            console.error('Erro ao carregar dados:', error);
            defaultModal('Erro ao carregar dados das pessoas. Verifique os códigos informados e tente novamente.');
        }
    };

    if (typeof getResources !== 'undefined') {
        getResources(options);
    } else {
        $j.ajax(options);
    }
}

function listaDadosPessoasUnificadas(response) {
    modalAvisoComplementaDadosPessoa();
    removeExclusaoDePessoas();
    disabilitaSearchInputs();
    removeItensVazios();
    removeBotaoMaisPessoas();
    montaTabelaDadosPessoa(response);
    adicionaBotoes();
    adicionaCheckboxConfirmacao();
    uniqueCheck();
    desabilitaBotaoUnificar();
}

function removeBotaoMaisPessoas() {
    $j('#tabela_pessoas tr:last').remove();
}

function removeItensVazios() {
    $j('input[id^="pessoa_duplicada["').each(function(id, input) {
        let value = input.value.split(' ')[0];
        if (value.length === 0) {
            tab_add_1.removeRow(this);
        }
    });
}

function montaTabelaDadosPessoa(response) {
    $j('tr#lista_dados_pessoas_unificadas').remove().animate({});
    $j('tr#unifica_pessoa_titulo').remove().animate({});

    $j('<tr id="lista_dados_pessoas_unificadas"></td>').insertAfter($j('.tableDetalheLinhaSeparador').first().closest('tr')).hide().show('slow');
    $j(`
    <tr id="unifica_pessoa_titulo">
      <td colspan="2">
        <h2 class="unifica_pessoa_h2">
          Selecione a pessoa que tenha os dados relevantes mais completos.
        </h2>
       </td>
     </tr>
  `).insertAfter($j('.tableDetalheLinhaSeparador').first().closest('tr')).hide().show('slow');

    let html = `
    <td colspan="2">
    <table id="tabela_pessoas_unificadas">
      <tr class="tr_title">
         <th>Principal</th>
         <th>Código</th>
         <th>Nome</th>
         <th>Data de Nascimento</th>
         <th>CPF</th>
         <th>RG</th>
         <th>Nome da Mãe</th>
         <th>Ação</th>
       </tr>
  `;

    if (response.pessoas && response.pessoas.length > 0) {
        $j.each(response.pessoas, function(id, pessoa) {
            html += '<tr id="' + pessoa.idpes + '" class="linha_listagem">';
            html += '<td><input type="checkbox" class="check_principal" id="check_principal_' + pessoa.idpes + '"/></td>';
            html += '<td><a target="_new" href="/intranet/atendidos_det.php?cod_pessoa=' + pessoa.idpes + '">' + pessoa.idpes + '</a></td>';
            html += '<td><a target="_new" href="/intranet/atendidos_det.php?cod_pessoa=' + pessoa.idpes + '">' + pessoa.nome + '</a></td>';
            html += '<td><a target="_new" href="/intranet/atendidos_det.php?cod_pessoa=' + pessoa.idpes + '">' + pessoa.data_nascimento + '</a></td>';
            html += '<td><a target="_new" href="/intranet/atendidos_det.php?cod_pessoa=' + pessoa.idpes + '">' + addMascara(pessoa.cpf) + '</a></td>';
            html += '<td><a target="_new" href="/intranet/atendidos_det.php?cod_pessoa=' + pessoa.idpes + '">' + pessoa.rg + '</a></td>';
            html += '<td><a target="_new" href="/intranet/atendidos_det.php?cod_pessoa=' + pessoa.idpes + '">' + pessoa.pessoa_mae + '</a></td>';
            html += '<td><a class="link_remove" onclick="removePessoa(' + pessoa.idpes + ')"><b><u>EXCLUIR</u></b></a></td>';
            html += '</tr>';
        });
    }

    html += '</table></td>';

    $j('#lista_dados_pessoas_unificadas').html(html);
}

function adicionaCheckboxConfirmacao() {
    $j('<tr id="tr_confirma_dados_unificacao"></tr>').insertBefore($j('.linhaBotoes'));

    let htmlCheckbox = '<td colspan="2">'
    htmlCheckbox += '<input onchange="confirmaAnalise()" id="check_confirma_dados_unificacao" type="checkbox" />';
    htmlCheckbox += '<label for="check_confirma_dados_unificacao">Confirmo a análise de que são a mesma pessoa, levando <br> em conta a possibilidade de gêmeos cadastrados.</label>';
    htmlCheckbox += '</td>';

    $j('#tr_confirma_dados_unificacao').html(htmlCheckbox);
}

function confirmaAnalise() {
    let checked = $j('#check_confirma_dados_unificacao').is(':checked');

    if (existePessoaPrincipal() && checked) {
        habilitaBotaoUnificar();
        return;
    }

    if (checked) {
        desabilitaBotaoUnificar();
        defaultModal('Você precisa definir uma pessoa como principal.');
        desabilitaConfirmarDadosUnificar();
        return;
    }

    desabilitaBotaoUnificar();
    desabilitaConfirmarDadosUnificar();
}

function desabilitaConfirmarDadosUnificar() {
    $j('#check_confirma_dados_unificacao').prop('checked', false);
}

function addMascara(value) {

    if (value === 'Não consta' || !value) {
        return value || 'Não consta';
    }

    if (value.length <= 10) {
        value = String(value).padStart(11, '0');
    }

    return value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
}

function existePessoaPrincipal() {
    let existePessoaPrincipal = false;
    const checkbox = document.querySelectorAll('input.check_principal')
    checkbox.forEach(element => {
        if (element.checked == true) {
            existePessoaPrincipal = true;
        }
    });

    return existePessoaPrincipal;
}

function habilitaBotaoUnificar() {
    $j('#unifica_pessoa').prop('disabled', false);
    $j('#unifica_pessoa').addClass('btn-green');
    $j('#unifica_pessoa').removeClass('btn-disabled');
}

function desabilitaBotaoUnificar() {
    $j('#unifica_pessoa').prop('disabled', true);
    $j('#unifica_pessoa').removeClass('btn-green');
    $j('#unifica_pessoa').addClass('btn-disabled');
}

function uniqueCheck() {
    const checkbox = document.querySelectorAll('input.check_principal')
    checkbox.forEach(element => {
        element.addEventListener('click', handleClick.bind(event, checkbox));
    });
}

function handleClick(checkbox, event) {
    checkbox.forEach(element => {
        confirmaAnalise();
        if (event.currentTarget.id !== element.id) {
            element.checked = false;
        }
    });
}

function removePessoa(codPessoa) {
    if ($j('#tabela_pessoas_unificadas tr.linha_listagem').length <= 2) {
        makeDialog({
            content: 'É necessário ao menos 2 pessoas para a unificação, ao confirmar o processo vai ser reiniciado. Deseja prosseguir?',
            title: 'Atenção!',
            maxWidth: 860,
            width: 860,
            close: function() {
                $j('#dialog-container').dialog('destroy');
            },
            buttons: [{
                text: 'Confirmar',
                click: function() {
                    recarregar();
                    $j('#dialog-container').dialog('destroy');
                }
            }, {
                text: 'Cancelar',
                click: function() {
                    $j('#dialog-container').dialog('destroy');
                }
            }]
        });
        return;
    }

    desabilitaConfirmarDadosUnificar();
    desabilitaBotaoUnificar();
    removeTr(codPessoa);
}

function removeTr(codPessoa) {
    let trClose = $j('#' + codPessoa);
    trClose.fadeOut(400, function() {
        trClose.remove();
        if ($j('#tabela_pessoas_unificadas tr.linha_listagem').length < 2) {
            recarregar();
        }
    });
}

function adicionaBotoesInicias() {
    let htmlBotao = '<input type="button" class="botaolistagem" onclick="voltarParaLista();" value="Voltar" autocomplete="off">';
    htmlBotao += '<input type="button" id="btn_enviar" name="botaolistagem" onClick="carregaDadosPessoas();" value="Carregar dados" autoComplete="off">';
    htmlBotao += '<input type="button" id="btn_carregar_duplicatas" name="botaolistagem" onClick="carregarPossiveisDuplicatasAutomaticamente();" value="🔍 Carregar Duplicatas" autoComplete="off" style="background-color: #4CAF50; color: white;">';
    $j('.linhaBotoes td').html(htmlBotao);
}

function adicionaBotoes() {
    let htmlBotao = '<input type="button" class="botaolistagem" onclick="voltarParaLista();" value="Voltar" autocomplete="off">';
    htmlBotao += '<input type="button" class="botaolistagem" onclick="recarregar();" value="Cancelar" autocomplete="off">';
    htmlBotao += '<input id="unifica_pessoa" type="button" class="botaolistagem" onclick="showConfirmationMessage();" value="Unificar pessoas da lista" autocomplete="off">';
    $j('.linhaBotoes td').html(htmlBotao);
}

function voltarParaLista() {
    document.location.href = '/unificacao-pessoa'
}

function recarregar() {
    document.location.reload(true);
}

function removeExclusaoDePessoas() {
    $j('.tr_tabela_pessoas td a').each(function(id, input) {
        input.remove();
    });
}

function disabilitaSearchInputs() {
    $j('input[id^="pessoa_duplicada["').prop('disabled', true);
}

function defaultModal(message) {
    makeDialog({
        content: message,
        title: 'Atenção!',
        maxWidth: 400,
        width: 400,
        close: function() {
            $j('#dialog-container').dialog('destroy');
        },
        buttons: [{
            text: 'Ok',
            click: function() {
                $j('#dialog-container').dialog('destroy');
            }
        }, ]
    });
}

function ajustaTabelaDePessoasUnificadas() {
    $j('a[id^="link_remove["').empty().text('EXCLUIR');
    $j('input[id^="pessoa_duplicada["').attr("placeholder", "Informe nome, código, CPF ou RG da pessoa");
    setTimeout(function() {
        setAutoComplete();
    }, 100);
}

var handleSelect = function(event, ui) {
    $j(event.target).val(ui.item.label);
    return false;
};

var search = function(request, response) {
    var searchPath = '/module/Api/Pessoa?oper=get&resource=pessoa-search';
    var params = {
        query: request.term
    };

    $j.get(searchPath, params, function(dataResponse) {
        if (typeof simpleSearch !== 'undefined' && simpleSearch.handleSearch) {
            simpleSearch.handleSearch(dataResponse, response);
        } else {
            var results = [];
            if (dataResponse && dataResponse.pessoas) {
                dataResponse.pessoas.forEach(function(pessoa) {
                    results.push({
                        label: pessoa.idpes + ' - ' + pessoa.nome + ' (' + (pessoa.cpf || '') + ')',
                        value: pessoa.idpes + ' - ' + pessoa.nome
                    });
                });
            }
            response(results);
        }
    });
};

function showConfirmationMessage() {
    makeDialog({
        content: 'O processo de unificação de pessoas não poderá ser desfeito. Deseja continuar?',
        title: 'Atenção!',
        maxWidth: 860,
        width: 860,
        close: function() {
            $j('#dialog-container').dialog('destroy');
        },
        buttons: [{
            text: 'Confirmar',
            click: function() {
                enviaDados();
                $j('#dialog-container').dialog('destroy');
            }
        }, {
            text: 'Cancelar',
            click: function() {
                $j('#dialog-container').dialog('destroy');
            }
        }]
    });
}

function enviaDados() {

    let dados = [];
    const formData = document.createElement('form');
    formData.method = 'post';
    formData.action = 'educar_unifica_pessoa.php';

    $j('#tabela_pessoas_unificadas .linha_listagem').each(function(id, input) {
        let isChecked = $j('#check_principal_' + input.id).is(':checked');
        let pessoaParaUnificar = {};
        pessoaParaUnificar.idpes = input.id;
        pessoaParaUnificar.pessoa_principal = isChecked;
        dados.push(pessoaParaUnificar);
    });

    const acao = document.createElement('input');
    acao.type = 'hidden';
    acao.name = 'tipoacao';
    acao.value = 'Novo';
    formData.appendChild(acao);

    const hiddenField = document.createElement('input');
    hiddenField.type = 'hidden';
    hiddenField.name = 'pessoas';
    hiddenField.value = JSON.stringify(dados);
    formData.appendChild(hiddenField);

    document.body.appendChild(formData);
    formData.submit();
}

function modalAvisoComplementaDadosPessoa() {
    makeDialog({
        content: `Para complementar os dados da pessoa que selecionou como principal,
    é necessário fazê-lo manualmente editando os dados da mesma antes da Unificação de Pessoas.
    <b>Caso não faça essa complementação, os dados das pessoas não selecionadas como principal serão perdidos.<b>`,
        title: 'Atenção!',
        maxWidth: 860,
        width: 860,
        close: function() {
            $j('#dialog-container').dialog('destroy');
        },
        buttons: [{
            text: 'Ok',
            click: function() {
                $j('#dialog-container').dialog('destroy');
            }
        }, ]
    });
}

function setAutoComplete() {
    $j.each($j('input[id^="pessoa_duplicada"]'), function(index, field) {
        if ($j(field).data('ui-autocomplete')) {
            $j(field).autocomplete('destroy');
        }

        $j(field).autocomplete({
            source: search,
            select: handleSelect,
            minLength: 2,
            autoFocus: true,
            delay: 300
        });
    });
}

setAutoComplete();

// bind events
var $addPontosButton = $j('#btn_add_tab_add_1');

$addPontosButton.click(function() {
    setAutoComplete();
});

function makeDialog(params) {
    params.closeOnEscape = false;
    params.draggable = false;
    params.modal = true;

    var container = $j('#dialog-container');

    if (container.length < 1) {
        $j('body').append('<div id="dialog-container" style="width: 500px;"></div>');
        container = $j('#dialog-container');
    }

    if (container.hasClass('ui-dialog-content')) {
        container.dialog('destroy');
    }

    container.empty();
    container.html(params.content);

    delete params['content'];

    container.dialog(params);
}

// ============================================
// NOVAS FUNÇÕES ADICIONADAS
// ============================================

function carregarPossiveisDuplicatasAutomaticamente() {
    mostrarLoading('Buscando possíveis duplicatas de pessoas...');

    var url = getResourceUrlBuilder.buildUrl(
        '/module/Api/Pessoa',
        'possiveisDuplicatas',
        {}
    );

    var options = {
        url: url,
        dataType: 'json',
        success: function(response) {
            esconderLoading();

            if (response.duplicatas && response.duplicatas.length > 0) {
                var $inputs = $j('input[id^="pessoa_duplicada["');
                for (var i = 1; i < $inputs.length; i++) {
                    if (tab_add_1 && tab_add_1.removeRow) {
                        tab_add_1.removeRow($inputs[i]);
                    }
                }

                var primeiroGrupo = response.duplicatas[0];
                var pessoas = primeiroGrupo;

                if (pessoas && pessoas.length >= 2) {
                    for (var i = 1; i < pessoas.length; i++) {
                        if (tab_add_1 && tab_add_1.addRow) {
                            tab_add_1.addRow();
                        }
                    }

                    $j('input[id^="pessoa_duplicada["').each(function(id, input) {
                        if (pessoas[id]) {
                            $j(input).val(pessoas[id].idpes + ' - ' + pessoas[id].nome);
                        }
                    });

                    if (response.duplicatas.length > 1) {
                        defaultModal('Encontradas ' + response.duplicatas.length + ' possíveis duplicatas. Exibindo o primeiro grupo. Utilize a pesquisa manual para as demais.');
                    }

                    carregaDadosPessoas();
                } else {
                    defaultModal('Não foi possível processar as duplicatas encontradas.');
                }
            } else {
                defaultModal('Não foram encontradas possíveis duplicatas de pessoas no sistema.');
            }
        },
        error: function(xhr, status, error) {
            esconderLoading();
            console.error('Erro ao carregar duplicatas:', error);
            defaultModal('Erro ao carregar possíveis duplicatas. Tente novamente ou utilize a pesquisa manual.');
        }
    };

    if (typeof getResources !== 'undefined') {
        getResources(options);
    } else {
        $j.ajax(options);
    }
}

function mostrarLoading(mensagem) {
    if ($j('#loading-overlay').length === 0) {
        $j('body').append('<div id="loading-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:10000;display:flex;align-items:center;justify-content:center"><div style="background:#fff;padding:30px;border-radius:8px;font-size:16px;font-weight:bold;">' + (mensagem || 'Carregando...') + '</div></div>');
    }
}

function esconderLoading() {
    $j('#loading-overlay').remove();
}

// ============================================
// CARREGAMENTO AUTOMÁTICO AO INICIAR
// ============================================
$(document).ready(function() {
    setTimeout(function() {
        // Modo pré-carregamento: vindo do aviso de CPF duplicado em atendidos_cad.php
        // O PHP injeta window.__preloadPessoas = [{label:'55401 - Nome...'}, {label:'55402 - Nome...'}]
        if (window.__preloadPessoas && window.__preloadPessoas.length >= 2) {
            var $inputs = $j('input[id^="pessoa_duplicada["]');
            if ($inputs.length >= 2) {
                $inputs.eq(0).val(window.__preloadPessoas[0].label);
                $inputs.eq(1).val(window.__preloadPessoas[1].label);
                // Rolar até o formulário para o usuário ver os campos preenchidos
                var formEl = document.querySelector('.formulario-superior');
                if (formEl) formEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                carregaDadosPessoas();
            }
            return; // Não executar o auto-load de duplicatas
        }

        // Comportamento padrão: carregar possíveis duplicatas automaticamente
        if (typeof carregarPossiveisDuplicatasAutomaticamente === 'function') {
            carregarPossiveisDuplicatasAutomaticamente();
        }
    }, 1000);
});