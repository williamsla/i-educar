<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Liberation Sans, Arial, sans-serif;
            font-size: 11pt;
            color: #222;
            margin: 0;
            padding: 16px 24px 32px;
            line-height: 1.4;
        }
        h1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0 0 8px;
            text-align: center;
        }
        .meta {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #ccc;
        }
        .meta p { margin: 4px 0; }
        .label { font-weight: bold; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #e8e8e8;
            font-weight: bold;
        }
        tr:nth-child(even) td { background: #f9f9f9; }
        .no-print {
            margin-bottom: 16px;
        }
        .no-print button {
            padding: 8px 16px;
            font-size: 12pt;
            cursor: pointer;
        }
        .no-print p {
            font-size: 10pt;
            color: #555;
            margin: 8px 0 0;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 12mm; }
            @page { size: A4; margin: 15mm; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Imprimir / Salvar em PDF</button>
    </div>

    <h1>{{ $titulo }}</h1>

    <div class="meta">
        <p><span class="label">Instituição:</span> {{ $instituicao }}</p>
        <p><span class="label">Data e hora da verificação:</span> {{ $verificado_em->format('d/m/Y \à\s H:i') }}</p>
        <p><span class="label">Ano letivo considerado:</span> {{ $ano_letivo }}</p>
        <p><span class="label">Total de CPF(s) extraídos do relatório eSUS:</span> {{ $cpfs_extraidos }}</p>
        <p><span class="label">Cidadãos sem matrícula ativa neste ano:</span> {{ count($itens) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 22%;">CPF</th>
                <th style="width: 48%;">Nome completo</th>
                <th style="width: 18%;">Data de nascimento</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($itens as $row)
                <tr>
                    <td>{{ $row['cpf'] ?? '' }}</td>
                    <td>{{ $row['nome'] ?? '—' }}</td>
                    <td>{{ $row['data_nascimento'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
