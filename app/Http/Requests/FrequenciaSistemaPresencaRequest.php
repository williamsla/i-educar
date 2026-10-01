<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FrequenciaSistemaPresencaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ano' => ['required', 'date_format:Y'],
            'ref_cod_instituicao' => ['required'],
            'ref_cod_escola' => ['required', 'integer'],
            'meses' => ['required', 'array', 'min:1'],
            'meses.*' => ['integer', 'between:1,12', 'distinct'],
            'ordenar' => ['nullable', 'in:nome,faltas'],
            'ref_cod_serie' => ['nullable', 'integer'],
            'ref_cod_turma' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'ano.required' => 'O ano letivo é obrigatório.',
            'ano.date_format' => 'O campo ano deve ser um ano válido.',
            'ref_cod_instituicao.required' => 'A instituição é obrigatória.',
            'ref_cod_escola.required' => 'A escola é obrigatória.',
            'meses.required' => 'Selecione ao menos um mês.',
            'meses.min' => 'Selecione ao menos um mês.',
            'meses.*.between' => 'Os meses devem estar entre 1 e 12.',
        ];
    }
}
