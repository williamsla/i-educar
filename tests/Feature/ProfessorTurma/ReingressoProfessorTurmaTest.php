<?php

namespace Tests\Feature\ProfessorTurma;

use Database\Factories\LegacySchoolClassTeacherFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReingressoProfessorTurmaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_permite_novo_vinculo_quando_o_anterior_ja_encerrou(): void
    {
        $vinculo = $this->vinculo([
            'data_inicial' => '2026-02-02',
            'data_fim' => '2026-03-31',
        ]);

        $conflito = $this->existe($vinculo, '2026-05-18', null);

        $this->assertFalse($conflito);
    }

    public function test_permite_novo_vinculo_encerrado_antes_do_periodo_seguinte(): void
    {
        $vinculo = $this->vinculo([
            'data_inicial' => '2026-05-18',
            'data_fim' => null,
        ]);

        $conflito = $this->existe($vinculo, '2026-02-02', '2026-02-28');

        $this->assertFalse($conflito);
    }

    public function test_bloqueia_quando_o_periodo_se_sobrepoe(): void
    {
        $vinculo = $this->vinculo([
            'data_inicial' => '2026-02-02',
            'data_fim' => '2026-03-31',
        ]);

        $conflito = $this->existe($vinculo, '2026-03-31', '2026-05-18');

        $this->assertSame((string) $vinculo->getKey(), (string) $conflito);
    }

    public function test_bloqueia_quando_o_vinculo_anterior_ainda_esta_aberto(): void
    {
        $vinculo = $this->vinculo([
            'data_inicial' => '2026-02-02',
            'data_fim' => null,
        ]);

        $conflito = $this->existe($vinculo, '2026-05-18', null);

        $this->assertSame((string) $vinculo->getKey(), (string) $conflito);
    }

    public function test_edicao_nao_conflita_com_o_proprio_registro(): void
    {
        $vinculo = $this->vinculo([
            'data_inicial' => '2026-02-02',
            'data_fim' => '2026-03-31',
        ]);

        $consulta = new \clsModulesProfessorTurma(
            id: $vinculo->getKey(),
            ano: $vinculo->ano,
            instituicao_id: $vinculo->instituicao_id,
            servidor_id: $vinculo->servidor_id,
            turma_id: $vinculo->turma_id,
            turno_id: $vinculo->turno_id,
            data_inicial: '2026-02-02',
            data_fim: '2026-03-31',
        );

        $this->assertFalse($consulta->existe2());
    }

    private function vinculo(array $datas)
    {
        return LegacySchoolClassTeacherFactory::new()->create(array_merge([
            'ano' => 2026,
        ], $datas));
    }

    private function existe($vinculo, ?string $dataInicial, ?string $dataFim)
    {
        $consulta = new \clsModulesProfessorTurma(
            ano: $vinculo->ano,
            instituicao_id: $vinculo->instituicao_id,
            servidor_id: $vinculo->servidor_id,
            turma_id: $vinculo->turma_id,
            turno_id: $vinculo->turno_id,
            data_inicial: $dataInicial,
            data_fim: $dataFim,
        );

        return $consulta->existe2();
    }
}
