<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudentScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentScoreController extends Controller
{
    public function __construct(
        private StudentScoreService $studentScoreService
    ) {
    }

    /**
     * Valida as chaves de acesso (access_key e secret_key)
     *
     * @param Request $request
     * @return bool
     */
    protected function validatesAccessKey(Request $request): bool
    {
        $accessKey = $request->get('access_key');
        $secretKey = $request->get('secret_key');

        $validAccessKey = config('legacy.apis.access_key');
        $validSecretKey = config('legacy.apis.secret_key');

        if (!$accessKey || !$secretKey) {
            return false;
        }

        return $accessKey === $validAccessKey && $secretKey === $validSecretKey;
    }

    /**
     * Retorna a nota de um aluno em uma etapa, disciplina e turma específicos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getScore(Request $request): JsonResponse
    {
        // Valida as chaves de acesso
        if (!$this->validatesAccessKey($request)) {
            return response()->json([
                'message' => 'Chave de acesso inválida!',
                'data' => null,
            ], 401);
        }

        $request->validate([
            'registration_id' => 'required|integer',
            'discipline_id' => 'required|integer',
            'stage' => 'required|string',
            'school_class_id' => 'nullable|integer',
        ]);

        try {
            $score = $this->studentScoreService->getStudentScore(
                $request->registration_id,
                $request->discipline_id,
                $request->stage,
                $request->school_class_id
            );

            if (!$score) {
                return response()->json([
                    'message' => 'Nota não encontrada para os parâmetros informados.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'message' => 'Nota recuperada com sucesso.',
                'data' => $score,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao recuperar nota: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Retorna todas as notas de um aluno em uma disciplina
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getScoresByDiscipline(Request $request): JsonResponse
    {
        // Valida as chaves de acesso
        if (!$this->validatesAccessKey($request)) {
            return response()->json([
                'message' => 'Chave de acesso inválida!',
                'data' => null,
            ], 401);
        }

        $request->validate([
            'registration_id' => 'required|integer',
            'discipline_id' => 'required|integer',
            'school_class_id' => 'nullable|integer',
        ]);

        try {
            $scores = $this->studentScoreService->getStudentScoresByDiscipline(
                $request->registration_id,
                $request->discipline_id,
                $request->school_class_id
            );

            return response()->json([
                'message' => 'Notas recuperadas com sucesso.',
                'data' => $scores,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao recuperar notas: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}

