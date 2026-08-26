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

    <form id="formcadastro" action="{{ route('mec-gestao-presente-export.export') }}" method="post">
        @csrf
        <table class="tablecadastro" width="100%" border="0" cellpadding="2" cellspacing="0" role="presentation">
            <tbody>
            <tr>
                <td class="formdktd" colspan="2" height="24"><b>MEC Gestão Presente na Escola</b></td>
            </tr>
            <tr>
                <td class="formlttd" colspan="2">
                    Gera a planilha oficial de profissionais da educação no layout do
                    <b>MEC Gestão Presente na Escola</b>, com dados do <b>ano letivo</b> selecionado
                    (identificação, endereço, formação acadêmica e vínculo).
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
