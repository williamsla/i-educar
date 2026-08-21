<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StageRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StageRequestTest extends TestCase
{
    public function test_allows_end_date_in_the_following_year(): void
    {
        $validator = $this->validateStages([
            4 => [
                'data_inicio' => '01/11/2026',
                'data_fim' => '31/01/2027',
                'dias_letivos' => 60,
            ],
        ]);

        $this->assertFalse($validator->errors()->has('etapas.4.data_fim'));
    }

    public function test_allows_start_date_in_the_previous_year(): void
    {
        $validator = $this->validateStages([
            1 => [
                'data_inicio' => '15/12/2025',
                'data_fim' => '28/02/2026',
                'dias_letivos' => 50,
            ],
        ]);

        $this->assertFalse($validator->errors()->has('etapas.1.data_inicio'));
    }

    public function test_rejects_end_date_two_years_ahead(): void
    {
        $validator = $this->validateStages([
            4 => [
                'data_inicio' => '01/11/2026',
                'data_fim' => '31/01/2028',
                'dias_letivos' => 60,
            ],
        ]);

        $this->assertTrue($validator->errors()->has('etapas.4.data_fim'));
        $this->assertStringContainsString(
            'O ano da data de término deve ser 2026 ou 2027.',
            $validator->errors()->first('etapas.4.data_fim')
        );
    }

    public function test_rejects_start_date_two_years_ahead(): void
    {
        $validator = $this->validateStages([
            1 => [
                'data_inicio' => '01/02/2028',
                'data_fim' => '28/02/2028',
                'dias_letivos' => 20,
            ],
        ]);

        $this->assertTrue($validator->errors()->has('etapas.1.data_inicio'));
        $this->assertStringContainsString(
            'O ano da data de início deve ser 2026 ou um ano adjacente.',
            $validator->errors()->first('etapas.1.data_inicio')
        );
    }

    private function validateStages(array $etapas)
    {
        $data = [
            'ref_cod_instituicao' => 1,
            'tipo' => 'school',
            'ano' => 2026,
            'etapas' => $etapas,
        ];

        $request = StageRequest::create('/atualiza-etapa', 'POST', $data);

        return Validator::make($data, $request->rules(), $request->messages(), $request->attributes());
    }
}
