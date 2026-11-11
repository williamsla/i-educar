<?php

namespace App\Services\InstitutoBrasilSolidario;

use iEducar\Modules\Educacenso\Model\Deficiencias;

final class DeficienciaGrupos
{
    /**
     * @return array{motora: bool, intelectual: bool, psicossocial: bool, visual: bool, auditiva: bool}
     */
    public static function vazio(): array
    {
        return [
            'motora' => false,
            'intelectual' => false,
            'psicossocial' => false,
            'visual' => false,
            'auditiva' => false,
        ];
    }

    /**
     * @param  array{motora: bool, intelectual: bool, psicossocial: bool, visual: bool, auditiva: bool}  $flags
     */
    public static function aplicarCodigo(?int $codigo, array &$flags): void
    {
        switch ($codigo) {
            case Deficiencias::DEFICIENCIA_FISICA:
                $flags['motora'] = true;
                break;
            case Deficiencias::DEFICIENCIA_INTELECTUAL:
                $flags['intelectual'] = true;
                break;
            case Deficiencias::TRANSTORNO_ESPECTRO_AUTISTA:
                $flags['psicossocial'] = true;
                break;
            case Deficiencias::CEGUEIRA:
            case Deficiencias::BAIXA_VISAO:
            case Deficiencias::VISAO_MONOCULAR:
                $flags['visual'] = true;
                break;
            case Deficiencias::SURDEZ:
            case Deficiencias::DEFICIENCIA_AUDITIVA:
                $flags['auditiva'] = true;
                break;
            case Deficiencias::SURDOCEGUEIRA:
                $flags['visual'] = true;
                $flags['auditiva'] = true;
                break;
        }
    }

    /**
     * @param  array{motora: bool, intelectual: bool, psicossocial: bool, visual: bool, auditiva: bool}  $flags
     */
    public static function aplicarTranstorno(?int $codigo, array &$flags): void
    {
        if ($codigo !== null && $codigo >= 50 && $codigo <= 55) {
            $flags['psicossocial'] = true;
        }
    }
}
