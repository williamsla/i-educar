<?php

if ($_FILES) {
    foreach ($_FILES as $file) {
        if (!empty($file['name'])) {
            $objFile = new FileControllerPdf($file, 2 * 1024 * 1024, ['pdf', 'docx']);
            if ($objFile->validateFile()) {
                $caminho = $objFile->sendFile();
                if ($caminho != '') {
                    $data = ['file_url' => $caminho];
                } else {
                    $data = ['error' => $objFile->getErrorMessage()];
                }
            } else {
                $data = ['error' => $objFile->getErrorMessage()];
            }
        } else {
            $data = ['error' => 'Nenhum arquivo enviado.'];
        }
    }
} else {
    $data = ['error' => 'Arquivo invÃ¡lido.'];
}

echo json_encode($data);
