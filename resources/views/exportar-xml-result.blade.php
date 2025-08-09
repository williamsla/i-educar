<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Arquivos Gerados</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .box { background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ccc; max-width: 600px; margin: auto; }
        a.button { display: inline-block; margin: 10px 0; padding: 10px 15px; background: #007bff; color: #fff; text-decoration: none; border-radius: 5px; }
        a.button:hover { background: #0056b3; }
    </style>
</head>
<body>

    <div class="box">
        <h2>Arquivos gerados com sucesso</h2>

        <p>✅ O arquivo de remessa com os dados foi gerado:</p>
        <a class="button" href="{{ $zipUrl }}" download>Baixar remessa para TCE</a>

        <p>⚠️ Avisos:</p>
        <a href="{{ $txtUrl }}" download>Baixar avisos</a>
        
        <br><br>
        <hr>
        <a href="{{ url()->previous() }}">⬅️ Voltar</a>
    </div>

</body>
</html>
