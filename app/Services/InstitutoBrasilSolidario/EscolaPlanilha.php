<?php

namespace App\Services\InstitutoBrasilSolidario;

final class EscolaPlanilha
{
    /**
     * @param  array{motora: int, intelectual: int, psicossocial: int, visual: int, auditiva: int}  $defAlunos
     * @param  array{motora: int, intelectual: int, psicossocial: int, visual: int, auditiva: int}  $defEducadores
     * @param  array<string, int>  $turmas
     * @param  array<string, int>  $alunos
     * @param  array{ei: int, ai: int, af: int, em: int, eja: int}  $educadores
     */
    public function __construct(
        public readonly int $sequencia,
        public readonly string $nome,
        public readonly string $inep,
        public readonly string $rede,
        public readonly string $zona,
        public readonly string $municipio,
        public readonly string $redesSociais,
        public readonly string $contato,
        public readonly string $telefone,
        public readonly string $email,
        public readonly int $docentes,
        public readonly int $funcionarios,
        public readonly array $defAlunos,
        public readonly array $defEducadores,
        public readonly array $turmas,
        public readonly array $alunos,
        public readonly array $educadores,
    ) {
    }

    public function alunos(string $bucket): int
    {
        return (int) ($this->alunos[$bucket] ?? 0);
    }

    public function turmasDe(string $bucket): int
    {
        return (int) ($this->turmas[$bucket] ?? 0);
    }

    public function totalAlunos(string $grupo): int
    {
        return $this->somar($this->alunos, $grupo);
    }

    public function totalTurmas(string $grupo): int
    {
        return $this->somar($this->turmas, $grupo);
    }

    public function totalGeralAlunos(): int
    {
        return array_sum($this->alunos);
    }

    public function etapasPresentes(): string
    {
        $etapas = [];

        if ($this->totalAlunos('ei') > 0 || $this->totalTurmas('ei') > 0) {
            $etapas[] = 'Anos Infantis';
        }

        if ($this->totalAlunos('ai') > 0 || $this->totalTurmas('ai') > 0) {
            $etapas[] = 'Anos Iniciais';
        }

        if ($this->totalAlunos('af') > 0 || $this->totalTurmas('af') > 0) {
            $etapas[] = 'Anos Finais';
        }

        if ($this->totalAlunos('em') > 0 || $this->totalTurmas('em') > 0) {
            $etapas[] = 'Ensino Médio';
        }

        if ($this->totalAlunos('eja') > 0 || $this->totalTurmas('eja') > 0) {
            $etapas[] = 'EJA';
        }

        return $etapas === [] ? 'Sem Etapa' : implode(', ', $etapas);
    }

    public function possuiDeficienciaAlunos(): string
    {
        return $this->possui($this->defAlunos);
    }

    public function possuiDeficienciaEducadores(): string
    {
        return $this->possui($this->defEducadores);
    }

    public function logs(): string
    {
        $avisos = [];

        if ($this->inep === '') {
            $avisos[] = 'Sem código INEP';
        }

        if ($this->telefone === '') {
            $avisos[] = 'Sem telefone';
        }

        if ($this->email === '') {
            $avisos[] = 'Sem e-mail';
        }

        if ($this->docentes === 0 && $this->totalGeralAlunos() > 0) {
            $avisos[] = 'Sem docentes vinculados';
        }

        if ($avisos !== []) {
            return implode('; ', $avisos);
        }

        return 'Consistente Consistente Consistente Consistente';
    }

    public function farol(): float|string
    {
        $ocorrencias = substr_count($this->logs(), 'Consistente');

        if ($ocorrencias === 0) {
            return '';
        }

        return $ocorrencias / 4;
    }

    public function piquenique(): int
    {
        return KitCalculator::piqueniqueIniciais($this->totalAlunos('ai'))
            + KitCalculator::piqueniqueFinais($this->totalAlunos('af'), $this->totalTurmas('af'));
    }

    public function bonsNegocios(): int
    {
        return KitCalculator::bonsNegocios($this->totalAlunos('af'), $this->totalTurmas('af'))
            + KitCalculator::bonsNegocios($this->totalAlunos('em'), $this->totalTurmas('em'))
            + KitCalculator::bonsNegociosEja($this->totalAlunos('eja'), $this->totalTurmas('eja'));
    }

    public function picCity(): int
    {
        return KitCalculator::picCity($this->totalAlunos('af'), $this->totalTurmas('af'))
            + KitCalculator::picCity($this->totalAlunos('em'), $this->totalTurmas('em'))
            + KitCalculator::picCityEja($this->totalAlunos('eja'), $this->totalTurmas('eja'));
    }

    /**
     * @param  array<string, int>  $valores
     */
    private function somar(array $valores, string $grupo): int
    {
        $total = 0;

        foreach (EtapaBuckets::GRUPO as $bucket => $grupoBucket) {
            if ($grupoBucket === $grupo) {
                $total += (int) ($valores[$bucket] ?? 0);
            }
        }

        return $total;
    }

    /**
     * @param  array{motora: int, intelectual: int, psicossocial: int, visual: int, auditiva: int}  $grupos
     */
    private function possui(array $grupos): string
    {
        foreach ($grupos as $quantidade) {
            if ((int) $quantidade > 0) {
                return 'Sim';
            }
        }

        return 'Não';
    }
}
