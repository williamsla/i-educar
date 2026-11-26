$j(document).ready(function(){
	let codigoEscola = document.getElementById('codigoEscola').value;
	let nomeEscola = document.getElementById('escola').value;
	let numeroSequencial = document.getElementById('numeroSequencial').value;
  const escola_em_outro_municipio = $j('#escola_em_outro_municipio');
  const cidade_escola = $j('#escola_cidade');
  const estado_escola = $j('#escola_uf');
  const pais_escola = $j('#idpais');

	//Quando for novo cadastro
	if(codigoEscola === '' && nomeEscola === '' && numeroSequencial === ''){
		$j('#ref_cod_escola').val("");
		$j('#escola').closest('tr').hide();
    cidade_escola.closest('tr').hide();
    estado_escola.closest('tr').hide();
    pais_escola.closest('tr').hide();
	}
	//Quando for edição e for outra
	else if(codigoEscola === '' && numeroSequencial !== ''){
    escola_em_outro_municipio.prop('checked', true);
    $j('#ref_cod_instituicao').closest('tr').hide();
    $j('#ref_cod_escola').closest('tr').hide();
		$j('#ref_cod_escola').val('outra');
		$j('#escola').closest('tr').show();
		$j('#escola').val(nomeEscola);
	}
	//Quando for edição e não for outra
	else{
    cidade_escola.closest('tr').hide();
    estado_escola.closest('tr').hide();
    pais_escola.closest('tr').hide();
    $j('#ref_cod_escola').makeRequired();
    $j('#ref_cod_instituicao').makeRequired();
		$j('#ref_cod_escola').val(codigoEscola);
		$j('#escola').val('');
		$j('#escola').closest('tr').hide();
	}

	function habilitaPosicao(){
		if ($j('#dependencia')[0].checked) {
			$j('#posicao').closest('tr').hide();
		} else{
			$j('#posicao').closest('tr').show();
		}
	}

	$j("#dependencia").click(function(){
		habilitaPosicao();
	});

	habilitaPosicao();

    escola_em_outro_municipio.change(function () {
      if (escola_em_outro_municipio.is(':checked')) {
        $j('#ref_cod_escola').val('outra');
        $j('#escola').closest('tr').show();
        $j('#ref_cod_instituicao').closest('tr').hide();
        $j('#ref_cod_escola').closest('tr').hide();
        cidade_escola.closest('tr').show();
        estado_escola.closest('tr').show();
        pais_escola.closest('tr').show();
        pais_escola.val('');
        estado_escola.val('');
        cidade_escola.val('');
        cidade_escola.makeRequired();
        estado_escola.makeRequired();
        pais_escola.makeRequired();
        $j('#ref_cod_instituicao').makeUnrequired();
        $j('#ref_cod_escola').makeUnrequired();
      } else {
        $j('#escola').closest('tr').hide();
        cidade_escola.closest('tr').hide();
        estado_escola.closest('tr').hide();
        pais_escola.closest('tr').hide();
        pais_escola.val('');
        estado_escola.val('');
        cidade_escola.val('');
        cidade_escola.makeUnrequired();
        estado_escola.makeUnrequired();
        pais_escola.makeUnrequired();
        $j('#ref_cod_instituicao').closest('tr').show();
        $j('#ref_cod_escola').closest('tr').show();
        $j('#ref_cod_instituicao').makeRequired();
        $j('#ref_cod_escola').makeRequired();
        }
      });

    $j('#ref_cod_escola').change(function () {
      const cod_escola = $j('#ref_cod_escola').val();
      let url = getResourceUrlBuilder.buildUrl('/module/Api/Escola',
        'endereco-escola',
        { escola_id : cod_escola }
      );
      let options = {
        url      : url,
        dataType : 'json',
        success  : function (response) {
          pais_escola.val(response.country_id);
          estado_escola.val(response.state_abbreviation);
          cidade_escola.val(response.city);
        }
      };
      getResources(options);
    });
});

document.getElementById('cb_faltas_globalizadas').onclick =function()
{
	setVisibility('tr_faltas_globalizadas',this.checked);
	this.setAttribute('value', this.checked ? 'on' : '');
}

document.getElementById('cb_faltas_globalizadas').onclick();



