<?php

namespace Tests\Feature\Commands;

use App\Models\LegacyDiscipline;
use App\Models\LegacyKnowledgeArea;
use App\Models\LegacySchoolClass;
use App\Models\LegacySchoolClassTeacher;
use App\Models\LegacySchoolClassTeacherDiscipline;
use Database\Factories\LegacyDisciplineFactory;
use Database\Factories\LegacyDisciplineSchoolClassFactory;
use Database\Factories\LegacyKnowledgeAreaFactory;
use Database\Factories\LegacySchoolClassFactory;
use Database\Factories\LegacySchoolClassTeacherDisciplineFactory;
use Database\Factories\LegacySchoolClassTeacherFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillTeacherDescriptorLinksCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasColumn('modules.area_conhecimento', 'componente_vinculo_id')) {
            $this->markTestSkipped('Migration componente_vinculo_id ainda não aplicada.');
        }
    }

    public function test_falha_quando_nenhuma_area_esta_configurada(): void
    {
        LegacyKnowledgeArea::query()
            ->whereNotNull('componente_vinculo_id')
            ->update(['componente_vinculo_id' => null]);

        $this->artisan('descriptors:backfill-teacher-links')
            ->expectsOutputToContain('Nenhuma área agrupadora possui disciplina vinculada configurada')
            ->assertFailed();
    }

    public function test_dry_run_nao_grava_descritores(): void
    {
        [$link, $anchor, $descriptorA, $descriptorB] = $this->createTeacherLinkWithMissingDescriptors();

        $this->artisan('descriptors:backfill-teacher-links', [
            '--year' => $link->ano,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing(LegacySchoolClassTeacherDiscipline::class, [
            'professor_turma_id' => $link->id,
            'componente_curricular_id' => $descriptorA->id,
        ]);
        $this->assertDatabaseMissing(LegacySchoolClassTeacherDiscipline::class, [
            'professor_turma_id' => $link->id,
            'componente_curricular_id' => $descriptorB->id,
        ]);
        $this->assertDatabaseHas(LegacySchoolClassTeacherDiscipline::class, [
            'professor_turma_id' => $link->id,
            'componente_curricular_id' => $anchor->id,
        ]);
    }

    public function test_insere_descritores_faltantes_no_vinculo(): void
    {
        [$link, $anchor, $descriptorA, $descriptorB] = $this->createTeacherLinkWithMissingDescriptors();

        $this->artisan('descriptors:backfill-teacher-links', [
            '--year' => $link->ano,
        ])->assertSuccessful();

        $this->assertDatabaseHas(LegacySchoolClassTeacherDiscipline::class, [
            'professor_turma_id' => $link->id,
            'componente_curricular_id' => $anchor->id,
        ]);
        $this->assertDatabaseHas(LegacySchoolClassTeacherDiscipline::class, [
            'professor_turma_id' => $link->id,
            'componente_curricular_id' => $descriptorA->id,
        ]);
        $this->assertDatabaseHas(LegacySchoolClassTeacherDiscipline::class, [
            'professor_turma_id' => $link->id,
            'componente_curricular_id' => $descriptorB->id,
        ]);
    }

    public function test_nao_duplica_descritores_ja_vinculados(): void
    {
        [$link, $anchor, $descriptorA, $descriptorB] = $this->createTeacherLinkWithMissingDescriptors();

        LegacySchoolClassTeacherDisciplineFactory::new()->create([
            'professor_turma_id' => $link->id,
            'componente_curricular_id' => $descriptorA->id,
        ]);

        $this->artisan('descriptors:backfill-teacher-links', [
            '--year' => $link->ano,
        ])->assertSuccessful();

        $count = LegacySchoolClassTeacherDiscipline::query()
            ->where('professor_turma_id', $link->id)
            ->where('componente_curricular_id', $descriptorA->id)
            ->count();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas(LegacySchoolClassTeacherDiscipline::class, [
            'professor_turma_id' => $link->id,
            'componente_curricular_id' => $descriptorB->id,
        ]);
    }

    public function test_filtra_por_ano_e_ignora_outros_vinculos(): void
    {
        [$link2026] = $this->createTeacherLinkWithMissingDescriptors(now()->year);
        [$linkOtherYear, , $descriptorAOther] = $this->createTeacherLinkWithMissingDescriptors(now()->year - 1);

        $this->artisan('descriptors:backfill-teacher-links', [
            '--year' => $link2026->ano,
        ])->assertSuccessful();

        $this->assertDatabaseMissing(LegacySchoolClassTeacherDiscipline::class, [
            'professor_turma_id' => $linkOtherYear->id,
            'componente_curricular_id' => $descriptorAOther->id,
        ]);
    }

    /**
     * @return array{0: LegacySchoolClassTeacher, 1: LegacyDiscipline, 2: LegacyDiscipline, 3: LegacyDiscipline}
     */
    private function createTeacherLinkWithMissingDescriptors(?int $year = null): array
    {
        $year ??= (int) now()->year;

        /** @var LegacySchoolClass $schoolClass */
        $schoolClass = LegacySchoolClassFactory::new()->create([
            'ano' => $year,
        ]);

        $regularArea = LegacyKnowledgeAreaFactory::new()->create([
            'agrupar_descritores' => false,
        ]);

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

        foreach ([$anchor, $descriptorA, $descriptorB] as $discipline) {
            LegacyDisciplineSchoolClassFactory::new()->create([
                'componente_curricular_id' => $discipline->id,
                'escola_id' => $schoolClass->school_id,
                'turma_id' => $schoolClass->getKey(),
                'ano_escolar_id' => $schoolClass->grade_id,
            ]);
        }

        $link = LegacySchoolClassTeacherFactory::new()->create([
            'turma_id' => $schoolClass->getKey(),
            'ano' => $year,
            'instituicao_id' => $schoolClass->ref_cod_instituicao,
        ]);

        LegacySchoolClassTeacherDisciplineFactory::new()->create([
            'professor_turma_id' => $link->id,
            'componente_curricular_id' => $anchor->id,
        ]);

        return [$link, $anchor, $descriptorA, $descriptorB];
    }
}
