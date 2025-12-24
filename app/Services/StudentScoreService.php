<?php

namespace App\Services;

use App\Models\LegacyRegistration;
use Avaliacao_Service_Boletim;
use Exception;

class StudentScoreService
{
    /**
     * Retorna a nota de um aluno em uma etapa, disciplina e turma específicos
     *
     * @param int $registrationId ID da matrícula
     * @param int $disciplineId ID do componente curricular/disciplina
     * @param int|string $stage Etapa/bimestre (ex: 1, 2, 3, 4 ou 'Rc' para recuperação)
     * @param int|null $schoolClassId ID da turma (opcional, mas recomendado para turmas multisseriadas)
     * @return array Retorna array com informações da nota ou null se não houver nota lançada
     * @throws Exception
     */
    public function getStudentScore(int $registrationId, int $disciplineId, $stage, ?int $schoolClassId = null): ?array
    {
        $registration = LegacyRegistration::findOrFail($registrationId);

        // Se não foi informada a turma, busca a turma ativa da matrícula
        if (!$schoolClassId) {
            $enrollment = $registration->enrollments()
                ->where('ativo', 1)
                ->orderBy('sequencial', 'desc')
                ->first();

            if (!$enrollment) {
                throw new Exception("Matrícula {$registrationId} não possui enturmação ativa.");
            }

            $schoolClassId = $enrollment->ref_cod_turma;
        }

        // Prepara os parâmetros para o serviço de boletim
        $params = [
            'matricula' => $registrationId,
            'usuario' => auth()->id() ?? 1,
            'componenteCurricularId' => $disciplineId,
            'turmaId' => $schoolClassId,
        ];

        // Instancia o serviço de boletim
        $boletimService = new Avaliacao_Service_Boletim($params);

        // Normaliza a etapa para garantir comparação correta (pode ser string "1" ou int 1)
        $stageNormalized = is_numeric($stage) ? (int) $stage : $stage;

        // Recupera a nota do componente na etapa especificada
        $notaComponente = $boletimService->getNotaComponente($disciplineId, $stageNormalized);

        if (!$notaComponente) {
            return null;
        }

        // Verifica se a etapa da nota corresponde à etapa solicitada
        $notaEtapa = $notaComponente->etapa;
        if ((string) $notaEtapa !== (string) $stageNormalized) {
            return null;
        }

        // Retorna os dados formatados - apenas a nota da etapa específica
        return [
            'registration_id' => $registrationId,
            'discipline_id' => $disciplineId,
            'stage' => (string) $stageNormalized,
            'school_class_id' => $schoolClassId,
            'score' => $notaComponente->nota ? str_replace(',', '.', urldecode($notaComponente->nota)) : null,
            'original_score' => $notaComponente->notaOriginal ? str_replace(',', '.', urldecode($notaComponente->notaOriginal)) : null,
            'recovery_parallel_score' => $notaComponente->notaRecuperacaoParalela ? str_replace(',', '.', urldecode($notaComponente->notaRecuperacaoParalela)) : null,
            'recovery_specific_score' => $notaComponente->notaRecuperacaoEspecifica ? str_replace(',', '.', urldecode($notaComponente->notaRecuperacaoEspecifica)) : null,
        ];
    }

    /**
     * Retorna todas as notas de um aluno em uma disciplina para todas as etapas
     *
     * @param int $registrationId ID da matrícula
     * @param int $disciplineId ID do componente curricular/disciplina
     * @param int|null $schoolClassId ID da turma (opcional)
     * @return array Array com as notas de todas as etapas
     * @throws Exception
     */
    public function getStudentScoresByDiscipline(int $registrationId, int $disciplineId, ?int $schoolClassId = null): array
    {
        $registration = LegacyRegistration::findOrFail($registrationId);

        if (!$schoolClassId) {
            $enrollment = $registration->enrollments()
                ->where('ativo', 1)
                ->orderBy('sequencial', 'desc')
                ->first();

            if (!$enrollment) {
                throw new Exception("Matrícula {$registrationId} não possui enturmação ativa.");
            }

            $schoolClassId = $enrollment->ref_cod_turma;
        }

        $params = [
            'matricula' => $registrationId,
            'usuario' => auth()->id() ?? 1,
            'componenteCurricularId' => $disciplineId,
            'turmaId' => $schoolClassId,
        ];

        $boletimService = new Avaliacao_Service_Boletim($params);
        $notasComponentes = $boletimService->getNotasComponentes();

        if (!isset($notasComponentes[$disciplineId])) {
            return [];
        }

        $notas = [];
        foreach ($notasComponentes[$disciplineId] as $nota) {
            $notas[] = [
                'stage' => $nota->etapa,
                'score' => $nota->nota ? str_replace(',', '.', urldecode($nota->nota)) : null,
                'original_score' => $nota->notaOriginal ? str_replace(',', '.', urldecode($nota->notaOriginal)) : null,
                'recovery_parallel_score' => $nota->notaRecuperacaoParalela ? str_replace(',', '.', urldecode($nota->notaRecuperacaoParalela)) : null,
                'recovery_specific_score' => $nota->notaRecuperacaoEspecifica ? str_replace(',', '.', urldecode($nota->notaRecuperacaoEspecifica)) : null,
            ];
        }

        return $notas;
    }
}

