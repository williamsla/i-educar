<?php

namespace App\Http\Requests;

use App\Services\InstitutoBrasilSolidario\CursoGrupos;
use Illuminate\Foundation\Http\FormRequest;

class InstitutoBrasilSolidarioExportRequest extends FormRequest
{
    public function rules(): array
    {
        $regras = [
            'ano' => [
                'required',
                'date_format:Y',
            ],
            'ref_cod_instituicao' => 'required',
            'ref_cod_escola' => 'nullable',
        ];

        foreach (CursoGrupos::CAMPOS as $campo) {
            $regras[$campo] = 'nullable|array';
            $regras[$campo . '.*'] = 'integer';
        }

        return $regras;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $vistos = [];

            foreach (CursoGrupos::CAMPOS as $grupo => $campo) {
                foreach ((array) $this->input($campo, []) as $id) {
                    $id = (int) $id;

                    if ($id <= 0) {
                        continue;
                    }

                    if (isset($vistos[$id])) {
                        $validator->errors()->add(
                            $campo,
                            'O curso já foi informado em ' . CursoGrupos::ROTULOS[$vistos[$id]] . '.'
                        );

                        return;
                    }

                    $vistos[$id] = $grupo;
                }
            }

            if ($vistos === []) {
                $validator->errors()->add('cursos_ei', 'Informe os cursos de pelo menos um grupo.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'ano.required' => 'O ano é obrigatório.',
            'ano.date_format' => 'O campo ano deve ser um ano válido.',
            'ref_cod_instituicao.required' => 'A instituição é obrigatória.',
        ];
    }
}
