(function($){
  $(document).ready(function(){
    var $instituicaoField = getElementFor('instituicao');
    var $escolaField = getElementFor('escola');

    $escolaField.chosen({
      no_results_text: "Sem resultados para ",
      placeholder_text_single: "Selecione uma escola",
      search_contains: true,
    });

    var atualizarCursosPorAno = false;

    var handleGetEscolas = function(response) {
      var valorSelecionado = $escolaField.val();

      $escolaField.empty();
      $escolaField.trigger("chosen:updated");

      var options = '<option/>';

      $j.each(response['options'], function(id, value) {
        if (id.indexOf && id.substr && id.indexOf('__') == 0) {
          id = id.substr(2);
        }
        options += '<option value="' + id + '"> ' + value + '</option>';
      });

      $escolaField.append(options);

      var escolaPermanece = valorSelecionado && $escolaField.find('option[value="' + valorSelecionado + '"]').length;

      if (escolaPermanece) {
        $escolaField.val(valorSelecionado);
      }

      $escolaField.trigger("chosen:updated");

      if ((valorSelecionado && !escolaPermanece) || (atualizarCursosPorAno && escolaPermanece)) {
        $escolaField.trigger('change');
      }

      atualizarCursosPorAno = false;
    }

    var anoLetivoSelecionado = function() {
      if ($escolaField.attr('filtra_ano_letivo') !== 'true') {
        return null;
      }

      var $anoField = getElementFor('ano');
      if (!$anoField || !$anoField.length || !$anoField.val()) {
        return null;
      }

      return $anoField.val();
    }

    var updateEscolas = function() {
      let resource = 'escolas-para-selecao';
      if ($escolaField.attr('escola_sem_filtro_por_usuario') === 'true') {
        resource = 'escolas-para-selecao-sem-filtro-por-usuario';
      }
      var additionalVars = {
        instituicao : $instituicaoField.val()
      };
      var ano = anoLetivoSelecionado();
      if (ano) {
        additionalVars.ano = ano;
      }
      var options = {
        url      : getResourceUrlBuilder.buildUrl('/module/Api/escola', resource, additionalVars),
        dataType : 'json',
        data     : {},
        success  : handleGetEscolas,
      };
      getResource(options);
    }

    $instituicaoField.change(updateEscolas);

    if ($escolaField.attr('filtra_ano_letivo') === 'true') {
      var $anoField = getElementFor('ano');
      if ($anoField && $anoField.length) {
        $anoField.on('change', function() {
          atualizarCursosPorAno = true;
          updateEscolas();
        });
      }
    }

  }); // ready
})(jQuery);
