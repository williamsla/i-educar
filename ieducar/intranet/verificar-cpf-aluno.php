<?php
// /intranet/verificar-cpf-aluno.php
require_once 'include/clsBanco.inc.php';
require_once 'include/clsBase.inc.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

try {
    $codAluno = $_GET['cod_aluno'] ?? null;

    if (!$codAluno) {
        echo json_encode(['cpf_valido' => false, 'error' => 'Código do aluno não informado']);
        exit;
    }

    $db = new clsBanco();
    
    // Busca o CPF do aluno
    $sql = "SELECT f.cpf 
            FROM pmieducar.aluno a
            INNER JOIN cadastro.fisica f ON f.idpes = a.ref_idpes
            WHERE a.cod_aluno = " . (int)$codAluno;
    
    $db->Consulta($sql);
    
    if ($db->ProximoRegistro()) {
        $cpf = $db->Campo('cpf');
        
        // Verifica se o CPF é válido (não nulo, não zero, e tem 11 dígitos)
        $cpfValido = false;
        if ($cpf && $cpf != '0' && $cpf != '00000000000') {
            $cpfString = str_pad((string)$cpf, 11, '0', STR_PAD_LEFT);
            if (strlen($cpfString) === 11 && $cpfString !== '00000000000') {
                $cpfValido = true;
            }
        }
        
        echo json_encode([
            'cpf_valido' => $cpfValido,
            'cpf_original' => $cpf,
            'cpf_formatado' => $cpfValido ? substr($cpfString, 0, 3) . '.' . substr($cpfString, 3, 3) . '.' . substr($cpfString, 6, 3) . '-' . substr($cpfString, 9, 2) : '000.000.000-00'
        ]);
    } else {
        echo json_encode(['cpf_valido' => false, 'error' => 'Aluno não encontrado']);
    }
    
} catch (Exception $e) {
    error_log('Erro em verificar-cpf-aluno.php: ' . $e->getMessage());
    echo json_encode(['cpf_valido' => false, 'error' => $e->getMessage()]);
}
?>