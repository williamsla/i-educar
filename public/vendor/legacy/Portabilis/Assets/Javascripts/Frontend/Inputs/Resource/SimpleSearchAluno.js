var simpleSearchAlunoOptions = {

  params : { 
    escola_id : function() { 
      return $j('#ref_cod_escola').val() 
    },
    turma_id : function() { 
      return $j('#ref_cod_turma').val() 
    }

  },

  canSearch : function() { 

    if (! $j('#ref_cod_escola').val()) {
      alert('Selecione uma escola.');
      return false;
    }
    
    return true;
 }
};
