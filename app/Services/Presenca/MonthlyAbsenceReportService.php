<?php

namespace App\Services\Presenca;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MonthlyAbsenceReportService
{
    public function __construct(private IdiarioEntityMapper $mapper)
    {
    }

    /**
     * @param  array{cod_escola:int,ano:int,meses:array<int>,ordenar:string,cod_serie:?int,cod_turma:?int}  $filters
     * @return array{body:string,filename:string}
     */
    public function generate(array $filters): array
    {
        $response = $this->request($this->query($filters));
        $status = $response['status'];
        $body = $response['body'];
        $contentType = (string) $response['content_type'];

        if ($status === 200 && str_contains($contentType, 'application/pdf') && $body !== '') {
            return [
                'body' => $body,
                'filename' => sprintf('faltas_mensais_%s_%s.pdf', $filters['cod_escola'], $filters['ano']),
            ];
        }

        if ($status === 401) {
            throw new MonthlyAbsenceReportException(
                'Token de integração inválido. A API_ACCESS_KEY do .env precisa ser igual à Chave de acesso em API de integração do i-Diário.'
            );
        }

        if ($status === 422) {
            throw new MonthlyAbsenceReportException($this->validationMessage($body));
        }

        throw new MonthlyAbsenceReportException(
            'O i-Diário não retornou o relatório. Confira a URL do Diário do Professor em Configurações Gerais.'
        );
    }

    /**
     * @param  array{cod_escola:int,ano:int,meses:array<int>,ordenar:string,cod_serie:?int,cod_turma:?int}  $filters
     * @return array<string, int|string>
     */
    public function query(array $filters): array
    {
        $meses = array_values(array_unique(array_map('intval', $filters['meses'])));
        sort($meses);

        $query = [
            'cod_escola' => (string) $filters['cod_escola'],
            'ano' => (int) $filters['ano'],
            'meses' => implode(',', $meses),
            'ordenar' => ($filters['ordenar'] ?? 'nome') === 'faltas' ? 'faltas' : 'nome',
            'locale' => 'pt-BR',
        ];

        if (!empty($filters['cod_serie'])) {
            $serieId = $this->mapper->gradeId((int) $filters['cod_serie']);

            if ($serieId === null) {
                throw new MonthlyAbsenceReportException(
                    'Não foi possível localizar a série no i-Diário. Gere o relatório sem o filtro de série ou verifique a sincronização.'
                );
            }

            $query['serie_id'] = $serieId;
        }

        if (!empty($filters['cod_turma'])) {
            $turmaId = $this->mapper->classroomId((int) $filters['cod_turma'], (int) $filters['ano']);

            if ($turmaId === null) {
                throw new MonthlyAbsenceReportException(
                    'Não foi possível localizar a turma no i-Diário. Gere o relatório sem o filtro de turma ou verifique a sincronização.'
                );
            }

            $query['turma_id'] = $turmaId;
        }

        return $query;
    }

    public static function reportUrl(string $baseUrl): string
    {
        $base = rtrim(trim($baseUrl), '/');
        $path = '/api/v2/monthly_absence_by_student_reports/report';

        if (str_ends_with($base, $path)) {
            return $base;
        }

        $base = (string) preg_replace('#/api(?:/v\d+)?$#', '', $base);

        return $base . $path;
    }

    /**
     * @param  array<string, int|string>  $query
     * @return array{status:int,body:string,content_type:string}
     */
    private function request(array $query): array
    {
        $baseUrl = trim((string) config('legacy.config.url_diario_professor'));
        $token = trim((string) config('legacy.apis.access_key'));

        if ($baseUrl === '' || $token === '') {
            throw new MonthlyAbsenceReportException(
                'Configure a URL do Diário do Professor em Configurações Gerais e a API_ACCESS_KEY no .env.'
            );
        }

        try {
            $response = Http::withHeaders([
                'token' => $token,
                'Accept' => 'application/pdf',
            ])->timeout(120)->connectTimeout(15)->get(self::reportUrl($baseUrl), $query);
        } catch (ConnectionException) {
            throw new MonthlyAbsenceReportException(
                'Não foi possível conectar ao i-Diário. Verifique a URL do Diário do Professor em Configurações Gerais.'
            );
        }

        return [
            'status' => $response->status(),
            'body' => $response->body(),
            'content_type' => $response->header('Content-Type') ?? '',
        ];
    }

    private function validationMessage(string $body): string
    {
        $decoded = json_decode($body, true);
        $errors = is_array($decoded) ? ($decoded['errors'] ?? null) : null;

        if (is_array($errors)) {
            $errors = implode(' ', array_map('strval', $errors));
        }

        if (is_string($errors) && $errors !== '') {
            return $errors;
        }

        return 'O i-Diário recusou os filtros informados.';
    }
}
