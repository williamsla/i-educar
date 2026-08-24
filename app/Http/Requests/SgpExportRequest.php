<?php

namespace App\Http\Requests;

use App\Services\SgpExport\SgpExportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SgpExportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ano' => [
                'required',
                'date_format:Y',
            ],
            'ref_cod_instituicao' => 'required',
            'ref_cod_escola' => 'nullable',
            'tipo' => [
                'required',
                Rule::in(array_keys(SgpExportService::types())),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ano.required' => 'O ano é obrigatório.',
            'ano.date_format' => 'O campo ano deve ser um ano válido.',
            'ref_cod_instituicao.required' => 'A instituição é obrigatória.',
            'tipo.required' => 'O tipo de exportação é obrigatório.',
            'tipo.in' => 'O tipo de exportação selecionado é inválido.',
        ];
    }
}
