<?php

namespace App\Http\Controllers;

use App\Jobs\VerificarCpfEsusProcessJob;
use App\Process;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VerificarCpfEsusProcessController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! Gate::allows('view', Process::CONFIGURATIONS_TOOLS)) {
            return response()->json(['message' => 'Sem permissão.'], 403);
        }

        $tipoFonte = $request->input('tipo_fonte') === 'cadastro_cidadao' ? 'cadastro_cidadao' : 'esus';
        $extensoesOk = $tipoFonte === 'cadastro_cidadao' ? ['xlsx'] : ['pdf', 'csv'];

        $request->validate([
            'ano_letivo' => 'required|integer|min:1990|max:'.((int) date('Y') + 2),
            'arquivo_pdf' => 'required|file|max:20480',
            'tipo_fonte' => 'nullable|string',
        ], [
            'arquivo_pdf.required' => 'Selecione um arquivo para enviar.',
            'arquivo_pdf.max' => 'O arquivo não pode ter mais de 20 MB.',
        ]);

        $file = $request->file('arquivo_pdf');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, $extensoesOk, true)) {
            return response()->json([
                'message' => $tipoFonte === 'cadastro_cidadao'
                    ? 'Para Cadastro cidadão o arquivo deve ser do tipo XLSX.'
                    : 'Para eSUS o arquivo deve ser do tipo PDF ou CSV.',
            ], 422);
        }

        $token = (string) Str::uuid();
        $dir = storage_path('app/temp/verificar-cpf-esus');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return response()->json(['message' => 'Não foi possível preparar o armazenamento temporário.'], 500);
        }
        $storagePath = 'temp/verificar-cpf-esus/'.$token.'.'.$ext;
        if (! $file->move(storage_path('app/temp/verificar-cpf-esus'), $token.'.'.$ext)) {
            return response()->json(['message' => 'Falha ao salvar o arquivo enviado.'], 500);
        }

        $payload = [
            'status' => 'queued',
            'sucesso' => null,
            'mensagem' => 'Arquivo enfileirado. Aguarde o processamento…',
            'token' => $token,
            'user_id' => (int) $request->user()->id,
            'resultado' => null,
            'atualizado_em' => now()->toIso8601String(),
        ];
        Cache::put(VerificarCpfEsusProcessJob::cacheKey($token), $payload, now()->addHours(2));

        VerificarCpfEsusProcessJob::dispatch(
            $token,
            DB::getDefaultConnection(),
            $storagePath,
            $tipoFonte,
            $ext,
            (int) $request->input('ano_letivo'),
            $request->boolean('esus_excluir_sem_cpf_somente_cns'),
            (int) $request->user()->id,
        );

        return response()->json([
            'token' => $token,
            'status' => 'queued',
            'message' => $payload['mensagem'],
        ]);
    }

    public function status(Request $request, string $token): JsonResponse
    {
        if (! Gate::allows('view', Process::CONFIGURATIONS_TOOLS)) {
            return response()->json(['message' => 'Sem permissão.'], 403);
        }

        $payload = Cache::get(VerificarCpfEsusProcessJob::cacheKey($token));
        if (! is_array($payload) || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json(['message' => 'Processamento não encontrado.'], 404);
        }

        $filtro = VerificarCpfEsusExportController::normalizarFiltro($request->query('filtro_exibicao'));

        if (($payload['status'] ?? '') === 'done') {
            $resultado = is_array($payload['resultado'] ?? null) ? $payload['resultado'] : [];
            $sem = $resultado['cpfs_nao_cadastrados'] ?? [];
            $com = $resultado['cpfs_com_matricula'] ?? [];
            if ((! is_array($sem) || $sem === []) && (! is_array($com) || $com === [])) {
                VerificarCpfEsusExportController::limparExportacao();
            } else {
                VerificarCpfEsusExportController::armazenarParaExportacao(
                    (int) ($resultado['cpfs_extraidos'] ?? 0),
                    (int) ($resultado['ano_letivo'] ?? date('Y')),
                    is_array($sem) ? $sem : [],
                    (bool) ($resultado['excluir_sem_cpf_somente_cns'] ?? false),
                    is_array($com) ? $com : [],
                    $filtro,
                    (string) ($resultado['tipo_fonte'] ?? 'esus')
                );
            }
        }

        return response()->json([
            'token' => $token,
            'status' => $payload['status'] ?? 'unknown',
            'sucesso' => $payload['sucesso'] ?? null,
            'mensagem' => $payload['mensagem'] ?? '',
            'resultado' => $payload['resultado'] ?? null,
            'filtro_exibicao' => $filtro,
            'export_url' => url('/relatorios/verificar-cpf-esus/exportar').'?filtro='.urlencode($filtro),
        ]);
    }
}
