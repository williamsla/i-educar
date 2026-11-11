<?php

namespace App\Services\Evaluation;

use DateTimeInterface;

/**
 * Define se um aluno transferido ou remanejado pode lançar nota e falta em uma etapa.
 */
class TransferredEnrollmentStageAccess
{
    /**
     * A etapa é permitida quando o período na turma cruza o calendário da etapa.
     * Na primeira enturmação da matrícula, etapas já encerradas antes da entrada
     * também são permitidas, para registrar notas trazidas de outra escola.
     * Etapas que começam depois da saída permanecem bloqueadas.
     */
    public static function allows(
        mixed $enrollmentDate,
        mixed $departureDate,
        mixed $stageStart,
        mixed $stageEnd,
        bool $allowStagesBeforeEnrollment = false
    ): bool {
        $enrollmentDate = self::toDate($enrollmentDate);
        $departureDate = self::toDate($departureDate);
        $stageStart = self::toDate($stageStart);
        $stageEnd = self::toDate($stageEnd);

        if ($enrollmentDate === null || $departureDate === null || $stageStart === null || $stageEnd === null) {
            return false;
        }

        if ($enrollmentDate <= $stageEnd && $departureDate >= $stageStart) {
            return true;
        }

        return $allowStagesBeforeEnrollment
            && $stageEnd < $enrollmentDate
            && $departureDate >= $stageStart;
    }

    private static function toDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches) === 1) {
            return $matches[1];
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}
