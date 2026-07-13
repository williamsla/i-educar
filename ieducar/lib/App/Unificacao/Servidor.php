<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class App_Unificacao_Servidor extends App_Unificacao_Base
{
    protected $chavesManterPrimeiroVinculo = [
        [
            'tabela' => 'pmieducar.servidor',
            'coluna' => 'cod_servidor',
        ],
        [
            'tabela' => 'modules.educacenso_cod_docente',
            'coluna' => 'cod_servidor',
        ],
    ];

    protected $chavesManterTodosVinculos = [
        [
            'tabela' => 'public.performance_evaluations',
            'coluna' => 'employee_id',
        ],
        [
            'tabela' => 'pmieducar.falta_atraso',
            'coluna' => 'ref_cod_servidor',
        ],
        [
            'tabela' => 'pmieducar.falta_atraso_compensado',
            'coluna' => 'ref_cod_servidor',
        ],
        [
            'tabela' => 'pmieducar.servidor_afastamento',
            'coluna' => 'ref_cod_servidor',
        ],
        [
            'tabela' => 'pmieducar.servidor_alocacao',
            'coluna' => 'ref_cod_servidor',
        ],
        [
            'tabela' => 'pmieducar.servidor_funcao',
            'coluna' => 'ref_cod_servidor',
        ],
        [
            'tabela' => 'modules.professor_turma',
            'coluna' => 'servidor_id',
        ],
        [
            'tabela' => 'pmieducar.turma',
            'coluna' => 'ref_cod_regente',
        ],
    ];

    protected $chavesDeletarDuplicados = [
        [
            'tabela' => 'pmieducar.servidor_curso_ministra',
            'coluna' => 'ref_cod_servidor',
        ],
        [
            'tabela' => 'pmieducar.servidor_disciplina',
            'coluna' => 'ref_cod_servidor',
        ],
    ];

    /**
     * {@inheritdoc}
     *
     * Garante o registro em pmieducar.servidor do unificador antes de
     * atualizar tabelas filhas (ex.: servidor_alocacao), cuja FK composta
     * (ref_cod_servidor, ref_ref_cod_instituicao) exige o pai existente.
     */
    public function unifica(): void
    {
        $this->garanteServidorDoUnificador();
        parent::unifica();
    }

    /**
     * Quando o duplicado é servidor e a pessoa principal ainda não possui
     * registro em pmieducar.servidor na mesma instituição, copia o cadastro
     * do duplicado para o código unificador.
     *
     * Sem isso, o UPDATE em servidor_alocacao (e demais filhas) falha com:
     * Key (ref_cod_servidor, ref_ref_cod_instituicao)=(X, Y) is not present in table "servidor".
     */
    protected function garanteServidorDoUnificador(): void
    {
        if (!Schema::hasTable('pmieducar.servidor') || empty($this->codigosDuplicados)) {
            return;
        }

        $stringCodigosDuplicados = implode(',', array_map('intval', $this->codigosDuplicados));
        $codigoUnificador = (int) $this->codigoUnificador;

        $colunas = $this->obterColunasServidor();

        if (empty($colunas)) {
            return;
        }

        $listaColunas = implode(', ', array_map(
            static fn (string $coluna): string => '"' . $coluna . '"',
            $colunas
        ));

        $expressoesSelect = array_map(
            static function (string $coluna) use ($codigoUnificador): string {
                if ($coluna === 'cod_servidor') {
                    return $codigoUnificador . ' AS "cod_servidor"';
                }

                return 's."' . $coluna . '"';
            },
            $colunas
        );

        $selectSql = implode(', ', $expressoesSelect);

        DB::statement(
            "INSERT INTO pmieducar.servidor ({$listaColunas})
             SELECT DISTINCT ON (s.ref_cod_instituicao) {$selectSql}
             FROM pmieducar.servidor s
             WHERE s.cod_servidor IN ({$stringCodigosDuplicados})
               AND NOT EXISTS (
                   SELECT 1
                   FROM pmieducar.servidor existente
                   WHERE existente.cod_servidor = {$codigoUnificador}
                     AND existente.ref_cod_instituicao = s.ref_cod_instituicao
               )
             ORDER BY s.ref_cod_instituicao, s.ativo DESC, s.cod_servidor"
        );
    }

    /**
     * @return list<string>
     */
    private function obterColunasServidor(): array
    {
        $colunas = DB::select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'pmieducar'
               AND table_name = 'servidor'
             ORDER BY ordinal_position"
        );

        return array_map(
            static fn ($coluna): string => $coluna->column_name,
            $colunas
        );
    }
}
