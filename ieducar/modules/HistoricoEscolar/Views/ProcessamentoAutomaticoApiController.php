<?php

if (!class_exists('ProcessamentoApiController', false)) {
    require_once __DIR__ . '/ProcessamentoApiController.php';
}

/**
 * Request isolado: lê SOMENTE os parâmetros informados.
 *
 * O CoreExt_Controller_Request padrão cai para $_GET/$_POST/$_COOKIE/$_SERVER quando
 * a chave não existe. Como o processamento automático roda ao final de outras
 * requisições (ex.: lançamento de notas, que também envia "notas", "faltas",
 * "turma_id"...), isso misturaria parâmetros. Aqui isso nunca acontece.
 */
class HistoricoEscolar_ProcessamentoAutomaticoRequest extends CoreExt_Controller_Request
{
    public function get($key)
    {
        return $this->_data[$key] ?? null;
    }
}

/**
 * Reaproveita integralmente a rotina de processamento da tela
 * "Processamento de histórico escolar" (ProcessamentoApiController::postProcessamento),
 * mas alimentada por parâmetros montados no servidor, sem saída JSON.
 *
 * Usado por App\Services\SchoolHistory\SchoolHistoryAutoProcessService.
 */
class ProcessamentoAutomaticoApiController extends ProcessamentoApiController
{
    /**
     * Parâmetros lidos via getQueryString() pelo controller original
     * (ex.: emitir_carga_disciplinas).
     *
     * @var array
     */
    protected $queryParams = [];

    /**
     * Processa (ou reprocessa) o histórico de uma matrícula.
     *
     * @param array $params      Mesmos parâmetros enviados pela tela de processamento
     * @param array $queryParams Parâmetros lidos por getQueryString()
     * @return array{sucesso: bool, mensagens: array<int, array{msg: string, type: string}>}
     */
    public function processarHistorico(array $params, array $queryParams = []): array
    {
        $this->msgs = [];
        $this->response = [];
        $this->service = null;
        unset($this->existsHistorico);

        $params['att'] = 'processamento';
        $params['oper'] = 'post';

        $this->queryParams = $queryParams;
        $this->setRequest(new HistoricoEscolar_ProcessamentoAutomaticoRequest(['data' => $params]));

        $this->postProcessamento();

        $sucesso = false;

        foreach ($this->msgs as $msg) {
            if ($msg['type'] === 'success') {
                $sucesso = true;
            }

            if ($msg['type'] === 'error') {
                $sucesso = false;
                break;
            }
        }

        return [
            'sucesso' => $sucesso,
            'mensagens' => $this->msgs,
        ];
    }

    public function getQueryString($name, $default = null)
    {
        $value = $this->queryParams[$name] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * Igual ao original, mas aceita matrícula cuja última enturmação foi encerrada por
     * transferência, remanejamento, reclassificação, abandono ou falecimento — assim o
     * aluno transferido sai com o histórico do que cursou (o boletim já aceita essas
     * enturmações em App_Model_IedFinder::getMatricula).
     */
    protected function canPostProcessamento()
    {
        $canPost = $this->validatesPresenceOfInstituicaoId(false) &&
            $this->validatesPresenceOfMatriculaId(false) &&
            $this->validatesPresenceOfDiasLetivos(false) &&
            $this->validatesPresenceAndValueInSetOfSituacao(false) &&
            $this->validatesPresenceAndValueInSetOfExtraCurricular(false) &&
            $this->validatesPresenceAndValueInDbOfGradeCursoId(false) &&
            $this->validatesPresenceAndValueOfPercentualFrequencia(false) &&
            $this->validatesPresenceAndValueOfDisciplinas(false);

        if ($canPost && $this->getRequest()->disciplinas == 'buscar-boletim') {
            $canPost = $this->validatesPresenceOfNotas(false) && $this->validatesPresenceAndValueOfFaltas(false);
        }

        $matriculaId = $this->getRequest()->matricula_id;

        if ($canPost) {
            $sql = 'select 1 from pmieducar.matricula where cod_matricula = $1 and ativo = 1';

            if (!Portabilis_Utils_Database::selectField($sql, $matriculaId)) {
                $this->appendMsg("A matricula {$matriculaId} não existe ou esta desativa", 'error');
                $canPost = false;
            }
        }

        if ($canPost && !$this->getUltimaEnturmacao($matriculaId)) {
            $this->appendMsg("A matricula {$matriculaId} não está enturmada.", 'error');
            $canPost = false;
        }

        return $canPost && $this->setService();
    }

    protected function getDadosMatricula($matriculaId)
    {
        try {
            return parent::getDadosMatricula($matriculaId);
        } catch (Exception $e) {
            // Sem enturmação ativa (ex.: transferido): usa a última enturmação
            $enturmacao = $this->getUltimaEnturmacao($matriculaId);

            if (!$enturmacao) {
                throw $e;
            }

            return [
                'ano' => $enturmacao['ano'],
                'instituicao_id' => $enturmacao['instituicao_id'],
                'escola_id' => $enturmacao['escola_id'],
                'turma_id' => $enturmacao['turma_id'],
                'nome_serie' => $this->getNomeSerie($enturmacao['serie_id']),
                'nome_curso' => $enturmacao['nome_curso'],
            ];
        }
    }

    protected function getUltimaEnturmacao($matriculaId)
    {
        $sql = '
            SELECT m.ano,
                   e.ref_cod_instituicao AS instituicao_id,
                   m.ref_ref_cod_escola AS escola_id,
                   mt.ref_cod_turma AS turma_id,
                   m.ref_ref_cod_serie AS serie_id,
                   c.nm_curso AS nome_curso
              FROM pmieducar.matricula m
              JOIN pmieducar.matricula_turma mt ON mt.ref_cod_matricula = m.cod_matricula
              JOIN pmieducar.escola e ON e.cod_escola = m.ref_ref_cod_escola
              JOIN pmieducar.curso c ON c.cod_curso = m.ref_cod_curso
             WHERE m.cod_matricula = $1
               AND (mt.ativo = 1 OR mt.transferido OR mt.remanejado OR mt.reclassificado
                    OR mt.abandono OR mt.falecido)
             ORDER BY mt.ativo DESC, mt.sequencial DESC
             LIMIT 1
        ';

        $row = Portabilis_Utils_Database::fetchPreparedQuery($sql, [
            'params' => [$matriculaId],
            'return_only' => 'first-line',
        ]);

        return is_array($row) && !empty($row) ? $row : null;
    }

    /**
     * O processamento automático pode rodar fora de uma requisição autenticada
     * (comando agendado). O serviço garante um usuário antes de chamar processar().
     */
    protected function requiresLogin($raiseExceptionOnEmpty)
    {
        return true;
    }
}
