<?php

class ProcessamentoController extends Portabilis_Controller_Page_ListController
{
    protected $_dataMapper = 'Avaliacao_Model_NotaAlunoDataMapper';

    protected $_titulo = 'Processamento histórico';

    protected $_processoAp = 999613;

    protected $_formMap = [];

    protected function _preRender()
    {
        $pessoa_logada = $this->pessoa_logada;

        $obj_permissao = new clsPermissoes();
        $obj_permissao->permissao_cadastra(999613, $pessoa_logada, 7, '/intranet/educar_index.php');

        parent::_preRender();

        $this->breadcrumb('Processamento de histórico escolar', [
            url('intranet/educar_index.php') => 'Escola',
        ]);
    }

    // #TODO migrar funcionalidade para novo padrão
    protected $backwardCompatibility = true;

    public function Gerar()
    {
        $styles = [
            '/vendor/legacy/HistoricoEscolar/Static/styles/processamento.css',
            '/vendor/legacy/Portabilis/Assets/Plugins/Chosen/chosen.css',
        ];
        Portabilis_View_Helper_Application::loadStylesheet($this, $styles);

        $this->inputsHelper()->dynamic(['ano', 'instituicao', 'escola']);

        $this->inputsHelper()->dynamic(
            'curso',
            [
                'required' => false,
                'label_hint' => _cl('historico.cadastro.curso_detalhe'),
            ]
        );

        $this->inputsHelper()->dynamic(
            'serie',
            [
                'required' => false,
                'label' => _cl('historico.cadastro.serie'),
            ]
        );

        $this->inputsHelper()->dynamic(['turma', 'matricula'], ['required' => false]);

        $this->campoCheck(
            'alunos_dependencia',
            'Processar somente históricos de dependências',
            null,
            null,
            false,
            false,
            false,
            'Marque esta opção para trazer somente alunos que possuem alguma dependência.'
        );

        $usaCargaHorariaComponente = [
            'rondondopara',
        ];
        $checked = true; //in_array(config('legacy.report.mostrar_relatorios'), $usaCargaHorariaComponente);
        $mensagemCh = 'Se esta opção for selecionada, passará a ser processada a CH informada no cadastro da <b>Turma</b> e/ou <b>Séries da escola</b>.';
        $this->inputsHelper()->checkbox('emitir_carga_disciplinas', ['label' => 'Usar soma das cargas horárias dos componentes como carga horária total?', 'value' => $checked, $mensagemCh]);

        $campoPosicao = '';

        if ($this->validaControlePosicaoHistorico()) {
            $campoPosicao = '
            <tr class=\'tr_posicao\'>
                <td><label for=\'posicao\'>' . 'Posição' . ' *</label><br>
                <sub style=\'vertical-align:top;\'>' . 'Informe a coluna equivalente a série/ano/etapa a qual o histórico pertence. Ex.: 1º ano informe 1, 2º ano informe 2' . '</sub></td>
                <td colspan=\'2\'><input type=\'text\' id=\'posicao\' name=\'posicao\' class=\'obrigatorio disable-on-search clear-on-change-curso validates-value-is-numeric\'></input></td>
            </tr>';
        }

        $resourceOptionsTable = "<table id='resource-options' style='padding: 20px 0;' class='styled horizontal-expand hide-on-search disable-on-apply-changes'>

            <tr>
                <td><label for='dias-letivos'>Quantidade dias letivos *</label></td>
                <td colspan='2'><input type='text' id='dias-letivos' name='quantidade-dias-letivos' class='obrigatorio disable-on-search clear-on-change-curso validates-value-is-numeric'></input></td>
            </tr>

            <tr>
                <td><label for='grade-curso'>Grade curso *</label></td>
                <td>{$this->getSelectGradeCurso()}</td>
            </tr>

            <tr>
                <td><label for='percentual-frequencia'>% Frequência *</label></td>
                <td>
                    <select id='percentual-frequencia' class='obrigatorio disable-on-search'>
                        <option value=''>Selecione</option>
                        <option value='buscar-boletim' selected>Usar do boletim</option>
                        <option value='informar-manualmente'>Informar manualmente</option>
                    </select>
                </td>
                <td><input id='percentual-frequencia-manual' name='percentual-frequencia-manual' style='display:none;'></input></td>
            </tr>

            <tr>
                <td><label for='situacao'>Situação *</label></td>
                <td colspan='2'>
                    <select id='situacao' class='obrigatorio disable-on-search'>
                        <option value=''>Selecione</option>
                        <option value='buscar-matricula' selected>Usar do boletim</option>
                        <option value='em-andamento'>Cursando</option>
                        <option value='aprovado'>Aprovado</option>
                        <option value='reprovado'>Reprovado</option>
                        <option value='transferido'>Transferido</option>
                        <option value='reclassificado'>Reclassificado</option>
                        <option value='abandono'>Abandono</option>
                    </select>
                </td>
            </tr>

            <tr>
                <td><label for='disciplinas'>Disciplinas *</label></td>
                <td>
                    <select id='disciplinas' name='disciplinas' class='obrigatorio disable-on-search'>
                        <option value=''>Selecione</option>
                        <option value='buscar-boletim' selected>Usar do boletim</option>
                        <option value='informar-manualmente'>Informar manualmente</option>
                    </select>
                </td>
                <td>
                    <table id='disciplinas-manual' style='display:none;'>
                        <tr>
                            <th>Nome</th>
                            <th>Nota</th>
                            <th>Falta</th>
                            <th>Ação</th>
                        </tr>
                        <tr class='disciplina'>
                            <td><input class='nome obrigatorio disable-on-search change-state-with-parent' style='display:none;'></input></td>
                            <td><input class='nota' ></input></td>
                            <td>
                                <input class='falta validates-value-is-numeric'></input>
                            </td>
                            <td>
                                <a class='remove-disciplina-line' href='#'>Remover</a>
                            </td>
                        </tr>
                        <tr class='actions'>
                            <td colspan='4'>
                                <input type='button' class='action' id='new-disciplina-line' name='new-line' value='Adicionar nova'></input>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td><label for='notas'>Notas *</label></td>
                <td>
                    <select id='notas' class='obrigatorio disable-on-search disable-and-hide-wen-disciplinas-manual'>
                        <option value=''>Selecione</option>
                        <option value='buscar-boletim' selected>Lançadas no boletim</option>
                        <option value='AP'>AP</option>
                        <option value='informar-manualmente'>Informar manualmente</option>
                    </select>
                </td>
                <td><input id='notas-manual' name='notas-manual' style='display:none;'></input></td>
            </tr>

            <tr>
                <td><label for='faltas'>Faltas *</label></td>
                <td>
                    <select id='faltas' class='obrigatorio disable-on-search disable-and-hide-wen-disciplinas-manual'>
                        <option value=''>Selecione</option>
                        <option value='buscar-boletim' selected>Lançadas no boletim</option>
                        <option value='informar-manualmente'>Informar manualmente</option>
                    </select>
                </td>
                <td><input id='faltas-manual' name='faltas-manual' style='display:none;'></input></td>
            </tr>

            <tr id='tr-area-conhecimento'>
                <td><label for='area-conhecimento'>Area Conhecimento </label></td>
                <td>
                    <select id='area-conhecimento' multiple class='chosen-choices' style='width: 90%' data-placeholder='Selecione'>
                    </select>
                </td>
            </tr>

            " . $campoPosicao . '

            <!--<tr>
                <td><label for=\'registro\'>Registro (arquivo)</label></td>
                <td colspan=\'2\'><input type=\'text\' id=\'registro\' name=\'registro\'></input></td>
            </tr>

            <tr>
                <td><label for=\'livro\'>Livro</label></td>
                <td colspan=\'2\'><input type=\'text\' id=\'livro\' name=\'livro\'></input></td>
            </tr>

            <tr>
                <td><label for=\'dias-letivos\'>Folha</label></td>
                <td colspan=\'2\'><input type=\'text\' id=\'folha\' name=\'folha\'></input></td>
            </tr>-->

            <tr>
                <td><label for=\'observacao\'>Observação</label></td>
                <td colspan=\'2\'><textarea id=\'observacao\' name=\'observacao\' cols=\'60\' rows=\'5\'></textarea></td>
            </tr>

            <tr>
                <td><label for=\'extra-curricular\'>Extra curricular</label></td>
                <td colspan=\'2\'><input type=\'checkbox\' id=\'extra-curricular\' name=\'extra-curricular\'></input></td>
            </tr>

            <!--<tr>
                                <td>
                                        <label for=\'media-area-conhecimento\'>Fechar média por área de conhecimento</label><br>
                                        <sub style=\'vertical-align:top;\'>Caso esse campo seja selecionado, será gerado o histórico das áreas de conhecimento e não dos componentes curriculares</sub>
                                </td>
                <td colspan=\'2\'><input type=\'checkbox\' id=\'media-area-conhecimento\' name=\'media-area-conhecimento\'></input></td>
            </tr>

            <tr>
                <td><label for=\'processar-media-geral\'>Processar média geral dos alunos</label><br></td>
                <td colspan=\'2\'><input type=\'checkbox\' id=\'processar-media-geral\' name=\'processar-media-geral\'></input></td>
            </tr>-->

        </table>';

        $this->appendOutput($resourceOptionsTable);

        $scripts = [
            '/vendor/legacy/Portabilis/Assets/Javascripts/Utils.js',
            '/vendor/legacy/Portabilis/Assets/Plugins/Chosen/chosen.jquery.min.js',
            '/vendor/legacy/Portabilis/Assets/Javascripts/Frontend/Inputs/MultipleSearch.js',
            '/vendor/legacy/Portabilis/Assets/Javascripts/Frontend/Inputs/SimpleSearch.js',
            '/vendor/legacy/HistoricoEscolar/Static/scripts/processamento.js',
        ];

        Portabilis_View_Helper_Application::loadJavascript($this, $scripts);
    }

    public function getSelectGradeCurso()
    {
        $db = new clsBanco();
        $sql = 'select * from pmieducar.historico_grade_curso where ativo = 1';
        $db->Consulta($sql);

        $select = '<select id=\'grade-curso\' class=\'obrigatorio disable-on-search clear-on-change-curso\'>';
        $select .= '<option value=\'\'>Selecione</option>';

        while ($db->ProximoRegistro()) {
            $record = $db->Tupla();
            $select .= "<option value='{$record['id']}'>{$record['descricao_etapa']}</option>";
        }

        $select .= '</select>';

        return $select;
    }

    public function validaControlePosicaoHistorico()
    {
        $obj = new clsPmieducarInstituicao;

        $lst = $obj->lista(null, null, null, null, null, null, null, null, null, null, null, null, null, 1);

        return dbBool($lst[0]['controlar_posicao_historicos']);
    }
}
