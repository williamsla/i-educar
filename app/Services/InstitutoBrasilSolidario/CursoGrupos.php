<?php

namespace App\Services\InstitutoBrasilSolidario;

use Illuminate\Http\Request;

final class CursoGrupos
{
    /**
     * @var array<string, string>
     */
    public const CAMPOS = [
        'ei' => 'cursos_ei',
        'ai' => 'cursos_ai',
        'af' => 'cursos_af',
        'em' => 'cursos_em',
        'eja_ai' => 'cursos_eja_inicial',
        'eja_af' => 'cursos_eja_final',
        'eja_em' => 'cursos_eja_medio',
    ];

    /**
     * @var array<string, string>
     */
    public const ROTULOS = [
        'ei' => 'Educação Infantil',
        'ai' => 'Anos Iniciais',
        'af' => 'Anos Finais',
        'em' => 'Ensino Médio',
        'eja_ai' => 'EJA - Anos Iniciais',
        'eja_af' => 'EJA - Anos Finais',
        'eja_em' => 'EJA - Ensino Médio',
    ];

    /**
     * Grupos exibidos no formulário. Ensino Médio fica de fora.
     *
     * @var list<string>
     */
    public const GRUPOS_FORMULARIO = ['ei', 'ai', 'af', 'eja_ai', 'eja_af'];

    /**
     * @return array<int, string> curso_id => grupo
     */
    public static function mapa(Request $request): array
    {
        $mapa = [];

        foreach (self::CAMPOS as $grupo => $campo) {
            foreach ((array) $request->input($campo, []) as $id) {
                $id = (int) $id;

                if ($id > 0) {
                    $mapa[$id] = $grupo;
                }
            }
        }

        return $mapa;
    }
}
