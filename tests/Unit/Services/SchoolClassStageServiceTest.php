<?php

namespace Tests\Unit\Services;

use App\Services\SchoolClassStageService;
use Database\Factories\LegacySchoolClassFactory;
use Database\Factories\LegacySchoolClassStageFactory;
use Database\Factories\LegacyStageTypeFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SchoolClassStageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private SchoolClassStageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SchoolClassStageService;
    }

    public function test_store_reindexes_sparse_form_keys_as_sequential_stages(): void
    {
        $schoolClass = LegacySchoolClassFactory::new()->create();
        $stageType = LegacyStageTypeFactory::new()->create(['num_etapas' => 4]);
        $year = now()->year;

        $this->service->store(
            $schoolClass,
            [
                0 => '02/03/' . $year,
                1 => '14/05/' . $year,
                4 => '05/08/' . $year,
                5 => '14/10/' . $year,
            ],
            [
                0 => '13/05/' . $year,
                1 => '04/08/' . $year,
                4 => '13/10/' . $year,
                5 => '24/12/' . $year,
            ],
            [
                0 => 50,
                1 => 50,
                4 => 50,
                5 => 50,
            ],
            $stageType->cod_modulo
        );

        $stages = $schoolClass->schoolClassStages()->orderBy('sequencial')->get();

        $this->assertCount(4, $stages);
        $this->assertEquals([1, 2, 3, 4], $stages->pluck('sequencial')->all());
        $this->assertEquals($year . '-03-02', $stages[0]->data_inicio->format('Y-m-d'));
        $this->assertEquals($year . '-08-05', $stages[2]->data_inicio->format('Y-m-d'));
        $this->assertEquals($year . '-10-14', $stages[3]->data_inicio->format('Y-m-d'));
    }

    public function test_store_ignores_empty_rows_and_keeps_sequential_numbering(): void
    {
        $schoolClass = LegacySchoolClassFactory::new()->create();
        $stageType = LegacyStageTypeFactory::new()->create(['num_etapas' => 2]);
        $year = now()->year;

        $this->service->store(
            $schoolClass,
            [
                0 => '02/03/' . $year,
                1 => '05/08/' . $year,
                4 => '',
                5 => '',
            ],
            [
                0 => '04/08/' . $year,
                1 => '24/12/' . $year,
                4 => '',
                5 => '',
            ],
            [
                0 => 100,
                1 => 100,
                4 => '',
                5 => '',
            ],
            $stageType->cod_modulo
        );

        $stages = $schoolClass->schoolClassStages()->orderBy('sequencial')->get();

        $this->assertCount(2, $stages);
        $this->assertEquals([1, 2], $stages->pluck('sequencial')->all());
    }

    public function test_store_replaces_previous_stages_when_reducing_from_four_to_two(): void
    {
        $schoolClass = LegacySchoolClassFactory::new()->create();
        $stageType = LegacyStageTypeFactory::new()->create(['num_etapas' => 2]);
        $year = now()->year;

        foreach ([1, 2, 5, 6] as $sequencial) {
            LegacySchoolClassStageFactory::new()->create([
                'ref_cod_turma' => $schoolClass->cod_turma,
                'ref_cod_modulo' => $stageType->cod_modulo,
                'sequencial' => $sequencial,
            ]);
        }

        $this->service->store(
            $schoolClass,
            [
                0 => '02/03/' . $year,
                1 => '05/08/' . $year,
            ],
            [
                0 => '04/08/' . $year,
                1 => '24/12/' . $year,
            ],
            [0 => 100, 1 => 100],
            $stageType->cod_modulo
        );

        $stages = $schoolClass->schoolClassStages()->orderBy('sequencial')->get();

        $this->assertCount(2, $stages);
        $this->assertEquals([1, 2], $stages->pluck('sequencial')->all());
        $this->assertEquals($year . '-03-02', $stages[0]->data_inicio->format('Y-m-d'));
        $this->assertEquals($year . '-08-05', $stages[1]->data_inicio->format('Y-m-d'));
    }

    public function test_get_stages_blocked_on_reduction_includes_calendar_and_orphaned_stages(): void
    {
        $schoolClass = LegacySchoolClassFactory::new()->create();
        $stageType = LegacyStageTypeFactory::new()->create();

        foreach ([1, 2, 5, 6] as $sequencial) {
            LegacySchoolClassStageFactory::new()->create([
                'ref_cod_turma' => $schoolClass->cod_turma,
                'ref_cod_modulo' => $stageType->cod_modulo,
                'sequencial' => $sequencial,
            ]);
        }

        $this->assertEquals(
            [5, 6],
            $this->service->getStagesBlockedOnReduction($schoolClass->fresh(), 2)
        );
    }
}
