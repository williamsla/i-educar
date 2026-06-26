var searchPath = '/module/Api/InstituicaoDocumentacao?oper=get&resource=getDocuments';

function setRelatorioLoadingState() {
  var selectRelatorio = document.getElementById('relatorio');
  selectRelatorio.length = 1;
  selectRelatorio.disabled = true;
  selectRelatorio.options[0].text = 'Carregando relatórios';
}

function resetRelatorioSelect(message) {
  var selectRelatorio = document.getElementById('relatorio');
  selectRelatorio.length = 1;
  selectRelatorio.options[0].text = message;
  selectRelatorio.disabled = false;
}

function populateRelatorioSelect(documentos) {
  var selectRelatorio = document.getElementById('relatorio');
  selectRelatorio.length = 1;

  if (!documentos || !documentos.length) {
    resetRelatorioSelect('A instituição não possui relatórios cadastrados');
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

function getDocumento(instituicaoId) {
  var params = { instituicao_id: instituicaoId };

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

var instituicaoId = document.getElementById('ref_cod_instituicao').value;
if (instituicaoId != '') {
  setRelatorioLoadingState();
  getDocumento(instituicaoId);
}

document.getElementById('btn_enviar').style.display = 'none';

document.getElementById('ref_cod_instituicao').onchange = function () {
  var selectRelatorio = document.getElementById('relatorio');

  if (this.selectedIndex !== 0) {
    setRelatorioLoadingState();
    getDocumento(document.getElementById('ref_cod_instituicao').value);
  } else {
    resetRelatorioSelect('Selecione');
  }
};

document.getElementById('relatorio').onchange = function () {
  if (this.selectedIndex !== 0) {
    window.open(linkUrlPrivada(this.value), '_blank');
  }
};