document.getElementById('idpais').onchange = function() {
	let campoPais = document.getElementById( 'idpais' ).value;
	let campoEstado = document.getElementById( 'escola_uf' );

	campoEstado.length = 1;
	campoEstado.disabled = true;
	campoEstado.options[0] = new Option( 'Carregando estados', '', false, false );

	let xml1 = new ajax(getEstado_XML);
	strURL = "public_uf_xml.php?pais="+campoPais+"&abbreviation=true";
	xml1.envia(strURL);
}

function getEstado_XML(xml)
{


	let campoEstado = document.getElementById( 'escola_uf' );


	let estados = xml.getElementsByTagName( "estado" );

	campoEstado.length = 1;
	campoEstado.options[0] = new Option( 'Selecione um estado', '', false, false );
	for ( let j = 0; j < estados.length; j++ )
	{

		campoEstado.options[campoEstado.options.length] = new Option( estados[j].firstChild.nodeValue, estados[j].getAttribute('id'), false, false );

	}
	if ( campoEstado.length == 1 ) {
		campoEstado.options[0] = new Option( 'País não possui estados', '', false, false );
	}

	campoEstado.disabled = false;
}


// autocomplete disciplina fields

let handleSelect = function(event, ui){
	$j(event.target).val(ui.item.label);
	return false;
};

let search = function(request, response) {
	let searchPath = '/module/Api/ComponenteCurricular?oper=get&resource=componente_curricular-search';
	let params     = { query : request.term };

	$j.get(searchPath, params, function(dataResponse) {
		simpleSearch.handleSearch(dataResponse, response);
	});
};

function setAutoComplete() {
	$j.each($j('input[id^="nm_disciplina"]'), function(index, field) {

		$j(field).autocomplete({
			source    : search,
			select    : handleSelect,
			minLength : 1,
			autoFocus : true
		});

	});
}

setAutoComplete();

let submitForm = function(event) {
	let $frequenciaField  	  = $j('#frequencia');
	let frequencia        	  = $frequenciaField.val();
	let frequenciaObrigatoria = $frequenciaField.hasClass('obrigatorio');

if (frequencia.indexOf(',') > -1){
	frequencia = frequencia.replace('.', '').replace(',', '.');
}

if((frequencia.trim() == '')&&(!frequenciaObrigatoria)){
	formUtils.submit();
}else{
	if (validatesIfNumericValueIsInRange(frequencia, $frequenciaField, 0, 100))
    	formUtils.submit();
	}
}

// bind events

let $addDisciplinaButton = $j('#btn_add_tab_add_1');

$addDisciplinaButton.click(function(){
	setAutoComplete();
});


// submit button

let $submitButton = $j('#btn_enviar');

$submitButton.removeAttr('onclick');
$submitButton.click(validaSubmit);

  function validaSubmit() {
    var canSubmit = validationUtils.validatesFields(true);

    if (!canSubmit) {
        return false;
    }

    if (!$j('#escola_em_outro_municipio').is(':checked')) {
      if ($j('#ref_cod_instituicao').closest("select").val() === '') {
        return alert('É necessário informar a instituição');
      }
      if ($j('#ref_cod_escola').closest("select").val() === '') {
        return alert('É necessário informar a escola');
      }
    }
    acao();
  };

