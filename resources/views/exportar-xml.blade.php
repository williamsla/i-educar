@extends('layout.blank')

@section('content')
<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .box {
        background: #f9f9f9;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #ccc;
        max-width: 600px;
        margin: auto;
    }
    label {
        display: block;
        margin-top: 15px;
        font-weight: bold;
    }
    select, input[type="number"] {
        width: 100%;
        padding: 8px;
        margin-top: 5px;
        border-radius: 4px;
        border: 1px solid #ccc;
    }
    button {
        margin-top: 20px;
        padding: 10px 20px;
        background: #28a745;
        color: #fff;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    button:hover {
        background: #218838;
    }
    .error {
        background: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 4px;
        margin-top: 15px;
        border: 1px solid #f5c6cb;
    }
</style>

<div class="box">
    <h2>Exportar Remessa para TCE</h2>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form action="{{ url('/exportar-xml') }}" method="GET">
        <label for="modelo">Escolha o modelo XML:</label>
        <select name="modelo" id="modelo" required>
            <option value="sagres">SAGRES TCE-SE</option>
            <option value="siap">SIAP TCE-AL</option>
        </select>

        <label for="ano">Ano de Referência:</label>
        <input type="number" name="ano" id="ano" required value="{{ now()->year }}">

        <label for="mes">Mês de Referência:</label>
        <select name="mes" id="mes" required>
            @foreach(range(1,12) as $mes)
                <option value="{{ $mes }}" {{ now()->month == $mes ? 'selected' : '' }}>
                    {{ $mes < 10 ? '0'.$mes : $mes }}
                </option>
            @endforeach
        </select>

        <button type="submit">Exportar</button>
    </form>
</div>
@endsection
