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

    <form id="formcadastro" action="{{ route('tc-gestao-export.export') }}" method="post">
        @csrf
        <table class="tablecadastro" width="100%" border="0" cellpadding="2" cellspacing="0" role="presentation">
            <tbody>
            <tr>
                <td class="formdktd" colspan="2" height="24">
                    <b>Exportação TC Gestão Pública</b>
                </td>
            </tr>
            <tr>
                <td class="formlttd" colspan="2">
                    Gera um ZIP com CSVs no padrão Educação SIAP do TC Gestão Pública:
                    Escola, Aluno, Turma, TurmaAluno, ProfissionalEducacao,
                    VinculoProfissionalEducacao, TurmaProfissional e ProfissionalFalta.
                </td>
            </tr>
            <tr>
                <td class="formmdtd" valign="top">
                    <span class="form">Instituição</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top">
                    @include('form.select-institution', ['obrigatorio' => true])
                </td>
            </tr>
            <tr>
                <td class="formmdtd" valign="top">
                    <span class="form">Ano de referência</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top">
                    <input class="geral obrigatorio" type="number" name="ano" id="ano" required
                           value="{{ old('ano', now()->year) }}" style="width: 308px;">
                </td>
            </tr>
            <tr>
                <td class="formlttd" valign="top">
                    <span class="form">Mês de referência</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formlttd" valign="top">
                    <select class="geral obrigatorio" name="mes" id="mes" required style="width: 308px;">
                        @foreach(range(1, 12) as $mes)
                            <option value="{{ $mes }}" @selected((int) old('mes', now()->month) === $mes)>
                                {{ str_pad((string) $mes, 2, '0', STR_PAD_LEFT) }}
                            </option>
                        @endforeach
                    </select>
                </td>
            </tr>
            <tr>
                <td class="formmdtd" valign="top">
                    <span class="form">Alunos com código INEP</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top">
                    <select class="geral obrigatorio" name="somente_alunos_com_inep" id="somente_alunos_com_inep" required style="width: 308px;">
                        <option value="0" @selected(old('somente_alunos_com_inep', '0') === '0')>
                            Exportar todos (sem INEP, Identificação fica em branco)
                        </option>
                        <option value="1" @selected(old('somente_alunos_com_inep') === '1')>
                            Somente alunos que possuem código INEP
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="formdktd" colspan="2"></td>
            </tr>
            <tr>
                <td colspan="2" align="center">
                    <button type="submit" class="btn-green">Exportar</button>
                </td>
            </tr>
            </tbody>
        </table>
    </form>
@endsection
