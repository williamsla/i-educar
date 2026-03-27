<?php

use App\Models\LegacyDiscipline;
use App\Models\LegacySchoolClass;
use App\Models\LegacySchoolClassTeacher;
use iEducar\Modules\Educacenso\Model\OrganizacaoCurricular;
use iEducar\Modules\Educacenso\Model\TipoAtendimentoTurma;
use iEducar\Modules\Educacenso\Model\TipoItinerarioFormativo;
use iEducar\Modules\Servidores\Model\FuncaoExercida;
use Illuminate\Support\Facades\DB;

return new class extends clsDetalhe
{
    public $titulo;

    public $cod_turma;

    public $ref_usuario_exc;

    public $ref_usuario_cad;

    public $ref_cod_serie;

    public $ref_ref_cod_escola;

    public $nm_turma;

    public $sgl_turma;

    public $max_aluno;

    public $multiseriada;

    public $data_cadastro;

    public $data_exclusao;

    public $ativo;

    public $ref_cod_turma_tipo;

    public $hora_inicial;

    public $hora_final;

    public $hora_inicio_intervalo;

    public $hora_fim_intervalo;

    public $ref_cod_instituicao;

    public $ref_cod_curso;

    public $ref_cod_escola;

    public $visivel;

    public $tipo_atendimento;

    public $atividades_complementares;

    public $etapa_educacenso;

    public $dias_semana;

    public $codigo_inep_educacenso;

    public $organizacao_curricular;

    public $tipo_mediacao_didatico_pedagogico;

    public $tipo_boletim;

    public $tipo_boletim_diferenciado;

    public $sequencial;

    public $ref_cod_modulo;

    public $data_inicio;

    public $data_fim;

    public $dias_letivos;

    public $etapas_utilizadas;

    public $local_funcionamento_diferenciado;

    public $ano;

    public $formacao_alternancia;

    public $classe_com_lingua_brasileira_sinais;

    public $classe_especial;

    public $cod_curso_profissional;

    public $etapa_agregada;

    public $area_itinerario;

    public $tipo_curso_intinerario;

    public $cod_curso_profissional_intinerario;

    public $nao_informar_educacenso;

    public function Gerar()
    {
        $this->titulo = 'Turma - Detalhe';

        $this->cod_turma = $_GET['cod_turma'];

        $tmp_obj = new clsPmieducarTurma($this->cod_turma);
        $registro = $tmp_obj->detalhe();

        if (!$registro) {
            $this->simpleRedirect('educar_turma_lst.php');
        }

        $obj_permissoes = new clsPermissoes();
        $obj_permissoes->permissao_cadastra(586, $this->pessoa_logada, 7, 'educar_turma_lst.php');

        $obj_escola = new clsPmieducarEscola($registro['ref_ref_cod_escola']);
        $det_escola = $obj_escola->detalhe();
        $obj_serie = new clsPmieducarSerie($registro['ref_ref_cod_serie']);
        $det_serie = $obj_serie->detalhe();
        $obj_curso = new clsPmieducarCurso($det_serie['ref_cod_curso']);
        $det_curso = $obj_curso->detalhe();
        $obj_turma_tipo = new clsPmieducarTurmaTipo($registro['ref_cod_turma_tipo']);
        $det_turma_tipo = $obj_turma_tipo->detalhe();

        $this->addDetalhe(['Instituição', $det_escola['nm_instituicao']]);
        $this->addDetalhe(['Escola', $det_escola['nome']]);
        $this->addDetalhe(['Ano letivo', $registro['ano']]);
        $this->addDetalhe(['Curso', $det_curso['nm_curso']]);
        $this->addDetalhe(['Série', $det_serie['nm_serie']]);
        $this->addDetalhe(['Turma tipo', $det_turma_tipo['nm_tipo']]);
        $this->addDetalhe(['Nome da turma', $registro['nm_turma']]);
        $this->addDetalhe(['Sigla da turma', $registro['sgl_turma']]);
        $this->addDetalhe(['Máximo de alunos', $registro['max_aluno']]);
        $this->addDetalhe(['Multisseriada', $registro['multiseriada'] ? 'Sim' : 'Não']);
        $this->addDetalhe(['Ativo', $registro['visivel'] ? 'Sim' : 'Não']);

        if ($registro['tipo_mediacao_didatico_pedagogico']) {
            $this->addDetalhe(['Tipo de mediação didático pedagógico', App_Model_TipoMediacaoDidaticoPedagogico::getInstance()->getValue($registro['tipo_mediacao_didatico_pedagogico'])]);
        }

        // Horário
        $this->addDetalhe(['Hora inicial', substr($registro['hora_inicial'], 0, 5)]);
        $this->addDetalhe(['Hora final', substr($registro['hora_final'], 0, 5)]);

        if ($registro['hora_inicio_intervalo'] && $registro['hora_fim_intervalo']) {
            $this->addDetalhe(['Hora inicial do intervalo', substr($registro['hora_inicio_intervalo'], 0, 5)]);
            $this->addDetalhe(['Hora final do intervalo', substr($registro['hora_fim_intervalo'], 0, 5)]);
        }

        // Dias da semana
        $dias = [
            1 => 'Domingo',
            2 => 'Segunda-feira',
            3 => 'Terça-feira',
            4 => 'Quarta-feira',
            5 => 'Quinta-feira',
            6 => 'Sexta-feira',
            7 => 'Sábado',
        ];

        $diasSemana = transformStringFromDBInArray($registro['dias_semana']);
        $diasSemanaFormatados = [];

        if (is_array($diasSemana)) {
            foreach ($diasSemana as $dia) {
                if (isset($dias[$dia])) {
                    $diasSemanaFormatados[] = $dias[$dia];
                }
            }
        }

        if (!empty($diasSemanaFormatados)) {
            $this->addDetalhe(['Dias da semana', implode(', ', $diasSemanaFormatados)]);
        }

        // Turno
        if ($registro['turma_turno_id']) {
            $turnos = [
                1 => 'Matutino',
                2 => 'Vespertino',
                3 => 'Noturno',
                4 => 'Integral',
            ];
            $this->addDetalhe(['Turno', $turnos[$registro['turma_turno_id']] ?? '']);
        }

        // Tipo de boletim
        $tiposBoletim = Portabilis_Model_Report_TipoBoletim::getInstance()->getEnums();
        if ($registro['tipo_boletim']) {
            $this->addDetalhe(['Modelo relatório boletim', $tiposBoletim[$registro['tipo_boletim']] ?? '']);
        }

        if ($registro['tipo_boletim_diferenciado']) {
            $this->addDetalhe(['Modelo relatório boletim diferenciado', $tiposBoletim[$registro['tipo_boletim_diferenciado']] ?? '']);
        }

        // Censo
        if ($registro['codigo_inep_educacenso']) {
            $this->addDetalhe(['Código INEP', $registro['codigo_inep_educacenso']]);
        }

        $tipoAtendimento = transformStringFromDBInArray($registro['tipo_atendimento']);
        if (!empty($tipoAtendimento)) {
            $tiposAtendimento = TipoAtendimentoTurma::getDescriptiveValues();
            $tiposAtendimentoFormatados = [];
            foreach ($tipoAtendimento as $item) {
                if (isset($tiposAtendimento[$item])) {
                    $tiposAtendimentoFormatados[] = $tiposAtendimento[$item];
                }
            }
            $this->addDetalhe(['Tipo de turma', implode(', ', $tiposAtendimentoFormatados)]);
        }

        $atividadesComplementares = transformStringFromDBInArray($registro['atividades_complementares']);
        if (!empty($atividadesComplementares)) {
            $atividades = loadJson('educacenso_json/atividades_complementares.json');
            $atividadesFormatadas = [];
            foreach ($atividadesComplementares as $item) {
                if (isset($atividades[$item])) {
                    $atividadesFormatadas[] = $atividades[$item];
                }
            }
            $this->addDetalhe(['Tipos de atividades complementares', implode(', ', $atividadesFormatadas)]);
        }

        if ($registro['etapa_agregada']) {
            $etapasAgregada = loadJson('educacenso_json/etapas_agregada.json');
            $this->addDetalhe(['Etapa Agregada', $etapasAgregada[$registro['etapa_agregada']] ?? '']);
        }

        if ($registro['etapa_educacenso']) {
            $etapasEnsino = loadJson('educacenso_json/etapas_ensino.json');
            $this->addDetalhe(['Etapa de ensino', $etapasEnsino[$registro['etapa_educacenso']] ?? '']);
        }

        $organizacaoCurricular = transformStringFromDBInArray($registro['organizacao_curricular']);
        if (!empty($organizacaoCurricular)) {
            $orgs = OrganizacaoCurricular::getDescriptiveValues();
            $orgsFormatadas = [];
            foreach ($organizacaoCurricular as $item) {
                if (isset($orgs[$item])) {
                    $orgsFormatadas[] = $orgs[$item];
                }
            }
            $this->addDetalhe(['Organização curricular da turma', implode(', ', $orgsFormatadas)]);
        }

        if ($registro['formas_organizacao_turma']) {
            $formas = [
                1 => 'Série/ano (séries anuais)',
                2 => 'Períodos semestrais',
                3 => 'Ciclo(s)',
                4 => 'Grupos não seriados com base na idade ou competência',
                5 => 'Módulos',
            ];
            $this->addDetalhe(['Formas de organização da turma', $formas[$registro['formas_organizacao_turma']] ?? '']);
        }

        if ($registro['cod_curso_profissional']) {
            $cursos = loadJson('educacenso_json/cursos_da_educacao_profissional.json');
            $this->addDetalhe(['Código do curso', $cursos[$registro['cod_curso_profissional']] ?? $registro['cod_curso_profissional']]);
        }

        if ($registro['local_funcionamento_diferenciado']) {
            $locais = App_Model_LocalFuncionamentoDiferenciado::getInstance()->getEnums();
            $this->addDetalhe(['Local de funcionamento diferenciado da turma', $locais[$registro['local_funcionamento_diferenciado']] ?? '']);
        }

        if ($registro['classe_especial'] !== null) {
            $this->addDetalhe(['Turma de Educação Especial (classe especial)', $registro['classe_especial'] ? 'Sim' : 'Não']);
        }

        if ($registro['formacao_alternancia'] !== null) {
            $this->addDetalhe(['Turma de Formação por Alternância', $registro['formacao_alternancia'] ? 'Sim' : 'Não']);
        }

        if ($registro['classe_com_lingua_brasileira_sinais'] !== null) {
            $this->addDetalhe(['Turma de Educação Bilíngue de Surdos', $registro['classe_com_lingua_brasileira_sinais'] ? 'Sim' : 'Não']);
        }

        $areaItinerario = transformStringFromDBInArray($registro['area_itinerario']);
        if (!empty($areaItinerario)) {
            $areas = TipoItinerarioFormativo::getDescriptiveValues();
            $areasFormatadas = [];
            foreach ($areaItinerario as $item) {
                if (isset($areas[$item])) {
                    $areasFormatadas[] = $areas[$item];
                }
            }
            $this->addDetalhe(['Área(s) do itinerário formativo', implode(', ', $areasFormatadas)]);
        }

        if ($registro['tipo_curso_intinerario']) {
            $tipos = [
                1 => 'Curso Técnico',
                2 => 'Qualificação Profissional Técnica',
            ];
            $this->addDetalhe(['Tipo do curso do itinerário de formação técnica e profissional', $tipos[$registro['tipo_curso_intinerario']] ?? '']);
        }

        if ($registro['cod_curso_profissional_intinerario']) {
            $cursos = loadJson('educacenso_json/cursos_da_educacao_profissional.json');
            $this->addDetalhe(['Código do curso técnico', $cursos[$registro['cod_curso_profissional_intinerario']] ?? $registro['cod_curso_profissional_intinerario']]);
        }

        if ($registro['nao_informar_educacenso']) {
            $this->addDetalhe(['Não informar esta turma no Censo escolar', 'Sim']);
        }

        // Etapas da turma
        $obj_turma_modulo = new clsPmieducarTurmaModulo();
        $lst_turma_modulo = $obj_turma_modulo->lista($this->cod_turma, null, null, null, null, null, null, 'sequencial ASC');

        if (is_array($lst_turma_modulo) && count($lst_turma_modulo)) {
            $tabela = '<table class="table-default" cellpadding="0" cellspacing="0" border="0" align="left" id="table-etapas">';
            $tabela .= '<thead>';
            $tabela .= '向西';
            $tabela .= '<th>Etapa</th>';
            $tabela .= '<th>Data inicial</th>';
            $tabela .= '<th>Data final</th>';
            $tabela .= '<th>Dias letivos</th>';
            $tabela .= '</tr>';
            $tabela .= '</thead>';
            $tabela .= '<tbody>';

            $i = 1;
            foreach ($lst_turma_modulo as $etapa) {
                $tabela .= '<tr>';
                $tabela .= '<td>' . $i . 'ª etapa</td>';
                $tabela .= '<td>' . dataToBrasil($etapa['data_inicio']) . '</td>';
                $tabela .= '<td>' . dataToBrasil($etapa['data_fim']) . '</td>';
                $tabela .= '<td>' . $etapa['dias_letivos'] . '</td>';
                $tabela .= '</tr>';
                $i++;
            }

            $tabela .= '</tbody>';
            $tabela .= '</table>';

            $this->addDetalhe(['Etapas', $tabela]);
        }

        // Professores da turma
        $professores = LegacySchoolClassTeacher::query()
            ->where('turma_id', $this->cod_turma)
            ->with('employee')
            ->get();

        if ($professores->count()) {
            $tabela = '<table class="table-default" cellpadding="0" cellspacing="0" border="0" align="left" id="table-professores">';
            $tabela .= '<thead>';
            $tabela .= '<tr>';
            $tabela .= '<th>Professor</th>';
            $tabela .= '<th>Função</th>';
            $tabela .= '<th>Tipo vínculo</th>';
            $tabela .= '<th>Turno</th>';
            $tabela .= '<th>Data inicial</th>';
            $tabela .= '<th>Data final</th>';
            $tabela .= '</tr>';
            $tabela .= '</thead>';
            $tabela .= '<tbody>';

            // Função para verificar se o professor tem alerta de alocação
            $professoresComAlerta = $this->getProfessoresSemAlocacao();

            foreach ($professores as $professor) {
                $nomeProfessor = $professor->employee->nome;

                // Verifica se o professor está com alerta de alocação
                if (in_array($professor->servidor_id, $professoresComAlerta)) {
                    $nomeProfessor .= ' <span style="color: #ff6600; font-weight: bold; cursor: help;" title="⚠️ ATENÇÃO: Professor sem alocação na escola! Uma alocação foi criada automaticamente com carga horária 00:00. Recomenda-se atualizar a carga horária.">⚠️</span>';
                }

                $tabela .= '<tr>';
                $tabela .= '<td>' . $nomeProfessor . '</td>';
                $tabela .= '<td>' . ($professor->funcao_exercida ? FuncaoExercida::getDescription($professor->funcao_exercida) : '') . '</td>';
                $tabela .= '<td>' . ($professor->tipo_vinculo ? $this->getTipoVinculoDescricao($professor->tipo_vinculo) : '') . '</td>';
                $tabela .= '<td>' . ($professor->turno_id ? $this->getTurnoDescricao($professor->turno_id) : '') . '</td>';
                $tabela .= '<td>' . ($professor->data_inicial ? dataToBrasil($professor->data_inicial) : '') . '</td>';
                $tabela .= '<td>' . ($professor->data_fim ? dataToBrasil($professor->data_fim) : '') . '</td>';
                $tabela .= '</tr>';
            }

            $tabela .= '</tbody>';
            $tabela .= '</table>';

            $this->addDetalhe(['Professores', $tabela]);
        }

        // Componentes curriculares da turma
        $componentes = LegacyDiscipline::query()
            ->join('modules.componente_curricular_turma', 'componente_curricular_turma.componente_curricular_id', '=', 'modules.componente_curricular.id')
            ->where('componente_curricular_turma.turma_id', $this->cod_turma)
            ->select('modules.componente_curricular.*', 'componente_curricular_turma.carga_horaria', 'componente_curricular_turma.etapas_especificas', 'componente_curricular_turma.etapas_utilizadas')
            ->get();

        if ($componentes->count()) {
            $tabela = '<table class="table-default" cellpadding="0" cellspacing="0" border="0" align="left" id="table-componentes">';
            $tabela .= '<thead>';
            $tabela .= '<tr>';
            $tabela .= '<th>Componente curricular</th>';
            $tabela .= '<th>Carga horária</th>';
            $tabela .= '<th>Etapas específicas</th>';
            $tabela .= '<th>Etapas utilizadas</th>';
            $tabela .= '</tr>';
            $tabela .= '</thead>';
            $tabela .= '<tbody>';

            foreach ($componentes as $componente) {
                $tabela .= '<tr>';
                $tabela .= '<td>' . $componente->nome . '</td>';
                $tabela .= '<td>' . $componente->carga_horaria . '</td>';
                $tabela .= '<td>' . ($componente->etapas_especificas ? 'Sim' : 'Não') . '</td>';
                $tabela .= '<td>' . $componente->etapas_utilizadas . '</td>';
                $tabela .= '</tr>';
            }

            $tabela .= '</tbody>';
            $tabela .= '</table>';

            $this->addDetalhe(['Componentes curriculares', $tabela]);
        }

        // Alunos da turma
        $obj_matriculas_turma = new clsPmieducarMatriculaTurma();
        $obj_matriculas_turma->setOrderby('nome_aluno ASC');
        $lst_matriculas_turma = $obj_matriculas_turma->lista(
            $this->cod_turma,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            true,
            1,
            null,
            null,
            null,
            true
        );

        if (is_array($lst_matriculas_turma) && count($lst_matriculas_turma)) {
            $tabela = '<table class="table-default" cellpadding="0" cellspacing="0" border="0" align="left" id="table-alunos">';
            $tabela .= '<thead>';
            $tabela .= '<tr>';
            $tabela .= '<th>Aluno</th>';
            $tabela .= '<th>Matrícula</th>';
            $tabela .= '<th>Data enturmação</th>';
            $tabela .= '<th>Data desenturmação</th>';
            $tabela .= '</tr>';
            $tabela .= '</thead>';
            $tabela .= '<tbody>';

            foreach ($lst_matriculas_turma as $aluno) {
                $tabela .= '<tr>';
                $tabela .= '<td>' . $aluno['nome_aluno'] . '</td>';
                $tabela .= '<td>' . $aluno['cod_matricula'] . '</td>';
                $tabela .= '<td>' . dataToBrasil($aluno['data_matricula']) . '</td>';
                $tabela .= '<td>' . ($aluno['data_desmatricula'] ? dataToBrasil($aluno['data_desmatricula']) : '') . '</td>';
                $tabela .= '</tr>';
            }

            $tabela .= '</tbody>';
            $tabela .= '</table>';

            $this->addDetalhe(['Alunos', $tabela]);
        }

        $obj_permissoes = new clsPermissoes();
        if ($obj_permissoes->permissao_cadastra(586, $this->pessoa_logada, 7)) {
            $this->url_editar = 'educar_turma_cad.php?cod_turma=' . $registro['cod_turma'];
        }

        $this->url_editar = 'educar_turma_cad.php?cod_turma=' . $registro['cod_turma'];
        $this->url_cancelar = 'educar_turma_lst.php';
        $this->url_cancelar_retorno = 'educar_turma_lst.php';
        $this->largura = '100%';

        $this->breadcrumb('Detalhe da turma', [
            url('intranet/educar_index.php') => 'Escola',
        ]);
    }

    /**
     * Obtém a lista de professores que estão com alerta de alocação
     *
     * @return array
     */
    private function getProfessoresSemAlocacao()
    {
        if (session_id() == '') {
            session_start();
        }

        $professoresComAlerta = [];

        if (isset($_SESSION['alerta_professor_sem_alocacao']) && is_array($_SESSION['alerta_professor_sem_alocacao'])) {
            $professoresComAlerta = array_keys($_SESSION['alerta_professor_sem_alocacao']);
        }

        return $professoresComAlerta;
    }

    /**
     * Retorna a descrição do tipo de vínculo
     *
     * @param int $tipoVinculo
     * @return string
     */
    private function getTipoVinculoDescricao($tipoVinculo)
    {
        $tipos = [
            1 => 'Efetivo',
            2 => 'Temporário',
            3 => 'Designado',
            4 => 'Contratado',
            5 => 'Outro',
        ];

        return $tipos[$tipoVinculo] ?? '';
    }

    /**
     * Retorna a descrição do turno
     *
     * @param int $turnoId
     * @return string
     */
    private function getTurnoDescricao($turnoId)
    {
        $turnos = [
            1 => 'Matutino',
            2 => 'Vespertino',
            3 => 'Noturno',
            4 => 'Integral',
        ];

        return $turnos[$turnoId] ?? '';
    }
};
