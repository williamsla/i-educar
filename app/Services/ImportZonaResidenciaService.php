<?php

namespace App\Services;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Statement;

class ImportZonaResidenciaService
{
    private int $processed = 0;

    private int $skippedSemIdentificador = 0;

    private int $skippedAlunoNaoEncontrado = 0;

    private int $zonaAtualizada = 0;

    private int $localizacaoAtualizada = 0;

    private int $corAtualizada = 0;

    private int $semAlteracao = 0;

    public function __construct(
        private readonly OutputStyle $output,
    ) {}

    public function import(string $filePath): void
    {
        if (! is_file($filePath)) {
            throw new \InvalidArgumentException("Arquivo não encontrado: {$filePath}");
        }

        $reader = Reader::createFromPath($filePath);
        $reader->setHeaderOffset(0);

        $total = max(0, count(file($filePath)) - 1);
        $this->output->progressStart($total);

        $records = Statement::create()->process($reader);

        foreach ($records as $row) {
            $this->processRow($row);
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->printSummary();
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function processRow(array $row): void
    {
        $this->processed++;

        $inep = $this->normalizeInep($row['CD_INEP'] ?? null);
        $nis = $this->normalizeNis($row['NU_NIS'] ?? null);

        if ($inep === null && $nis === null) {
            $this->skippedSemIdentificador++;

            return;
        }

        $students = $this->findStudents($inep, $nis);

        if ($students->isEmpty()) {
            $this->skippedAlunoNaoEncontrado++;

            return;
        }

        $zonaResidencial = $this->parseIntegerValue($row['TP_ZONA_RESIDENCIAL'] ?? null);
        $localizacaoDiferenciada = $this->parseIntegerValue($row['TP_LOCALIZACAO_DIFERENCIADA'] ?? null);
        $corRaca = $this->parseCorRaca($row['TP_COR'] ?? null);

        foreach ($students as $student) {
            $updated = false;
            $updates = [];

            if ($zonaResidencial !== null && $this->isFieldEmpty($student->zona_localizacao_censo)) {
                $updates['zona_localizacao_censo'] = $zonaResidencial;
            }

            if ($localizacaoDiferenciada !== null && $this->isFieldEmpty($student->localizacao_diferenciada)) {
                $updates['localizacao_diferenciada'] = $localizacaoDiferenciada;
            }

            if ($updates !== []) {
                DB::table('cadastro.fisica')
                    ->where('idpes', $student->ref_idpes)
                    ->update($updates);

                if (isset($updates['zona_localizacao_censo'])) {
                    $this->zonaAtualizada++;
                }

                if (isset($updates['localizacao_diferenciada'])) {
                    $this->localizacaoAtualizada++;
                }

                $updated = true;
            }

            if ($corRaca !== null && $this->studentHasNoRace((int) $student->ref_idpes)) {
                DB::table('cadastro.fisica_raca')->insert([
                    'ref_idpes' => $student->ref_idpes,
                    'ref_cod_raca' => $corRaca,
                ]);
                $this->corAtualizada++;
                $updated = true;
            }

            if (! $updated) {
                $this->semAlteracao++;
            }
        }
    }

    private function findStudents(?string $inep, ?string $nis): Collection
    {
        $query = DB::table('pmieducar.aluno as al')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'al.ref_idpes')
            ->leftJoin('modules.educacenso_cod_aluno as eca', 'eca.cod_aluno', '=', 'al.cod_aluno')
            ->where('al.ativo', 1)
            ->select([
                'al.cod_aluno',
                'al.ref_idpes',
                'f.zona_localizacao_censo',
                'f.localizacao_diferenciada',
            ]);

        if ($inep !== null && $nis !== null) {
            $query->where(function ($builder) use ($inep, $nis) {
                $builder->where('eca.cod_aluno_inep', $inep)
                    ->orWhere('f.nis_pis_pasep', $nis);
            });
        } elseif ($inep !== null) {
            $query->where('eca.cod_aluno_inep', $inep);
        } else {
            $query->where('f.nis_pis_pasep', $nis);
        }

        return $query->distinct()->get();
    }

    private function normalizeInep(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', trim($value));

        return $digits !== '' ? $digits : null;
    }

    private function normalizeNis(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', trim($value));

        if ($digits === '' || strlen($digits) > 11) {
            return null;
        }

        return (string) (int) $digits;
    }

    private function parseIntegerValue(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', trim($value));

        if ($digits === '') {
            return null;
        }

        return (int) $digits;
    }

    private function parseCorRaca(?string $value): ?int
    {
        $cor = $this->parseIntegerValue($value);

        if ($cor === null || $cor === 0 || $cor > 6) {
            return null;
        }

        return $cor;
    }

    private function studentHasNoRace(int $idpes): bool
    {
        return ! DB::table('cadastro.fisica_raca')
            ->where('ref_idpes', $idpes)
            ->exists();
    }

    private function isFieldEmpty(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function printSummary(): void
    {
        $this->output->newLine();
        $this->output->info('Importação concluída.');
        $this->output->table(
            ['Métrica', 'Quantidade'],
            [
                ['Linhas processadas', $this->processed],
                ['Ignoradas (sem INEP e NIS)', $this->skippedSemIdentificador],
                ['Ignoradas (aluno não encontrado)', $this->skippedAlunoNaoEncontrado],
                ['Zonas de residência definidas', $this->zonaAtualizada],
                ['Localizações diferenciadas definidas', $this->localizacaoAtualizada],
                ['Cores/raças definidas', $this->corAtualizada],
                ['Alunos sem alteração', $this->semAlteracao],
            ]
        );
    }
}
