@extends('layout.default')

@push('styles')
    <link rel="stylesheet" type="text/css" href="{{ Asset::get('css/ieducar.css') }}"/>
@endpush

@section('content')
    @if ($errors->any())
        <div class="form_erro" style="margin-bottom: 12px;">
            @foreach ($errors->all() as $erro)
                <div>{{ $erro }}</div>
            @endforeach
        </div>
    @endif

    <form id="formcadastro" action="{{ route('instituto-brasil-solidario-export.export') }}" method="post">
        @csrf
        <table class="tablecadastro" width="100%" border="0" cellpadding="2" cellspacing="0" role="presentation">
            <tbody>
            <tr>
                <td class="formdktd" colspan="2" height="24"><b>Instituto Brasil Solidário</b></td>
            </tr>
            <tr>
                <td class="formlttd" colspan="2">
                    Gera a planilha <b>Dados das Escolas</b> no modelo do Instituto Brasil Solidário.
                    As quantidades de turmas, alunos e educadores saem dos cursos que você classificar abaixo,
                    pela etapa do curso (1ª, 2ª, 3ª...), sem depender do nome usado no município.
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
                <td class="formmdtd" valign="top"><span class="form">Escola</span></td>
                <td class="formmdtd" valign="top">
                    @include('form.select-school')
                </td>
            </tr>
            <tr>
                <td class="formdktd" colspan="2" height="24"><b>Cursos de cada grupo</b></td>
            </tr>
            <tr>
                <td class="formlttd" colspan="2">
                    Informe quais cursos do município pertencem a cada grupo. A 1ª etapa de Anos Iniciais
                    entra como 1º ano; da 6ª etapa em diante, entra como Anos Finais. Por isso, um curso único
                    do 1º ao 9º ano deve ser marcado em Anos Iniciais. A 1ª etapa de Anos Finais entra como 6º ano.
                    Na Educação Infantil, a 1ª etapa entra como creche e as seguintes como pré-escola.
                    Um curso só pode estar em um grupo.
                </td>
            </tr>
            @foreach (\App\Services\InstitutoBrasilSolidario\CursoGrupos::GRUPOS_FORMULARIO as $grupo)
                @php
                    $rotulo = \App\Services\InstitutoBrasilSolidario\CursoGrupos::ROTULOS[$grupo];
                    $campo = \App\Services\InstitutoBrasilSolidario\CursoGrupos::CAMPOS[$grupo];
                @endphp
                <tr id="tr_{{ $campo }}">
                    <td class="{{ $loop->even ? 'formlttd' : 'formmdtd' }}" valign="top">
                        <label for="{{ $campo }}" class="form">{{ $rotulo }}</label>
                    </td>
                    <td class="{{ $loop->even ? 'formlttd' : 'formmdtd' }}" valign="top">
                        <select name="{{ $campo }}[]" id="{{ $campo }}" multiple style="width: 308px;"></select>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td class="formdktd" colspan="2"></td>
            </tr>
            </tbody>
        </table>

        <div style="text-align: center">
            <button class="btn-green" type="submit">Exportar</button>
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
@endprepend

@php
    $camposCurso = array_values(array_intersect_key(
        \App\Services\InstitutoBrasilSolidario\CursoGrupos::CAMPOS,
        array_flip(\App\Services\InstitutoBrasilSolidario\CursoGrupos::GRUPOS_FORMULARIO)
    ));
    $selecionadosCurso = [];

    foreach ($camposCurso as $campoCurso) {
        $selecionadosCurso[$campoCurso] = array_values(array_map('intval', (array) old($campoCurso, [])));
    }
@endphp

@push('scripts')
    <script>
        (function ($) {
            var urlCursos = @json(route('instituto-brasil-solidario-export.cursos'));
            var campos = @json($camposCurso);
            var selecionados = @json($selecionadosCurso);

            function bloquearRepetidos() {
                var usados = {};

                campos.forEach(function (campo) {
                    ($('#' + campo).val() || []).forEach(function (id) {
                        usados[String(id)] = campo;
                    });
                });

                campos.forEach(function (campo) {
                    $('#' + campo + ' option').prop('disabled', false);

                    Object.keys(usados).forEach(function (id) {
                        if (usados[id] !== campo) {
                            $('#' + campo + ' option[value="' + id + '"]').prop('disabled', true);
                        }
                    });

                    $('#' + campo).trigger('chosen:updated');
                });
            }

            function preencher(cursos) {
                campos.forEach(function (campo) {
                    var $select = $('#' + campo);
                    var atual = $select.val() || selecionados[campo] || [];

                    $select.empty();
                    (cursos || []).forEach(function (curso) {
                        $select.append($('<option>', {value: curso.id, text: curso.nome}));
                    });
                    $select.val((atual || []).map(String));

                    if ($select.data('chosen')) {
                        $select.trigger('chosen:updated');
                    } else {
                        $select.chosen({
                            width: '308px',
                            search_contains: true,
                            placeholder_text_multiple: 'Selecione os cursos'
                        });
                    }
                });

                bloquearRepetidos();
            }

            function carregar() {
                var instituicao = $('#ref_cod_instituicao').val();

                if (!instituicao) {
                    preencher([]);
                    return;
                }

                $.getJSON(urlCursos, {ref_cod_instituicao: instituicao}, preencher);
            }

            $(function () {
                $('#ref_cod_instituicao').on('change', carregar);
                campos.forEach(function (campo) {
                    $('#' + campo).on('change', bloquearRepetidos);
                });
                carregar();
            });
        })(window.jQuery || window.$j);
    </script>
@endpush
