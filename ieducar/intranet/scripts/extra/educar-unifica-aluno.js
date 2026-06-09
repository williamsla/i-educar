// ============================================
// FUNÇÕES ORIGINAIS DO SISTEMA (PRESERVADAS)
// ============================================

adicionaMaisUmaLinhaNaTabela();
ajustaTabelaDeAlunosUnificados();
ajustarUiBotao();
adicionaBotoesInicias();

function ajustarUiBotao() {
    $j('#btn_add_tab_add_1').addClass('button_center');
    document.getElementById("btn_add_tab_add_1").lastChild.textContent = 'ADICIONAR MAIS ALUNOS';
}

$j('#btn_add_tab_add_1').click(function() {
    ajustaTabelaDeAlunosUnificados();
    $j('a[id^="link_remove["').css('font-weight', 'bold');
    $j('a[id^="link_remove["').css('text-decoration', 'underline');
});

function adicionaMaisUmaLinhaNaTabela() {
    tab_add_1.addRow();
}

function carregaDadosAlunos() {
    let alunos_duplicados = [];
    let message = '';

    $j('input[id^="aluno_duplicado["').each(function(id, input) {
        if (input.value != "") {
            alunos_duplicados.push(input.value.split(' ')[0]);
        }
    });

    let alunos_sem_duplicidade = [...new Set(alunos_duplicados)];

    if (alunos_duplicados.length <= 1) {
        message = 'Informe pelo menos dois alunos para unificar.'
        defaultModal(message);
        return;
    }

    if (alunos_duplicados.length != alunos_sem_duplicidade.length) {
        message = 'Selecione alunos diferentes.'
        defaultModal(message);
        return;
    }

    var url = getResourceUrlBuilder.buildUrl(
        '/module/Api/Aluno',
        'dadosUnificacaoAlunos',
        {
            alunos_ids: alunos_duplicados
        }
    );

    var options = {
        url: url,
        dataType: 'json',
        success: function(response) {
            $j('#adicionar_linha').hide();
            listaDadosAlunosUnificados(response);
        }
    };

    getResources(options);
}

function listaDadosAlunosUnificados(response) {
    modalAvisoComplementaDadosAluno();
    removeExclusaoDeAlunos();
    disabilitaSearchInputs();
    removeItensVazios();
    removeBotaoMaisPessoas();
    montaTabelaDadosAluno(response);
    adicionaBotoes();
    adicionaCheckboxConfirmacao();
    uniqueCheck();
    desabilitaBotaoUnificar();
}

function removeBotaoMaisPessoas() {
    $j('#tabela_alunos tr:last').remove();
}

function removeItensVazios() {
    $j('input[id^="aluno_duplicado["').each(function(id, input) {
        let value = input.value.split(' ')[0];
        if (value.length === 0) {
            tab_add_1.removeRow(this);
        }
    });
}

