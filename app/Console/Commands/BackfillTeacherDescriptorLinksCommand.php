<?php

namespace App\Console\Commands;

use App\Models\LegacyKnowledgeArea;
use App\Models\LegacySchoolClassTeacher;
use App\Services\DisciplineDescriptorAutoLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillTeacherDescriptorLinksCommand extends Command
{
    protected $signature = 'descriptors:backfill-teacher-links
                            {--year= : Filtra pelo ano letivo do vínculo}
                            {--school= : Filtra pela escola (cod_escola)}
                            {--institution= : Filtra pela instituição}
                            {--dry-run : Apenas simula, sem gravar}';

    protected $description = 'Completa vínculos professor×turma já existentes com descritores das fichas conceituais vinculadas às disciplinas âncora';

    public function handle(DisciplineDescriptorAutoLinkService $service): int
    {
        if (!Schema::hasColumn('modules.area_conhecimento', 'componente_vinculo_id')) {
            $this->error('Coluna componente_vinculo_id não existe. Execute as migrations antes.');

            return self::FAILURE;
        }

        $configuredAreas = LegacyKnowledgeArea::query()
            ->where('agrupar_descritores', true)
            ->whereNotNull('componente_vinculo_id')
            ->count();

        if ($configuredAreas === 0) {
            $this->warn('Nenhuma área agrupadora possui disciplina vinculada configurada.');
            $this->warn('Configure o campo "Disciplina vinculada" nas áreas da ficha antes de rodar o backfill.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $year = $this->option('year');
        $schoolId = $this->option('school');
        $institutionId = $this->option('institution');

        $query = LegacySchoolClassTeacher::query()
            ->with(['disciplines:id', 'schoolClass:cod_turma,ref_ref_cod_escola'])
            ->when($year, fn ($q) => $q->where('ano', $year))
            ->when($institutionId, fn ($q) => $q->where('instituicao_id', $institutionId))
            ->when($schoolId, function ($q) use ($schoolId) {
                $q->whereHas('schoolClass', fn ($turma) => $turma->where('ref_ref_cod_escola', $schoolId));
            });

        $totalLinks = (clone $query)->count();
        $this->info("Áreas configuradas: {$configuredAreas}");
        $this->info("Vínculos a analisar: {$totalLinks}" . ($dryRun ? ' (dry-run)' : ''));

        if ($totalLinks === 0) {
            $this->info('Nenhum vínculo encontrado com os filtros informados.');

            return self::SUCCESS;
        }

        $linksUpdated = 0;
        $descriptorsInserted = 0;
        $bar = $this->output->createProgressBar($totalLinks);
        $bar->start();

        $query->orderBy('id')->chunkById(100, function ($links) use (
            $service,
            $dryRun,
            &$linksUpdated,
            &$descriptorsInserted,
            $bar
        ) {
            foreach ($links as $link) {
                $currentIds = $link->disciplines->pluck('id')->all();
                $missing = $service->missingDescriptorsForSchoolClass(
                    $currentIds,
                    (int) $link->turma_id
                );

                if ($missing !== []) {
                    $linksUpdated++;
                    $descriptorsInserted += count($missing);

                    if (!$dryRun) {
                        $rows = array_map(
                            fn (int $descriptorId) => [
                                'professor_turma_id' => $link->id,
                                'componente_curricular_id' => $descriptorId,
                            ],
                            $missing
                        );

                        DB::table('modules.professor_turma_disciplina')->insertOrIgnore($rows);
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Vínculos analisados', $totalLinks],
                ['Vínculos que precisam de descritores', $linksUpdated],
                ['Descritores a inserir' . ($dryRun ? ' (não gravados)' : ' inseridos'), $descriptorsInserted],
            ]
        );

        if ($dryRun) {
            $this->comment('Dry-run: nada foi gravado. Remova --dry-run para aplicar.');
        } else {
            $this->info('Backfill concluído.');
        }

        return self::SUCCESS;
    }
}
