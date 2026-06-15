<?php

namespace iEducar\Modules\Educacenso\Layout;

use App\Models\Educacenso\Registro00;
use App\Models\Educacenso\Registro10;
use App\Models\Educacenso\Registro20;

class CensoLayout2026
{
    public static function isEnabled(int $year): bool
    {
        return $year >= 2026;
    }

    public static function esferaAdministrativa(Registro00 $record)
    {
        if (!$record->esferaFederal && !$record->esferaEstadual && !$record->esferaMunicipal) {
            return '';
        }

        if ($record->esferaFederal && $record->esferaEstadual) {
            return 5;
        }

        if ($record->esferaEstadual && $record->esferaMunicipal) {
            return 4;
        }

        if ($record->esferaFederal) {
            return 1;
        }

        if ($record->esferaEstadual) {
            return 2;
        }

        return 3;
    }

    public static function tipoTurma(Registro20 $record)
    {
        $curricular = $record->curricularEtapaDeEnsino();
        $complementar = $record->atividadeComplementar();
        $aee = $record->atendimentoEducacionalEspecializado();

        if ($curricular && $complementar) {
            return 9;
        }

        if ($complementar) {
            return 4;
        }

        if ($aee) {
            return 5;
        }

        if ($curricular) {
            return 6;
        }

        return '';
    }

    public static function formaOrganizacaoTurma(Registro20 $record)
    {
        if (!$record->requereFormasOrganizacaoTurma()) {
            return '';
        }

        return $record->formasOrganizacaoTurma ?: '';
    }

    public static function equipamentosInternetAlunos(Registro10 $data)
    {
        if (!$data->usoInternetAlunos()) {
            return null;
        }

        $computadorMesa = $data->equipamentosAcessoInternetComputadorMesa();
        $dispositivosPessoais = $data->equipamentosAcessoInternetDispositivosPessoais();

        if ($computadorMesa && $dispositivosPessoais) {
            return 3;
        }

        if ($computadorMesa) {
            return 1;
        }

        if ($dispositivosPessoais) {
            return 2;
        }

        return '';
    }

    public static function redeLocal(Registro10 $data)
    {
        if (!$data->possuiComputadores() && !$data->possuiComputadoresDeMesaTabletsEPortateis()) {
            return null;
        }

        $cabo = $data->redeLocalACabo();
        $wireless = $data->redeLocalWireless();

        if ($cabo && $wireless) {
            return 3;
        }

        if ($wireless) {
            return 2;
        }

        if ($cabo) {
            return 1;
        }

        if ($data->redeLocalNenhuma()) {
            return 0;
        }

        return '';
    }

    public static function linguaMinistradaEnsino(Registro10 $data)
    {
        if (!$data->educacaoIndigena) {
            return 0;
        }

        $portugues = $data->linguaMinistradaPortugues();
        $indigena = $data->linguaMinistradaIndigena();

        if ($portugues && $indigena) {
            return 3;
        }

        if ($indigena) {
            return 1;
        }

        if ($portugues) {
            return 2;
        }

        return 0;
    }

    public static function exportaCodigosLinguaIndigena(Registro10 $data)
    {
        $lingua = self::linguaMinistradaEnsino($data);

        if (!in_array($lingua, [1, 3], true)) {
            return [null, null, null];
        }

        return [
            $data->codigoLinguaIndigena[0] ?? null,
            $data->codigoLinguaIndigena[1] ?? null,
            $data->codigoLinguaIndigena[2] ?? null,
        ];
    }
}