function montaTabelaDadosAluno(response) {
    $j('tr#lista_dados_alunos_unificados').remove().animate({});
    $j('tr#unifica_aluno_titulo').remove().animate({});

    $j('<tr id="lista_dados_alunos_unificados"></td>').insertAfter($j('.tableDetalheLinhaSeparador').first().closest('tr')).hide().show('slow');
    $j(`
    <tr id="unifica_aluno_titulo">
      <td colspan="2">
        <h2 class="unifica_pessoa_h2">
          Selecione o(a) aluno(a) que tenha os dados relevantes mais completos.
        </h2>
       </td>
     </tr>
  `).insertAfter($j('.tableDetalheLinhaSeparador').first().closest('tr')).hide().show('slow');

    let html = `
    <td colspan="2">
    <table id="tabela_alunos_unificados">
      <tr class="tr_title">
         <th>Principal</th>
         <th>Código</th>
         <th>INEP</th>
         <th>Nome</th>
         <th>Data de Nascimento</th>
         <th>CPF</th>
         <th>RG</th>
         <th>Nome da Mãe</th>
         <th>Dados escolares</th>
         <th>Ação</th>
       </tr>
  `;

    response.alunos.each(function(aluno, id) {
        html += '<tr id="' + aluno.codigo + '" class="linha_listagem">';
        html += '<td><input type="checkbox" class="check_principal" id="check_principal_' + aluno.codigo + '"/></td>';
        html += '<td><a target="_new" href="/intranet/educar_aluno_det.php?cod_aluno=' + aluno.codigo + '">' + aluno.codigo + '</a></td>';
        html += '<td><a target="_new" href="/intranet/educar_aluno_det.php?cod_aluno=' + aluno.codigo + '">' + aluno.inep + '</a></td>';
        html += '<td><a target="_new" href="/intranet/educar_aluno_det.php?cod_aluno=' + aluno.codigo + '">' + aluno.nome + '</a></td>';
        html += '<td><a target="_new" href="/intranet/educar_aluno_det.php?cod_aluno=' + aluno.codigo + '">' + aluno.data_nascimento + '</a></td>';
        html += '<td><a target="_new" href="/intranet/educar_aluno_det.php?cod_aluno=' + aluno.codigo + '">' + addMascara(aluno.cpf) + '</a></td>';
        html += '<td><a target="_new" href="/intranet/educar_aluno_det.php?cod_aluno=' + aluno.codigo + '">' + aluno.rg + '</a></td>';
        html += '<td><a target="_new" href="/intranet/educar_aluno_det.php?cod_aluno=' + aluno.codigo + '">' + aluno.mae_aluno + '</a></td>';
        html += '<td><a onclick="visualizarDadosAlunos(' + aluno.codigo + ', \'' + aluno.nome + '\')">Visualizar</a></td>';
        html += '<td><a class="link_remove" onclick="removeAluno(' + aluno.codigo + ')"><b><u>EXCLUIR</u></b></a></td>';
        html += '</tr>';
    });

    html += '</table></td>';

    $j('#lista_dados_alunos_unificados').html(html);
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

    if (existeAlunoPrincipal() && checked) {
        habilitaBotaoUnificar();
        return;
    }

    if (checked) {
        desabilitaBotaoUnificar();
        defaultModal('Você precisa definir um(a) aluno(a) como principal.');
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

    if (value === 'Não consta') {
        return value
    }

    if (value.length <= 10) { // Quando o CPF tem 0 na frente o i-educar remove.
        value = String(value).padStart(11, '0'); // '0010'
    }

    return value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
}

function existeAlunoPrincipal() {
    let existeAlunoPrincipal = false;
    const checkbox = document.querySelectorAll('input.check_principal')
    checkbox.forEach(element => {
        if (element.checked == true) {
            existeAlunoPrincipal = true;
        }
    });

    return existeAlunoPrincipal;
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

function visualizarDadosAlunos(codAluno, nomeAluno) {
    var url = getResourceUrlBuilder.buildUrl(
        '/module/Api/Aluno',
        'dadosMatriculasHistoricosAlunos',
        {
            aluno_id: codAluno
        }
    );

    var options = {
        url: url,
        dataType: 'json',
        success: function(response) {
            modalMatriculasEHistoricos(response, nomeAluno);
        }
    };

    getResources(options);
}

function removeAluno(codAluno) {
    if ($j('#tabela_alunos_unificados tr').length === 3) {
        makeDialog({
            content: 'É necessário ao menos 2 alunos para a unificação, ao confirmar o processo vai ser reiniciado. Deseja prosseguir?',
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
    removeTr(codAluno);
}

function removeTr(codAluno) {
    let trClose = $j('#' + codAluno);
    trClose.fadeOut(400, function() {
        trClose.remove();
    });
}

function adicionaBotoesInicias() {
    let htmlBotao = '<input type="button" class="botaolistagem" onclick="voltarParaLista();" value="Voltar" autocomplete="off">';
    htmlBotao += '<input type="button" id="btn_enviar" name="botaolistagem" onClick="carregaDadosAlunos();" value="Carregar dados" autoComplete="off">';
    htmlBotao += '<input type="button" id="btn_carregar_duplicatas" name="botaolistagem" onClick="carregarPossiveisDuplicatasAutomaticamente();" value="🔍 Carregar Duplicatas" autoComplete="off" style="background-color: #4CAF50; color: white;">';
    $j('.linhaBotoes td').html(htmlBotao);
}

function adicionaBotoes() {
    let htmlBotao = '<input type="button" class="botaolistagem" onclick="voltarParaLista();" value="Voltar" autocomplete="off">';
    htmlBotao += '<input type="button" class="botaolistagem" onclick="recarregar();" value="Cancelar" autocomplete="off">';
    htmlBotao += '<input id="unifica_pessoa" type="button" class="botaolistagem" onclick="showConfirmationMessage();" value="Unificar alunos da lista" autocomplete="off">';
    $j('.linhaBotoes td').html(htmlBotao);
}

function voltarParaLista() {
    document.location.href = '/unificacao-aluno'
}

function recarregar() {
    document.location.reload(true);
}

function removeExclusaoDeAlunos() {
    $j('.tr_tabela_alunos td a').each(function(id, input) {
        input.remove();
    });
}

function disabilitaSearchInputs() {
    $j('input[id^="aluno_duplicado["').prop('disabled', true);
}

function modalMatriculasEHistoricos(response, nomeAluno) {
    let content = contentMatriculasEHistoricos(response);
    makeDialog({
        content: content,
        title: 'Dados escolares do(a) aluno(a) ' + nomeAluno,
        width: '90%',
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

function contentMatriculasEHistoricos(response) {
    let html = '';
    html += htmlTabelaMatriculas(response.matriculas);
    html += htmlTabelaHistoricos(response.historicos);

    return html;
}

function htmlTabelaMatriculas(matriculas) {
    let html = '<h4>Matrículas</h4>';
    html += '<table class="tabela-modal-dados-aluno">';
    html += '<tr class="tabela-modal-dados-aluno-titulo">';
    html += '<th>Ano</th>';
    html += '<th>Escola</th>';
    html += '<th>Curso</th>';
    html += '<th>Série</th>';
    html += '<th>Turma</th>';
    html += '</tr>';

    matriculas.each(function(matricula, id) {
        html += '<tr class="linha_listagem">';
        html += '<td>' + matricula.ano + '</td>';
        html += '<td>' + matricula.escola + '</td>';
        html += '<td>' + matricula.curso + '</td>';
        html += '<td>' + matricula.serie + '</td>';
        html += '<td>' + matricula.turma + '</td>';
        html += '</tr>';
    });

    html += '</table>';

    return html;
}

function htmlTabelaHistoricos(historicos) {
    let html = '<h4>Históricos escolares</h4>';
    html += '<table class="tabela-modal-dados-aluno">';
    html += '<tr class="tabela-modal-dados-aluno-titulo">';
    html += '<th>Ano</th>';
    html += '<th>Escola</th>';
    html += '<th>Curso</th>';
    html += '<th>Serie</th>';
    html += '<th>Situação</th>';
    html += '</tr>';

    historicos.each(function(historico, id) {
        html += '<tr class="linha_listagem">';
        html += '<td>' + historico.ano + '</td>';
        html += '<td>' + historico.escola + '</td>';
        html += '<td>' + historico.curso + '</td>';
        html += '<td>' + historico.serie + '</td>';
        html += '<td>' + historico.situacao + '</td>';
        html += '</tr>';
    });

    html += '</table>';

    return html;
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

function ajustaTabelaDeAlunosUnificados() {
    $j('a[id^="link_remove["').empty().text('EXCLUIR');
    $j('input[id^="aluno_duplicado["').attr("placeholder", "Informe nome ou código do(a) aluno(a)");
}

var handleSelect = function(event, ui) {
    $j(event.target).val(ui.item.label);
    return false;
};

var search = function(request, response) {
    var searchPath = '/module/Api/Aluno?oper=get&resource=aluno-search';
    var params = {
        query: request.term
    };

    $j.get(searchPath, params, function(dataResponse) {
        simpleSearch.handleSearch(dataResponse, response);
    });
};

function showConfirmationMessage() {
    makeDialog({
        content: 'Você está realizando uma unificação de alunos. Deseja continuar?',
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
    formData.action = 'educar_unifica_aluno.php';

    $j('#tabela_alunos_unificados .linha_listagem').each(function(id, input) {
        let isChecked = $j('#check_principal_' + input.id).is(':checked');
        let alunoParaUnificar = {};
        alunoParaUnificar.codAluno = input.id;
        alunoParaUnificar.aluno_principal = isChecked;
        dados.push(alunoParaUnificar);
    });

    const acao = document.createElement('input');
    acao.type = 'hidden';
    acao.name = 'tipoacao';
    acao.value = 'Novo';
    formData.appendChild(acao);

    const hiddenField = document.createElement('input');
    hiddenField.type = 'hidden';
    hiddenField.name = 'alunos';
    hiddenField.value = JSON.stringify(dados);
    formData.appendChild(hiddenField);

    document.body.appendChild(formData);
    formData.submit();
}

function modalAvisoComplementaDadosAluno() {
    makeDialog({
        content: `Para complementar os dados do(a) aluno(a) que selecionou como principal,
    é necessário fazê-lo manualmente editando os dados do(a) mesmo(a) antes da Unificação de Alunos.
    <b>Caso não faça essa complementação, os dados dos alunos não selecionadas como principal serão perdidos,
    exceto matrículas e dados de histórico.<b>`,
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
    $j.each($j('input[id^="aluno_duplicado"]'), function(index, field) {

        $j(field).autocomplete({
            source: search,
            select: handleSelect,
            minLength: 1,
            autoFocus: true
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
    mostrarLoading('Buscando possíveis duplicatas de alunos...');

    var url = getResourceUrlBuilder.buildUrl(
        '/module/Api/Aluno',
        'possiveisDuplicatas',
        {}
    );

    var options = {
        url: url,
        dataType: 'json',
        success: function(response) {
            esconderLoading();

            if (response.duplicatas && response.duplicatas.length > 0) {
                // Limpar campos existentes, mantendo apenas o primeiro
                var $inputs = $j('input[id^="aluno_duplicado["');
                for (var i = 1; i < $inputs.length; i++) {
                    if (tab_add_1 && tab_add_1.removeRow) {
                        tab_add_1.removeRow($inputs[i]);
                    }
                }

                // Para cada grupo de duplicatas (pegamos apenas o primeiro grupo)
                var primeiroGrupo = response.duplicatas[0];
                var alunos = primeiroGrupo;

                if (alunos.length >= 2) {
                    // Adicionar linhas necessárias
                    for (var i = 1; i < alunos.length; i++) {
                        if (tab_add_1 && tab_add_1.addRow) {
                            tab_add_1.addRow();
                        }
                    }

                    // Preencher os campos com os dados dos alunos
                    $j('input[id^="aluno_duplicado["').each(function(id, input) {
                        if (alunos[id]) {
                            $j(input).val(alunos[id].codigo + ' - ' + alunos[id].nome);
                        }
                    });

                    // Se houver mais grupos, mostrar mensagem
                    if (response.duplicatas.length > 1) {
                        defaultModal('Encontradas ' + response.duplicatas.length + ' possíveis duplicatas. Exibindo o primeiro grupo. Utilize a pesquisa manual para as demais.');
                    }

                    // Carregar os dados dos alunos
                    carregaDadosAlunos();
                } else {
                    defaultModal('Não foi possível processar as duplicatas encontradas.');
                }
            } else {
                defaultModal('Não foram encontradas possíveis duplicatas de alunos no sistema.');
            }
        },
        error: function(xhr, status, error) {
            esconderLoading();
            console.error('Erro ao carregar duplicatas:', error);
            defaultModal('Erro ao carregar possíveis duplicatas. Tente novamente ou utilize a pesquisa manual.');
        }
    };

    getResources(options);
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
// FUNÇÕES PARA ACORDEON DOS GRUPOS
// ============================================

// Função para alternar o accordion
window.toggleAccordion = function(grupoId) {
    console.log('toggleAccordion chamado para:', grupoId);
    var content = document.getElementById('accordion-content-' + grupoId);
    var icone = document.getElementById('icone-' + grupoId);
    
    if (!content) {
        console.log('Elemento não encontrado:', 'accordion-content-' + grupoId);
        return;
    }
    
    if (content.style.display === 'block') {
        content.style.display = 'none';
        if (icone) icone.classList.remove('rotacionado');
        console.log('fechando grupo');
    } else {
        content.style.display = 'block';
        if (icone) icone.classList.add('rotacionado');
        console.log('abrindo grupo');
    }
};

// Função para visualizar dados do aluno (usada nos cards)
window.visualizarDadosAluno = function(codAluno, nomeAluno) {
    var url = '/module/Api/Aluno?oper=get&resource=dadosMatriculasHistoricosAlunos&aluno_id=' + codAluno;
    
    fetch(url)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            var html = '<h3>Dados do aluno: ' + nomeAluno + '</h3>';
            
            if (data.matriculas && data.matriculas.length > 0) {
                html += '<h4>Matrículas</h4><table class="tabela-grupo"><thead><tr><th>Ano</th><th>Escola</th><th>Curso</th><th>Série</th><th>Turma</th></tr></thead><tbody>';
                data.matriculas.forEach(function(m) {
                    html += '<tr><td>' + (m.ano || '') + '</td><td>' + (m.escola || '') + '</td><td>' + (m.curso || '') + '</td><td>' + (m.serie || '') + '</td><td>' + (m.turma || '') + '</td></tr>';
                });
                html += '</tbody><table>';
            }
            
            if (data.historicos && data.historicos.length > 0) {
                html += '<h4>Históricos</h4><table class="tabela-grupo"><thead><tr><th>Ano</th><th>Escola</th><th>Curso</th><th>Série</th><th>Situação</th></tr></thead><tbody>';
                data.historicos.forEach(function(h) {
                    html += '<tr><td>' + (h.ano || '') + '</td><td>' + (h.escola || '') + '</td><td>' + (h.curso || '') + '</td><td>' + (h.serie || '') + '</td><td>' + (h.situacao || '') + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            
            if (html === '<h3>Dados do aluno: ' + nomeAluno + '</h3>') {
                html += '<p>Nenhum dado encontrado.</p>';
            }
            
            makeDialog({
                content: html,
                title: 'Dados Escolares - ' + nomeAluno,
                width: '800px',
                buttons: [{
                    text: 'Fechar', 
                    click: function() { 
                        if (typeof $j !== 'undefined' && $j('#dialog-container').length) {
                            $j('#dialog-container').dialog('close');
                            $j('#dialog-container').dialog('destroy');
                            $j('#dialog-container').remove();
                        }
                        var dialogs = document.querySelectorAll('.ui-dialog');
                        for (var i = 0; i < dialogs.length; i++) {
                            dialogs[i].remove();
                        }
                    }
                }]
            });
        })
        .catch(function(error) {
            console.error('Erro:', error);
            alert('Erro ao carregar dados do aluno');
        });
};

console.log('Funções do acordeon carregadas com sucesso!');

// ============================================
// CARREGAMENTO AUTOMÁTICO AO INICIAR
// ============================================
$(document).ready(function() {
    setTimeout(function() {
        if (typeof carregarPossiveisDuplicatasAutomaticamente === 'function') {
            carregarPossiveisDuplicatasAutomaticamente();
        }
    }, 1000);
});