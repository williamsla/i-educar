(function($){
  $(document).ready(function() {
    let $instituicaoField = getElementFor('instituicao');
    let $escolaField = getElementFor('escola');
    let $cursoField = getElementFor('curso');
    let $ano = getElementFor('ano');

    let updateCursos = function() {
      const currentCursoId = $cursoField.val();
      resetSelect($cursoField);

      const onCursosLoaded = function(response) {
        let selectOptions = jsonResourcesToSelectOptions(response['options']);
        updateSelect($cursoField, selectOptions, "Selecione um curso");
        if (currentCursoId !== '' && currentCursoId != null) {
          $cursoField.val(String(currentCursoId));
        }
        $cursoField.change();
      };

      if ($instituicaoField.val() && $escolaField.val() && $escolaField.is(':enabled')) {
        $cursoField.children().first().html('Aguarde carregando...');
        let urlForGetCursos = getResourceUrlBuilder.buildUrl('/module/DynamicInput/Curso', 'cursos', {
          escola_id: $escolaField.val(),
          instituicao_id: $instituicaoField.val(),
          ano: ($ano.val() && $ano.val() != "NaN" ? $ano.val() : ''),
          curso_id: currentCursoId || ''
        });

        let options = {
          url: urlForGetCursos,
          dataType: 'json',
          success: onCursosLoaded
        };

        getResources(options);
      } else if ($instituicaoField.val()) {
        $cursoField.children().first().html('Aguarde carregando...');
        let urlForGetCursos = getResourceUrlBuilder.buildUrl('/module/DynamicInput/Curso', 'cursos', {
          instituicao_id: $instituicaoField.val(),
          ano: ($ano.val() && $ano.val() != "NaN" ? $ano.val() : ''),
          curso_id: currentCursoId || ''
        });

        let options = {
          url: urlForGetCursos,
          dataType: 'json',
          success: onCursosLoaded
        };

        getResources(options);
      } else {
        $cursoField.change();
      }
    };

    $instituicaoField.change(updateCursos);
    $escolaField.change(updateCursos);
    $ano.change(function () {

      // Evita que o select "curso" tenha seus valores limpos ao alterar o campo ano
      if ($cursoField.attr('data-refresh-ano') === 'false') {
        return;
      }

      updateCursos();
    });

    // Carrega os cursos automaticamente ao carregar a página (quando instituição/escola já estiverem preenchidos)
    updateCursos();
  });
})(jQuery);
