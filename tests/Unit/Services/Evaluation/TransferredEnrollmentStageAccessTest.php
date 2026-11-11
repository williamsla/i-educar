<?php

namespace Tests\Unit\Services\Evaluation;

use App\Services\Evaluation\TransferredEnrollmentStageAccess;
use Tests\TestCase;

class TransferredEnrollmentStageAccessTest extends TestCase
{
    /**
     * Calendário letivo de 2026 (bimestres).
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private array $etapas2026 = [
        1 => ['2026-02-19', '2026-04-27'],
        2 => ['2026-04-28', '2026-08-08'],
        3 => ['2026-08-10', '2026-10-09'],
        4 => ['2026-10-10', '2026-12-18'],
    ];

    public function test_enturmacao_em_14_07_e_transferencia_em_28_10_libera_a_primeira_etapa(): void
    {
        $permitidas = $this->etapasPermitidas('2026-07-14', '2026-10-28', true);

        $this->assertSame([1, 2, 3, 4], $permitidas);
    }

    public function test_segunda_enturmacao_nao_libera_etapa_anterior_a_entrada(): void
    {
        $permitidas = $this->etapasPermitidas('2026-07-14', '2026-10-28', false);

        $this->assertSame([2, 3, 4], $permitidas);
    }

    public function test_saida_no_fim_da_etapa_2_bloqueia_etapas_seguintes(): void
    {
        $permitidas = $this->etapasPermitidas('2026-02-19', '2026-08-08', true);

        $this->assertSame([1, 2], $permitidas);
    }

    public function test_saida_antes_do_inicio_da_etapa_4_bloqueia_somente_essa_etapa(): void
    {
        $permitidas = $this->etapasPermitidas('2026-07-14', '2026-10-09', true);

        $this->assertSame([1, 2, 3], $permitidas);
    }

    /**
     * @return int[]
     */
    private function etapasPermitidas(string $enturmacao, string $saida, bool $permiteEtapasAnteriores): array
    {
        $permitidas = [];

        foreach ($this->etapas2026 as $etapa => [$inicio, $fim]) {
            if (TransferredEnrollmentStageAccess::allows($enturmacao, $saida, $inicio, $fim, $permiteEtapasAnteriores)) {
                $permitidas[] = $etapa;
            }
        }

        return $permitidas;
    }
}
