@extends('layout.default')

@push('styles')
    <link rel="stylesheet" type="text/css" href="{{ Asset::get('css/ieducar.css') }}"/>
@endpush

@section('content')
    <table class="tablecadastro" width="100%" border="0" cellpadding="2" cellspacing="0" role="presentation">
        <tbody>
        <tr>
            <td class="formdktd" colspan="2" height="24">
                <b>Arquivos gerados — TC Gestão Pública</b>
            </td>
        </tr>
        <tr>
            <td class="formlttd" colspan="2">
                A remessa CSV foi gerada com sucesso.
            </td>
        </tr>
        <tr>
            <td class="formmdtd" colspan="2" align="center" style="padding: 16px;">
                <a class="btn-green" href="{{ $zipUrl }}" download>Baixar remessa (ZIP)</a>
                &nbsp;
                <a href="{{ $txtUrl }}" download>Baixar avisos</a>
            </td>
        </tr>
        <tr>
            <td class="formdktd" colspan="2"></td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                <a href="{{ route('tc-gestao-export.index') }}">Voltar</a>
            </td>
        </tr>
        </tbody>
    </table>
@endsection
