<?php

namespace App\Services;

use App\Models\LegacyStudentBenefit;
use iEducar\Modules\Educacenso\Model\VeiculoTransporteEscolar;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Statement;

class ImportSislameBeneficioTransporteService
{
    private const BOLSA_FAMILIA_BENEFICIO_ID = 1;

    private int $processed = 0;

    private int $skippedSemCpf = 0;

    private int $skippedAlunoNaoEncontrado = 0;

    private int $beneficiosAtualizados = 0;

    private int $veiculosAtualizados = 0;

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

        $cpf = idFederal2int((string) ($row['NU_CPF'] ?? ''));

        if ($cpf === '') {
            $this->skippedSemCpf++;

            return;
        }

        $students = DB::table('pmieducar.aluno as al')
            ->join('cadastro.fisica as f', 'f.idpes', '=', 'al.ref_idpes')
            ->where('f.cpf', $cpf)
            ->where('al.ativo', 1)
            ->select(['al.cod_aluno', 'al.veiculo_transporte_escolar'])
            ->get();

        if ($students->isEmpty()) {
            $this->skippedAlunoNaoEncontrado++;

            return;
        }

        $bolsaFamilia = $this->isTruthy($row['FL_BOLSA_FAMILIA'] ?? null);
        $veiculos = $this->resolveVeiculos($row);

        foreach ($students as $student) {
            $updated = false;

            if ($bolsaFamilia && $this->studentHasNoBenefits((int) $student->cod_aluno)) {
                LegacyStudentBenefit::query()->firstOrCreate([
                    'aluno_id' => $student->cod_aluno,
                    'aluno_beneficio_id' => self::BOLSA_FAMILIA_BENEFICIO_ID,
                ]);
                $this->beneficiosAtualizados++;
                $updated = true;
            }

            if ($veiculos !== [] && $this->isVeiculoEmpty($student->veiculo_transporte_escolar)) {
                DB::table('pmieducar.aluno')
                    ->where('cod_aluno', $student->cod_aluno)
                    ->update([
                        'veiculo_transporte_escolar' => '{' . implode(',', $veiculos) . '}',
                    ]);
                $this->veiculosAtualizados++;
                $updated = true;
            }

            if (! $updated) {
                $this->semAlteracao++;
            }
        }
    }

    private function studentHasNoBenefits(int $alunoId): bool
    {
        return ! DB::table('pmieducar.aluno_aluno_beneficio')
            ->where('aluno_id', $alunoId)
            ->exists();
    }

    /**
     * @param  array<string, string|null>  $row
     * @return list<int>
     */
    private function resolveVeiculos(array $row): array
    {
        $veiculos = [];

        if ($this->isTruthy($row['FL_TRANSPORTE_VAN'] ?? null)) {
            $veiculos[] = VeiculoTransporteEscolar::VAN_KOMBI;
        }

        if ($this->isTruthy($row['FL_TRANSPORTE_MICROONIBUS'] ?? null)) {
            $veiculos[] = VeiculoTransporteEscolar::MICROONIBUS;
        }

        if ($this->isTruthy($row['FL_TRANSPORTE_ONIBUS'] ?? null)) {
            $veiculos[] = VeiculoTransporteEscolar::ONIBUS;
        }

        return $veiculos;
    }

    private function isTruthy(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        return in_array(strtolower(trim($value)), ['true', '1', 'sim', 's', 'yes'], true);
    }

    private function isVeiculoEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_array($value)) {
            return $value === [];
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '' || $stringValue === '{}') {
            return true;
        }

        return trim($stringValue, '{}') === '';
    }

    private function printSummary(): void
    {
        $this->output->newLine();
        $this->output->info('Importação concluída.');
        $this->output->table(
            ['Métrica', 'Quantidade'],
            [
                ['Linhas processadas', $this->processed],
                ['Ignoradas (sem CPF)', $this->skippedSemCpf],
                ['Ignoradas (aluno não encontrado)', $this->skippedAlunoNaoEncontrado],
                ['Benefícios adicionados', $this->beneficiosAtualizados],
                ['Veículos definidos', $this->veiculosAtualizados],
                ['Alunos sem alteração', $this->semAlteracao],
            ]
        );
    }
}