(function () {
  const SEM_ORDEM = 99999;

  if (!$j('#tr_notas').length || $j('#tr_palavra_notas').length) {
    return;
  }

  $j('#tr_notas').before(
    '<tr id="tr_palavra_notas" class="formmdtd">' +
      '<td colspan="2" style="text-align:left; padding:12px 16px;">' +
        '<button type="button" id="palavra_notas_cabecalho" aria-expanded="false" aria-controls="palavra_notas_corpo" title="Clique para ver as opções" style="background:none; border:0; padding:0; margin:0; font:inherit; font-weight:bold; cursor:pointer; color:inherit; display:inline-flex; align-items:center; gap:8px;">' +
          '<span id="palavra_notas_icone" aria-hidden="true" style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:#47728f; color:#fff; flex:0 0 22px; transition:transform .15s ease;">' +
            '<svg width="14" height="14" viewBox="0 0 14 14">' +
              '<path d="M3 5.5 L7 9.5 L11 5.5" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>' +
            '</svg>' +
          '</span>' +
          'Inserir uma palavra no lugar das notas' +
        '</button>' +
        '<div id="palavra_notas_corpo" style="display:none; margin-top:8px;">' +
          '<div style="margin-bottom:8px; color:#47728f;">A palavra é gravada na coluna Nota, uma letra por disciplina, na ordem de apresentação do componente curricular. A coluna Ordem recebe esse número.</div>' +
          '<div>' +
            '<input type="text" id="palavra_notas" maxlength="80" size="28" autocomplete="off" placeholder="Ex.: PROSSEGUE" style="margin-right:8px;">' +
            '<button type="button" id="btn_distribuir_palavra" style="margin-right:6px;">Distribuir letras</button>' +
            '<button type="button" id="btn_limpar_palavra">Limpar notas</button>' +
          '</div>' +
          '<div id="palavra_notas_previa" style="max-height:240px; overflow:auto; margin-top:8px;"></div>' +
        '</div>' +
      '</td>' +
    '</tr>'
  );

  function alternarPalavraNotas() {
    const $corpo = $j('#palavra_notas_corpo');
    const aberto = $corpo.is(':visible');

    $corpo.toggle(!aberto);
    $j('#palavra_notas_cabecalho').attr('aria-expanded', aberto ? 'false' : 'true');
    $j('#palavra_notas_icone').css('transform', aberto ? 'none' : 'rotate(180deg)');
  }

  function normalizarNome(nome) {
    return (nome || '')
      .trim()
      .replace(/\s+/g, ' ')
      .toLocaleUpperCase('pt-BR')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function mapaComponentes() {
    const dados = window.componentesOrdenamentoHistorico || {};
    const lista = dados.componentes || [];
    const mapa = {};

    lista.forEach(function (componente) {
      const chave = normalizarNome(componente.nome);
      if (!chave) {
        return;
      }

      const ordem = parseInt(componente.ordenamento, 10);
      const ordemValida = Number.isFinite(ordem) ? ordem : SEM_ORDEM;
      const atual = mapa[chave];

      if (atual === undefined) {
        mapa[chave] = ordemValida;
        return;
      }

      const atualSemOrdem = atual === SEM_ORDEM;
      const novaSemOrdem = ordemValida === SEM_ORDEM;

      if (atualSemOrdem && !novaSemOrdem) {
        mapa[chave] = ordemValida;
      } else if (!atualSemOrdem && !novaSemOrdem && ordemValida < atual) {
        mapa[chave] = ordemValida;
      }
    });

    return mapa;
  }

  function ordemDaDisciplina(nome, mapa) {
    const chave = normalizarNome(nome);

    if (!Object.prototype.hasOwnProperty.call(mapa, chave)) {
      return { ordem: SEM_ORDEM, encontrada: false };
    }

    return { ordem: mapa[chave], encontrada: true };
  }

  function letrasDaPalavra() {
    return Array.from(($j('#palavra_notas').val() || '').trim());
  }

  function linhasDisciplina() {
    const linhas = [];

    $j('#notas tr.tr_notas').each(function () {
      const $tr = $j(this);
      const $nome = $tr.find('input[id^="nm_disciplina"]');
      const $nota = $tr.find('input[id^="nota"]');
      const $ordem = $tr.find('input[id^="ordenamento"]');

      if (!$nome.length || !$nota.length) {
        return;
      }

      const nome = $nome.val() || '';

      if (!nome.trim()) {
        return;
      }

      linhas.push({
        nome: nome,
        nota: $nota,
        ordem: $ordem
      });
    });

    return linhas;
  }

  function montarDistribuicao() {
    const mapa = mapaComponentes();
    const linhas = linhasDisciplina();

    linhas.forEach(function (linha) {
      const info = ordemDaDisciplina(linha.nome, mapa);
      linha.ordemComponente = info.ordem;
      linha.encontrada = info.encontrada;
      linha.letra = '';
      linha.recebe = false;
    });

    linhas.sort(function (a, b) {
      if (a.ordemComponente !== b.ordemComponente) {
        return a.ordemComponente - b.ordemComponente;
      }

      return normalizarNome(a.nome).localeCompare(normalizarNome(b.nome), 'pt-BR');
    });

    const letras = letrasDaPalavra();
    const receptores = linhas;
    let erro = '';

    if (!letras.length) {
      erro = 'Informe a palavra.';
    } else if (!linhas.length) {
      erro = 'Inclua as disciplinas antes de distribuir.';
    } else if (letras.length > receptores.length) {
      erro = letras.join('') + ' tem ' + letras.length + ' letras e há ' + receptores.length + ' disciplinas.';
    }

    if (!erro) {
      receptores.forEach(function (linha, indice) {
        linha.recebe = true;
        linha.letra = letras[indice] || '';
      });
    } else if (letras.length && letras.length > receptores.length) {
      receptores.forEach(function (linha, indice) {
        linha.letra = letras[indice] || '';
      });
    }

    return {
      linhas: linhas,
      letras: letras,
      receptores: receptores,
      erro: erro,
      buraco: temBuraco(linhas)
    };
  }

  function temBuraco(linhas) {
    let viuLetra = false;
    let viuVazioDepois = false;

    for (let i = 0; i < linhas.length; i++) {
      if (linhas[i].letra) {
        if (viuVazioDepois) {
          return true;
        }
        viuLetra = true;
      } else if (viuLetra) {
        viuVazioDepois = true;
      }
    }

    return false;
  }

  function bloquearNotas() {
    const bloquear = ($j('#palavra_notas').val() || '').trim() !== '';

    $j('#notas input[id^="nota"]').each(function () {
      const $nota = $j(this);

      $nota.prop('readonly', bloquear);
      $nota.css({
        backgroundColor: bloquear ? '#eef2f5' : '',
        cursor: bloquear ? 'not-allowed' : ''
      });

      if (bloquear) {
        $nota.attr('title', 'A nota vem da palavra informada acima.');
      } else {
        $nota.removeAttr('title');
      }
    });
  }

  function atualizarPrevia() {
    bloquearNotas();

    const distribuicao = montarDistribuicao();
    const $previa = $j('#palavra_notas_previa');
    const $lista = $j('<div>');
    if (!distribuicao.linhas.length) {
      $previa.text('Inclua as disciplinas abaixo para ver a ordem da impressão.');
      return;
    }

    distribuicao.linhas.forEach(function (linha) {
      const $item = $j('<div>').css({ padding: '2px 0' });
      const letra = linha.letra;

      $item.append($j('<strong>').css({ display: 'inline-block', width: '1.2em' }).text(letra || '·'));
      $item.append(document.createTextNode(' ' + linha.nome));

      if (!linha.encontrada) {
        $item.append($j('<span>').css({ color: '#a15c07' }).text(' — não encontrada no componente curricular'));
      } else if (linha.ordemComponente === SEM_ORDEM) {
        $item.append($j('<span>').css({ color: '#666' }).text(' — sem ordem de apresentação'));
      }

      $lista.append($item);
    });

    const $resumo = $j('<div>').css({ marginTop: '6px' });

    if (distribuicao.erro && distribuicao.letras.length) {
      $resumo.css({ color: '#a12622' }).text(distribuicao.erro);
    } else if (distribuicao.letras.length) {
      const sobra = distribuicao.receptores.length - distribuicao.letras.length;
      let texto = distribuicao.letras.length + ' letras em ' + distribuicao.receptores.length + ' disciplinas.';

      if (sobra > 0) {
        texto += ' Sobram ' + sobra + ' linhas em branco.';
      }

      $resumo.text(texto);

      if (distribuicao.buraco) {
        $resumo.append($j('<div>').css({ color: '#a15c07', marginTop: '4px' }).text('Há disciplina sem letra no meio da palavra. Essa célula vazia aparece no relatório.'));
      }
    } else {
      $resumo.css({ color: '#47728f' }).text('Ordem da impressão, pela ordem de apresentação do componente.');
    }

    $previa.empty().append($lista).append($resumo);
  }

  function distribuir() {
    const distribuicao = montarDistribuicao();

    if (distribuicao.erro) {
      alert(distribuicao.erro);
      atualizarPrevia();
      return;
    }

    const substitui = distribuicao.linhas.some(function (linha) {
      if (!linha.recebe) {
        return false;
      }

      const atual = (linha.nota.val() || '').trim();

      return atual !== '' && atual !== linha.letra;
    });

    if (substitui && !confirm('Algumas notas já preenchidas serão substituídas. Continuar?')) {
      return;
    }

    $j('#notas input[data-letra-distribuida]').removeAttr('data-letra-distribuida');

    distribuicao.linhas.forEach(function (linha) {
      if (linha.ordem.length) {
        linha.ordem.val(String(linha.ordemComponente));
      }

      if (!linha.recebe) {
        return;
      }

      linha.nota.val(linha.letra);
      linha.nota.attr('data-letra-distribuida', '1');
    });

    atualizarPrevia();
  }

  function limparNotas() {
    const marcadas = [];

    $j('#notas input[data-letra-distribuida="1"]').each(function () {
      marcadas.push($j(this));
    });

    if (!marcadas.length) {
      linhasDisciplina().forEach(function (linha) {
        if (Array.from((linha.nota.val() || '').trim()).length === 1) {
          marcadas.push(linha.nota);
        }
      });
    }

    if (!marcadas.length) {
      alert('Não há notas de uma letra para limpar.');
      return;
    }

    if (!confirm('Apagar a nota de ' + marcadas.length + ' disciplina(s)?')) {
      return;
    }

    marcadas.forEach(function ($nota) {
      $nota.val('').removeAttr('data-letra-distribuida');
    });

    atualizarPrevia();
  }

  function reconstruirPalavra() {
    const mapa = mapaComponentes();
    const linhas = linhasDisciplina();

    if (linhas.length < 2) {
      return;
    }

    const temNotaLonga = linhas.some(function (linha) {
      return Array.from((linha.nota.val() || '').trim()).length > 1;
    });

    if (temNotaLonga) {
      return;
    }

    linhas.forEach(function (linha) {
      const info = ordemDaDisciplina(linha.nome, mapa);
      linha.ordemComponente = info.ordem;
    });

    linhas.sort(function (a, b) {
      if (a.ordemComponente !== b.ordemComponente) {
        return a.ordemComponente - b.ordemComponente;
      }

      return normalizarNome(a.nome).localeCompare(normalizarNome(b.nome), 'pt-BR');
    });

    const letras = linhas
      .map(function (linha) { return (linha.nota.val() || '').trim(); })
      .filter(function (nota) { return Array.from(nota).length === 1; });

    if (letras.length < 2) {
      return;
    }

    $j('#palavra_notas').val(letras.join(''));
  }

  function carregarComponentes(instituicaoId, reconstruir) {
    function concluir() {
      if (reconstruir) {
        reconstruirPalavra();
      }
      atualizarPrevia();
    }

    if (!instituicaoId || typeof getResourceUrlBuilder === 'undefined') {
      concluir();
      return;
    }

    const atual = window.componentesOrdenamentoHistorico || {};

    if (String(atual.instituicaoId || '') === String(instituicaoId) && (atual.componentes || []).length) {
      concluir();
      return;
    }

    const url = getResourceUrlBuilder.buildUrl(
      '/module/Api/ComponenteCurricular',
      'componentes-curriculares',
      { instituicao_id: instituicaoId }
    );

    atualizarPrevia();

    $j.get(url, function (resposta) {
      if (resposta && Array.isArray(resposta.disciplinas)) {
        window.componentesOrdenamentoHistorico = {
          instituicaoId: String(instituicaoId),
          componentes: resposta.disciplinas
        };
      }
    }, 'json').always(concluir);
  }

  $j('#palavra_notas_cabecalho').click(alternarPalavraNotas);
  $j('#btn_distribuir_palavra').click(distribuir);
  $j('#btn_limpar_palavra').click(limparNotas);
  $j('#palavra_notas').on('input change', atualizarPrevia);
  $j('#notas').on('input change', 'input, select', atualizarPrevia);
  $j('#ref_cod_instituicao').on('change', function () {
    carregarComponentes($j(this).val(), false);
  });

  if (window.tab_add_1) {
    const depoisDeAdicionar = tab_add_1.afterAddRow.bind(tab_add_1);
    const depoisDeRemover = tab_add_1.afterRemoveRow.bind(tab_add_1);

    tab_add_1.afterAddRow = function () {
      depoisDeAdicionar();
      atualizarPrevia();
    };

    tab_add_1.afterRemoveRow = function () {
      depoisDeRemover();
      atualizarPrevia();
    };
  }

  carregarComponentes($j('#ref_cod_instituicao').val(), true);
})();

