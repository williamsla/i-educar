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

    <form id="formcadastro" action="{{ route('sgp-export.export') }}" method="post">
        @csrf
        <table class="tablecadastro" width="100%" border="0" cellpadding="2" cellspacing="0" role="presentation">
            <tbody>
            <tr>
                <td class="formdktd" colspan="2" height="24"><b>Exportação para o SGP</b></td>
            </tr>
            <tr>
                <td class="formlttd" colspan="2">
                    Gera planilhas no layout do Sistema Gestão Presente apenas com dados do <b>ano letivo</b>
                    selecionado (escolas, componentes curriculares, profissionais, turmas, estudantes e matrículas).
                    Os identificadores <code>ID_SGP_ESTUDANTE</code> e <code>ID_SGP_MATRICULA</code> ficam vazios
                    porque são gerados pelo SGP. Em turmas, o código do i-Educar vai em <code>CO_TURMA_REDE</code>;
                    em estudantes e matrículas, o mesmo código é repetido em <code>ID_SGP_TURMA</code> para facilitar
                    o cruzamento após a importação das turmas.
                </td>
            </tr>
            <tr id="tr_nm_tipo">
                <td class="formmdtd" valign="top">
                    <span class="form">Tipo de exportação</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top">
                    <span class="form">
                        <select class="geral obrigatorio" name="tipo" id="tipo" style="width: 308px;">
                            <option value="">Selecione</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @if(old('tipo', Request::get('tipo')) == $value) selected @endif>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </span>
                </td>
            </tr>
            <tr id="tr_nm_ano">
                <td class="formlttd" valign="top">
                    <span class="form">Ano letivo</span>
                    <span class="campo_obrigatorio">*</span>
                    <br>
                    <sub style="vertical-align:top;">somente números</sub>
                </td>
                <td class="formlttd" valign="top">
                    @include('form.select-year')
                </td>
            </tr>
            <tr id="tr_nm_instituicao">
                <td class="formmdtd" valign="top">
                    <span class="form">Instituição</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top">
                    @include('form.select-institution')
                </td>
            </tr>
            <tr id="tr_nm_escola">
                <td class="formlttd" valign="top"><span class="form">Escola</span></td>
                <td class="formlttd" valign="top">
                    @include('form.select-school')
                </td>
            </tr>
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
