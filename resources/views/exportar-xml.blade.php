@extends('layout.public')

@section('content')
<div class="container mt-5">
    <h1>Exportar XML</h1>

    @if ($errors->any())
        <div class="alert alert-danger mt-3">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ url('/exportar-xml') }}" method="GET" class="mt-3">
        <div class="form-group">
            <label for="modelo">Escolha o modelo XML:</label>
            <select name="modelo" id="modelo" class="form-control" required>
                <option value="sagres">Modelo SAGRES TCE-SE</option>
                <option value="siap">Modelo Interno</option>
            </select>
        </div>

        <div class="form-group mt-3">
            <label for="ano">Ano de Referência:</label>
            <input type="number" name="ano" id="ano" class="form-control" required value="{{ now()->year }}">
        </div>

        <div class="form-group mt-3">
            <label for="mes">Mês de Referência:</label>
            <select name="mes" id="mes" class="form-control" required>
                @foreach(range(1,12) as $mes)
                    <option value="{{ $mes }}" {{ now()->month == $mes ? 'selected' : '' }}>
                        {{ $mes < 10 ? '0'.$mes : $mes }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-success mt-4">Exportar ZIP</button>
    </form>
</div>
@endsection
