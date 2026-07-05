<?php

use App\Models\DataSearch\StudentFilter;
use App\Models\LegacyStudent;

return new class extends clsListagem
{
    public $titulo;

    public $limite;

    public $offset;

    public $cod_inep;

    public $aluno_estado_id;

    public $cod_aluno;

    public $ref_cod_religiao;

    public $ref_usuario_exc;

    public $ref_usuario_cad;

    public $ref_idpes;

    public $ativo;

    public $nome_aluno;

    public $mat_aluno;

    public $identidade;

    public $matriculado;

    public $inativado;

    public $nome_responsavel;

    public $nome_pai;

    public $nome_mae;

    public $data_nascimento;

    public $ano;

    public $ref_cod_instituicao;

    public $ref_cod_escola;

    public $ref_cod_curso;

    public $ref_cod_serie;

    public $cpf_aluno;

    public $rg_aluno;

    public $situacao_matricula_id;

    public $incluir_inativos;

    public function Gerar()
    {
        $this->titulo = 'Aluno - Listagem';

        $configuracoes = new clsPmieducarConfiguracoesGerais;
        $configuracoes = $configuracoes->detalhe();

        foreach ($_GET as $var => $val) {
            $this->$var = ($val === '') ? null : $val;
        }

        // ===== CAMPOS DE BUSCA =====
        $this->campoNumero(nome: 'cod_aluno', campo: _cl(key: 'aluno.detalhe.codigo_aluno'), valor: $this->cod_aluno, tamanhovisivel: 20, tamanhomaximo: 9);

        if ($configuracoes['mostrar_codigo_inep_aluno']) {
            $this->campoNumero(nome: 'cod_inep', campo: 'Código INEP', valor: $this->cod_inep, tamanhovisivel: 20, tamanhomaximo: 255);
        }

        $this->campoTexto(nome: 'nome_aluno', campo: 'Nome do aluno', valor: $this->nome_aluno, tamanhovisivel: 50, tamanhomaximo: 255);
        $this->campoData(nome: 'data_nascimento', campo: 'Data de Nascimento', valor: $this->data_nascimento);
        $this->campoCpf(nome: 'cpf_aluno', campo: 'CPF', valor: $this->cpf_aluno);
        $this->campoTexto(nome: 'nome_pai', campo: 'Nome do Pai', valor: $this->nome_pai, tamanhovisivel: 50, tamanhomaximo: 255);
        $this->campoTexto(nome: 'nome_mae', campo: 'Nome da Mãe', valor: $this->nome_mae, tamanhovisivel: 50, tamanhomaximo: 255);
        $this->campoTexto(nome: 'nome_responsavel', campo: 'Nome do Responsável', valor: $this->nome_responsavel, tamanhovisivel: 50, tamanhomaximo: 255);

        // ===== CHECKBOX: INCLUIR ALUNOS INATIVOS =====
        $this->campoCheck(
            nome: 'incluir_inativos',
            campo: 'Incluir alunos inativos',
            valor: $this->incluir_inativos,
            desc: 'Mostrar também alunos com cadastro inativo (desativados)',
            dica: 'Útil para localizar alunos que foram unificados ou desativados'
        );

        $this->campoRotulo(nome: 'filtros_matricula', campo: '<b>Filtros de alunos</b>');

        $this->inputsHelper()->integer(attrName: 'ano', inputOptions: ['required' => false, 'value' => $this->ano, 'max_length' => 4, 'label_hint' => 'Retorna alunos com matrículas no ano selecionado']);
        $this->inputsHelper()->dynamic(helperNames: 'instituicao', inputOptions: ['required' => false, 'value' => $this->ref_cod_instituicao]);
        $this->inputsHelper()->dynamic(helperNames: 'escolaSemFiltroPorUsuario', inputOptions: ['required' => false, 'value' => $this->ref_cod_escola, 'label_hint' => 'Retorna alunos com matrículas na escola selecionada']);
        $this->inputsHelper()->dynamic(helperNames: 'curso', inputOptions: ['required' => false, 'label_hint' => 'Retorna alunos com matrículas no curso selecionado']);
        $this->inputsHelper()->dynamic(helperNames: 'serie', inputOptions: ['required' => false, 'label_hint' => 'Retorna alunos com matrículas na série selecionada']);

        $obj_permissoes = new clsPermissoes;
        $cod_escola = $obj_permissoes->getEscola(int_idpes_usuario: $this->pessoa_logada);

        if ($cod_escola) {
            $this->campoCheck(nome: 'meus_alunos', campo: 'Meus Alunos', valor: $_GET['meus_alunos']);
            if ($_GET['meus_alunos']) {
                $this->ref_cod_escola = $cod_escola;
            }
        }

        // ===== CABEÇALHOS =====
        $cabecalhos = [
            'Código Aluno',
            $configuracoes['mostrar_codigo_inep_aluno'] === 1 ? 'Código INEP' : null,
            'Nome do Aluno',
            'Nome da Mãe',
            'Nome do Responsável',
            'CPF Responsável',
            'Status',
        ];

        $this->addCabecalhos(coluna: array_filter(array: $cabecalhos));

        // ===== VALIDAÇÕES =====
        $validator_date = Validator::make(request()->only(keys: 'data_nascimento'), ['data_nascimento' => ['nullable', 'date_format:d/m/Y', 'after_or_equal:1990-01-01']]);
        if ($validator_date->fails()) {
            $this->data_nascimento = null;
        }

        $this->cod_aluno = preg_replace(pattern: '/\D/', replacement: '', subject: $this->cod_aluno);
        $this->cod_inep = preg_replace(pattern: '/\D/', replacement: '', subject: $this->cod_inep);
        $this->nome_aluno = $this->cleanNameSearch(name: $this->nome_aluno);
        $this->nome_pai = $this->cleanNameSearch(name: $this->nome_pai);
        $this->nome_mae = $this->cleanNameSearch(name: $this->nome_mae);

        // ===== FILTRO DE DADOS =====
        $dataFilter = [
            'rg' => preg_replace(pattern: '/\D/', replacement: '', subject: $this->rg_aluno),
            'year' => $this->ano,
            'cpf' => preg_replace(pattern: '/\D/', replacement: '', subject: $this->cpf_aluno),
            'inep' => $this->cod_inep,
            'grade' => $this->ref_cod_serie,
            'school' => $this->ref_cod_escola,
            'course' => $this->ref_cod_curso,
            'birthdate' => $this->data_nascimento,
            'fatherName' => $this->nome_pai,
            'motherName' => $this->nome_mae,
            'studentName' => $this->nome_aluno,
            'studentCode' => (int) $this->cod_aluno > 0 ? $this->cod_aluno : null,
            'stateNetwork' => $this->aluno_estado_id,
            'responsableName' => $this->nome_responsavel,
            'perPage' => $this->limite,
            'pageName' => $this->nome,
            'similarity' => request()->has('similaridade'),
        ];

        $this->limite = 20;
        $this->offset = ($_GET["pagina_{$this->nome}"]) ? $_GET["pagina_{$this->nome}"] * $this->limite - $this->limite : 0;

        // ===== CONSULTA =====
        $studentFilter = new StudentFilter(...$dataFilter);

        // ===== MÉTODO CORRIGIDO =====
        // Em vez de usar findStudentWithMultipleSearch (que já aplica ->active()),
        // vamos construir a query manualmente para ter controle sobre o filtro de ativo
        $query = LegacyStudent::query()
            ->with([
                'individual' => function ($query) {
                    $query->select(['idpes', 'idpes_mae', 'idpes_pai', 'nome_social', 'idpes_responsavel']);
                    $query
                        ->with('father:nome,idpes', 'father.individual:cpf,idpes')
                        ->with('mother:nome,idpes', 'mother.individual:cpf,idpes')
                        ->with('responsible:nome,idpes', 'responsible.individual:cpf,idpes');
                },
                'person:idpes,nome',
                'inep:cod_aluno,cod_aluno_inep',
            ])
            ->filter([
                'student' => $studentFilter->studentCode,
                'student_name' => !$studentFilter->similarity ? $studentFilter->studentName : null,
                'student_name_similarity' => $studentFilter->similarity ? $studentFilter->studentName : null,
                'mother_name' => $studentFilter->motherName,
                'father_name' => $studentFilter->fatherName,
                'guardian_name' => $studentFilter->responsableName,
                'inep' => $studentFilter->inep,
                'cpf' => $studentFilter->cpf,
                'rg' => $studentFilter->rg,
                'state_network' => $studentFilter->stateNetwork,
                'birthdate' => $studentFilter->birthdate,
                'registration' => [
                    'grade' => $studentFilter->grade,
                    'course' => $studentFilter->course,
                    'school' => $studentFilter->school,
                    'year' => $studentFilter->year,
                ],
            ]);

        // ===== FILTRO DE ATIVO/INATIVO =====
        if (!$this->incluir_inativos) {
            $query->where('aluno.ativo', 1);
        }
        // Se incluir inativos, NÃO aplica o filtro de ativo

        // ===== ORDENAÇÃO =====
        if ($studentFilter->similarity) {
            $query->join('cadastro.pessoa', 'pessoa.idpes', '=', 'aluno.ref_idpes');
            $query->orderByRaw('LEVENSHTEIN(UPPER(nome), UPPER(?), 1, 0, 4), nome ASC', $studentFilter->studentName);
        } else {
            $query->orderBy('data_cadastro', 'desc');
        }

        // ===== PAGINAÇÃO =====
        $students = $query->paginate(
            $this->limite,
            ['ref_idpes', 'cod_aluno', 'tipo_responsavel'],
            'pagina_' . $this->nome
        );

        // ===== EXIBIÇÃO =====
        foreach ($students as $student) {
            $nomeAluno = $student->person->name ?? '-';
            $nomeSocial = $student->individual->nome_social ?? null;

            if ($nomeSocial) {
                $nomeAluno = $nomeSocial . '<br> <i>Nome de registro: </i>' . $nomeAluno;
            }

            $nomeResponsavel = mb_strtoupper(string: $student->getGuardianName() ?? '-');
            $cpfResponsavel = ucfirst(string: $student->getGuardianCpf());
            $nomeMae = mb_strtoupper(string: $student->individual->mother->name ?? '-');

            $statusAluno = ($student->ativo ?? 0) == 1 
                ? '<span style="color: green; font-weight: bold;">✅ Ativo</span>' 
                : '<span style="color: red; font-weight: bold;">❌ Inativo</span>';

            $linhas = array_filter(array: [
                "<a href=\"educar_aluno_det.php?cod_aluno={$student->cod_aluno}\">{$student->cod_aluno}</a>",
                $configuracoes['mostrar_codigo_inep_aluno'] === 1 ? "<a href=\"educar_aluno_det.php?cod_aluno={$student->cod_aluno}\">" . ($student->inepNumber ?? '-') . '</a>' : null,
                "<a href=\"educar_aluno_det.php?cod_aluno={$student->cod_aluno}\">{$nomeAluno}</a>",
                "<a href=\"educar_aluno_det.php?cod_aluno={$student->cod_aluno}\">{$nomeMae}</a>",
                "<a href=\"educar_aluno_det.php?cod_aluno={$student->cod_aluno}\">{$nomeResponsavel}</a>",
                "<a href=\"educar_aluno_det.php?cod_aluno={$student->cod_aluno}\">{$cpfResponsavel}</a>",
                $statusAluno,
            ]);

            $this->addLinhas(linha: $linhas);
        }

        // ===== MENSAGEM VAZIA =====
        if ($students->isEmpty()) {
            $this->addLinhas(linha: [
                '<td colspan="7" style="text-align: center; padding: 20px; color: #666;">',
                'Não há informação para ser apresentada',
                '</td>'
            ]);
        }

        // ===== PAGINADOR =====
        $this->addPaginador2(
            strUrl: 'educar_aluno_lst.php', 
            intTotalRegistros: $students->total(), 
            mixVariaveisMantidas: $_GET, 
            nome: $this->nome, 
            intResultadosPorPagina: $this->limite
        );

        // ===== BOTÃO NOVO =====
        $bloquearCadastroAluno = dbBool(val: $configuracoes['bloquear_cadastro_aluno']);
        $usuarioTemPermissaoCadastro = $obj_permissoes->permissao_cadastra(int_processo_ap: 578, int_idpes_usuario: $this->pessoa_logada, int_soma_nivel_acesso: 7);
        $usuarioPodeCadastrar = $usuarioTemPermissaoCadastro && $bloquearCadastroAluno == false;

        if ($usuarioPodeCadastrar) {
            $this->acao = 'go("/module/Cadastro/aluno")';
            $this->nome_acao = 'Novo';
        }

        $this->largura = '100%';
        $this->breadcrumb(currentPage: 'Alunos', breadcrumbs: ['/intranet/educar_index.php' => 'Escola']);
    }

    public function Formular()
    {
        $this->title = 'Aluno';
        $this->processoAp = '578';
    }

    public function cleanNameSearch($name)
    {
        return trim(string: preg_replace(pattern: '/\W/', replacement: ' ', subject: limpa_acentos(str_nome: $name)));
    }
};