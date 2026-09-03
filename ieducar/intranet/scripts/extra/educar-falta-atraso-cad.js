var obj_tipo = document.getElementById('tipo');
var justificada = document.getElementById('justificada');

obj_tipo.onchange = function () {
  var isAtraso = document.getElementById('tipo').value == 1;
  setVisibility('tr_qtd_horas', isAtraso);
  setVisibility('tr_qtd_min', isAtraso);
}

setVisibility('tr_file', false);
justificada.onchange = function () {
  if (document.getElementById('justificada').value != '' && document.getElementById('justificada').value == 0) {
    setVisibility('tr_file', true);
  } else if (document.getElementById('justificada').value != '' &&  document.getElementById('justificada').value == 1) {
    setVisibility('tr_file', false);
  }
}

obj_tipo.dispatchEvent(new Event('change'));
justificada.dispatchEvent(new Event('change'));

