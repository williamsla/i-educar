@extends('layout.default')

@push('styles')
    <link rel="stylesheet" type="text/css" href="{{ Asset::get('css/ieducar.css') }}"/>
@endpush

@section('content')
    @if ($errors->any())
        <div class="form_erro" style="margin-bottom: 12px;">
            {{ $errors->first() }}
        </div>
    @endif

    @php
        $mesesNomes = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        $mesesSelecionados = array_map('intval', (array) old('meses', []));
    @endphp

    <form id="formcadastro" action="{{ route('frequencia-sistema-presenca.report') }}" method="post">
        @csrf
        <table class="tablecadastro" width="100%" border="0" cellpadding="2" cellspacing="0" role="presentation">
            <tbody>
            <tr>
                <td class="formdktd" colspan="2" height="24"><b>Frequência para o Sistema Presença</b></td>
            </tr>
            <tr>
                <td class="formlttd" colspan="2">
                    Gera o PDF de faltas mensais por aluno a partir do i-Diário.
                    A URL usada é a do campo <b>URL do Diário do Professor</b> em Configurações Gerais.
                    O token é a <b>API_ACCESS_KEY</b> do .env, a mesma chave de acesso configurada no i-Diário.
                    Série e turma são opcionais; sem esses filtros o relatório traz todas as turmas da escola.
                </td>
            </tr>
            <tr id="tr_nm_ano">
                <td class="formmdtd" valign="top">
                    <span class="form">Ano letivo</span>
                    <span class="campo_obrigatorio">*</span>
                    <br>
                    <sub style="vertical-align:top;">somente números</sub>
                </td>
                <td class="formmdtd" valign="top">
                    @include('form.select-year')
                </td>
            </tr>
            <tr id="tr_nm_instituicao">
                <td class="formlttd" valign="top">
                    <span class="form">Instituição</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formlttd" valign="top">
                    @include('form.select-institution')
                </td>
            </tr>
            <tr id="tr_nm_escola">
                <td class="formmdtd" valign="top">
                    <span class="form">Escola</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top">
                    @include('form.select-school')
                </td>
            </tr>
            <tr id="tr_nm_curso">
                <td class="formlttd" valign="top"><span class="form">Curso</span></td>
                <td class="formlttd" valign="top">
                    @include('form.select-course')
                </td>
            </tr>
            <tr id="tr_nm_serie">
                <td class="formmdtd" valign="top"><span class="form">Série</span></td>
                <td class="formmdtd" valign="top">
                    @include('form.select-grade')
                </td>
            </tr>
            <tr id="tr_nm_turma">
                <td class="formlttd" valign="top"><span class="form">Turma</span></td>
                <td class="formlttd" valign="top">
                    @include('form.select-school-class')
                </td>
            </tr>
            <tr id="tr_meses">
                <td class="formmdtd" valign="top">
                    <span class="form">Meses</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top">
                    @foreach ($mesesNomes as $numero => $nome)
                        <label style="display:inline-block; min-width: 140px; margin: 2px 8px 2px 0;">
                            <input type="checkbox" name="meses[]" value="{{ $numero }}" @checked(in_array($numero, $mesesSelecionados, true))>
                            {{ $nome }}
                        </label>
                    @endforeach
                </td>
            </tr>
            <tr id="tr_ordenar">
                <td class="formlttd" valign="top"><span class="form">Ordenar por</span></td>
                <td class="formlttd" valign="top">
                    <select class="geral" name="ordenar" id="ordenar" style="width: 308px;">
                        <option value="nome" @selected(old('ordenar', 'nome') === 'nome')>Nome do aluno</option>
                        <option value="faltas" @selected(old('ordenar') === 'faltas')>Maior total de faltas</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="formdktd" colspan="2"></td>
            </tr>
            </tbody>
        </table>

        <div style="text-align: center">
            <button class="btn-green" type="submit">Gerar relatório</button>
        </div>
    </form>
@endsection

@prepend('scripts')
    <link type='text/css' rel='stylesheet' href='{{ Asset::get("/vendor/legacy/Portabilis/Assets/Plugins/Chosen/chosen.css") }}'>
    <script type='text/javascript' src='{{ Asset::get('/vendor/legacy/Portabilis/Assets/Plugins/Chosen/chosen.jquery.min.js') }}'></script>
    <script type="text/javascript"
            src="{{ Asset::get("/vendor/legacy/Portabilis/Assets/Javascripts/ClientApi.js") }}"></script>
    <script type="text/javascript"
            src="{{ Asset::get("/vendor/legacy/DynamicInput/Assets/Javascripts/DynamicInput.js") }}"></script>
    <script type="text/javascript"
            src="{{ Asset::get("/vendor/legacy/DynamicInput/Assets/Javascripts/Escola.js") }}"></script>
    <script type="text/javascript"
            src="{{ Asset::get("/vendor/legacy/DynamicInput/Assets/Javascripts/Curso.js") }}"></script>
    <script type="text/javascript"
            src="{{ Asset::get("/vendor/legacy/DynamicInput/Assets/Javascripts/Serie.js") }}"></script>
    <script type="text/javascript"
            src="{{ Asset::get("/vendor/legacy/DynamicInput/Assets/Javascripts/Turma.js") }}"></script>
@endprepend
