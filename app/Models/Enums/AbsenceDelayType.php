<?php

namespace App\Models\Enums;

use Illuminate\Support\Collection;

enum AbsenceDelayType: int
{
    case DELAY = 1;
    case ABSENCE = 2;
    case ABONO = 3;
    case OTHER = 4;

    public function name(): string
    {
        return match ($this) {
            self::DELAY => 'Atraso',
            self::ABSENCE => 'Falta',
            self::ABONO => 'Abono',
            self::OTHER => 'Outras',
        };
    }

    public function requiresHours(): bool
    {
        return $this === self::DELAY;
    }

    /**
     * @return Collection<int, string>
     */
    public static function getDescriptiveValues(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn (AbsenceDelayType $type) => [$type->value => $type->name()]);
    }
}
