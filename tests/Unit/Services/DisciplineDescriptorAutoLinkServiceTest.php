<?php

namespace Tests\Unit\Services;

use App\Models\LegacyDiscipline;
use App\Models\LegacyKnowledgeArea;
use App\Models\LegacySchoolClass;
use App\Services\DisciplineDescriptorAutoLinkService;
use Database\Factories\LegacyDisciplineFactory;
use Database\Factories\LegacyDisciplineSchoolClassFactory;
use Database\Factories\LegacyKnowledgeAreaFactory;
use Database\Factories\LegacySchoolClassFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DisciplineDescriptorAutoLinkServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DisciplineDescriptorAutoLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DisciplineDescriptorAutoLinkService::class);
    }

    public function test_expande_descritores_quando_ancora_selecionada(): void
    {
        [$anchor, $descriptorA, $descriptorB] = $this->createAnchorWithDescriptors();

        $result = $this->service->expand(
            [$anchor->id],
            [$anchor->id, $descriptorA->id, $descriptorB->id]
        );

        $this->assertSame(
            [$anchor->id, $descriptorA->id, $descriptorB->id],
            $result
        );
    }

    public function test_remove_descritores_quando_ancora_nao_esta_selecionada(): void
    {
        [$anchor, $descriptorA, $descriptorB] = $this->createAnchorWithDescriptors();

        $result = $this->service->expand(
            [$descriptorA->id, $descriptorB->id],
            [$anchor->id, $descriptorA->id, $descriptorB->id]
        );

        $this->assertSame([], $result);
    }

    public function test_nao_inclui_descritores_fora_da_oferta(): void
    {
        [$anchor, $descriptorA, $descriptorB] = $this->createAnchorWithDescriptors();

        $result = $this->service->expand(
            [$anchor->id],
            [$anchor->id, $descriptorA->id]
        );

        $this->assertSame([$anchor->id, $descriptorA->id], $result);
        $this->assertNotContains($descriptorB->id, $result);
    }

    public function test_remove_descritores_ao_tirar_ancora_mantendo_outra_disciplina(): void
    {
        [$anchor, $descriptorA] = $this->createAnchorWithDescriptors();
        $otherArea = LegacyKnowledgeAreaFactory::new()->create([
            'agrupar_descritores' => false,
        ]);
        $math = LegacyDisciplineFactory::new()->create([
            'knowledge_area_id' => $otherArea->id,
            'name' => 'Matemática',
        ]);

        $result = $this->service->expand(
            [$math->id, $descriptorA->id],
            [$anchor->id, $descriptorA->id, $math->id]
        );

        $this->assertSame([$math->id], $result);
    }

    public function test_mantem_componentes_sem_vinculo_de_ficha(): void
    {
        $area = LegacyKnowledgeAreaFactory::new()->create([
            'agrupar_descritores' => false,
        ]);
        $math = LegacyDisciplineFactory::new()->create([
            'knowledge_area_id' => $area->id,
            'name' => 'Matemática',
        ]);

        $result = $this->service->expand([$math->id], [$math->id]);

        $this->assertSame([$math->id], $result);
    }

    public function test_selecao_vazia_retorna_vazio(): void
    {
        $this->assertSame([], $this->service->expand([], [1, 2, 3]));
    }

    public function test_ignora_ids_invalidos_na_selecao(): void
    {
        [$anchor, $descriptorA] = $this->createAnchorWithDescriptors();

        $result = $this->service->expand(
            [null, '', '0', $anchor->id],
            [$anchor->id, $descriptorA->id]
        );

        $this->assertSame([$anchor->id, $descriptorA->id], $result);
    }

    public function test_expand_for_school_class_respeita_oferta_da_turma(): void
    {
        [$schoolClass, $anchor, $descriptorA, $descriptorB] = $this->createSchoolClassWithAnchorAndDescriptors();

        // Oferta da turma: âncora + descritor A (sem B)
        $this->offerDisciplinesOnSchoolClass($schoolClass, [$anchor, $descriptorA]);

        $result = $this->service->expandForSchoolClass(
            [$anchor->id],
            (int) $schoolClass->getKey()
        );

        $this->assertSame([$anchor->id, $descriptorA->id], $result);
        $this->assertNotContains($descriptorB->id, $result);
    }

    public function test_missing_descriptors_for_school_class_retorna_somente_faltantes(): void
    {
        [$schoolClass, $anchor, $descriptorA, $descriptorB] = $this->createSchoolClassWithAnchorAndDescriptors();
        $this->offerDisciplinesOnSchoolClass($schoolClass, [$anchor, $descriptorA, $descriptorB]);

        $missing = $this->service->missingDescriptorsForSchoolClass(
            [$anchor->id, $descriptorA->id],
            (int) $schoolClass->getKey()
        );

        $this->assertSame([$descriptorB->id], $missing);
    }

    public function test_missing_descriptors_vazio_quando_ja_completo(): void
    {
        [$schoolClass, $anchor, $descriptorA, $descriptorB] = $this->createSchoolClassWithAnchorAndDescriptors();
        $this->offerDisciplinesOnSchoolClass($schoolClass, [$anchor, $descriptorA, $descriptorB]);

        $missing = $this->service->missingDescriptorsForSchoolClass(
            [$anchor->id, $descriptorA->id, $descriptorB->id],
            (int) $schoolClass->getKey()
        );

        $this->assertSame([], $missing);
    }

    /**
     * @return array{0: LegacyDiscipline, 1: LegacyDiscipline, 2: LegacyDiscipline}
     */
    private function createAnchorWithDescriptors(): array
    {
        $regularArea = LegacyKnowledgeAreaFactory::new()->create([
            'agrupar_descritores' => false,
        ]);

        /** @var LegacyDiscipline $anchor */
        $anchor = LegacyDisciplineFactory::new()->create([
            'knowledge_area_id' => $regularArea->id,
            'name' => 'Língua Portuguesa',
        ]);

        /** @var LegacyKnowledgeArea $grouperArea */
        $grouperArea = LegacyKnowledgeAreaFactory::new()->create([
            'agrupar_descritores' => true,
            'componente_vinculo_id' => $anchor->id,
        ]);

        $descriptorA = LegacyDisciplineFactory::new()->create([
            'knowledge_area_id' => $grouperArea->id,
            'name' => 'Descritor A',
        ]);

        $descriptorB = LegacyDisciplineFactory::new()->create([
            'knowledge_area_id' => $grouperArea->id,
            'name' => 'Descritor B',
        ]);

        return [$anchor, $descriptorA, $descriptorB];
    }

    /**
     * @return array{0: LegacySchoolClass, 1: LegacyDiscipline, 2: LegacyDiscipline, 3: LegacyDiscipline}
     */
    private function createSchoolClassWithAnchorAndDescriptors(): array
    {
        $schoolClass = LegacySchoolClassFactory::new()->create();
        [$anchor, $descriptorA, $descriptorB] = $this->createAnchorWithDescriptors();

        return [$schoolClass, $anchor, $descriptorA, $descriptorB];
    }

    /**
     * @param  array<int, LegacyDiscipline>  $disciplines
     */
    private function offerDisciplinesOnSchoolClass(LegacySchoolClass $schoolClass, array $disciplines): void
    {
        foreach ($disciplines as $discipline) {
            LegacyDisciplineSchoolClassFactory::new()->create([
                'componente_curricular_id' => $discipline->id,
                'escola_id' => $schoolClass->school_id,
                'turma_id' => $schoolClass->getKey(),
                'ano_escolar_id' => $schoolClass->grade_id,
            ]);
        }
    }
}
