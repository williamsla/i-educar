(function () {
  var searchPath = '/module/Api/InstituicaoDocumentacao?oper=get&resource=getDocuments';
  var yearsPath = '/module/Api/InstituicaoDocumentacao?oper=get&resource=getYears';

  function setSelectLoadingState(selectId, message) {
    var select = document.getElementById(selectId);
    if (!select) {
      return;
    }

    select.length = 1;
    select.disabled = true;
    select.options[0].text = message;
  }

  function resetDocumentacaoSelect(selectId, message, disabled) {
    var select = document.getElementById(selectId);
    if (!select) {
      return;
    }

    select.length = 1;
    select.options[0].text = message;
    select.disabled = disabled;
  }

  function setRelatorioLoadingState() {
    setSelectLoadingState('relatorio', 'Carregando relatórios');
  }

  function resetRelatorioSelect(message) {
    resetDocumentacaoSelect('relatorio', message, false);
  }

  function resetAnoSelect(message) {
    resetDocumentacaoSelect('ano', message, false);
  }

  function populateAnoSelect(anos) {
    var selectAno = document.getElementById('ano');
    if (!selectAno) {
      return;
    }

    selectAno.length = 1;

    if (!anos || !anos.length) {
      resetAnoSelect('A instituição não possui documentos cadastrados');
      resetRelatorioSelect('Selecione');
      return;
    }

    selectAno.options[0].text = 'Selecione um ano';
    selectAno.disabled = false;

    for (var i = 0; i < anos.length; i++) {
      var option = document.createElement('option');
      option.text = anos[i];
      option.value = anos[i];
      selectAno.add(option);
    }

    resetRelatorioSelect('Selecione');
  }

  function populateRelatorioSelect(documentos) {
    var selectRelatorio = document.getElementById('relatorio');
    if (!selectRelatorio) {
      return;
    }

    selectRelatorio.length = 1;

    if (!documentos || !documentos.length) {
      resetRelatorioSelect('Não há relatórios para o ano selecionado');
      return;
    }

    selectRelatorio.options[0].text = 'Selecione um relatório';
    selectRelatorio.disabled = false;

    for (var i = 0; i < documentos.length; i++) {
      var option = document.createElement('option');
      option.text = documentos[i].titulo_documento;
      option.value = documentos[i].url_documento;
      selectRelatorio.add(option);
    }
  }

  function getAnos(instituicaoId) {
    var params = { instituicao_id: instituicaoId };

    $j.get(yearsPath, params)
      .done(function (data) {
        if (data && data.any_error_msg) {
          resetAnoSelect('Não foi possível carregar os anos');
          resetRelatorioSelect('Selecione');
          return;
        }

        populateAnoSelect(data ? data.anos : []);
      })
      .fail(function () {
        resetAnoSelect('Não foi possível carregar os anos');
        resetRelatorioSelect('Selecione');
      });
  }

  function getDocumento(instituicaoId, ano) {
    var params = { instituicao_id: instituicaoId, ano: ano };

    $j.get(searchPath, params)
      .done(function (data) {
        if (data && data.any_error_msg) {
          resetRelatorioSelect('Não foi possível carregar os relatórios');
          return;
        }

        populateRelatorioSelect(data ? data.documentos : []);
      })
      .fail(function () {
        resetRelatorioSelect('Não foi possível carregar os relatórios');
      });
  }

  function loadInstituicaoDocumentos(instituicaoId) {
    resetAnoSelect('Selecione');
    resetRelatorioSelect('Selecione');

    var selectAno = document.getElementById('ano');
    var selectRelatorio = document.getElementById('relatorio');

    if (selectAno) {
      selectAno.disabled = true;
    }

    if (selectRelatorio) {
      selectRelatorio.disabled = true;
    }

    if (instituicaoId != '') {
      setSelectLoadingState('ano', 'Carregando anos letivos');
      getAnos(instituicaoId);
    }
  }

  $j(function () {
    var $instituicao = $j('#ref_cod_instituicao');
    var $ano = $j('#ano');
    var $relatorio = $j('#relatorio');
    var $btnEnviar = $j('#btn_enviar');

    if (!$instituicao.length || !$ano.length || !$relatorio.length) {
      return;
    }

    if ($btnEnviar.length) {
      $btnEnviar.hide();
    }

    $instituicao.on('change', function () {
      if (this.selectedIndex !== undefined && this.selectedIndex === 0) {
        resetAnoSelect('Selecione');
        resetRelatorioSelect('Selecione');
        $ano.prop('disabled', true);
        $relatorio.prop('disabled', true);
        return;
      }

      loadInstituicaoDocumentos($instituicao.val());
    });

    $ano.on('change', function () {
      var instituicaoId = $instituicao.val();

      if (this.selectedIndex !== 0 && instituicaoId != '') {
        setRelatorioLoadingState();
        getDocumento(instituicaoId, this.value);
        return;
      }

      resetRelatorioSelect('Selecione');
      $relatorio.prop('disabled', true);
    });

    $relatorio.on('change', function () {
      if (this.selectedIndex !== 0) {
        window.open(linkUrlPrivada(this.value), '_blank');
      }
    });

    if ($instituicao.val() != '') {
      loadInstituicaoDocumentos($instituicao.val());
    }
  });
})();
