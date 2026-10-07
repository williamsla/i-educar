<?php

namespace Tests\Unit\Services\Presenca;

use App\Services\Presenca\IdiarioEntityMapper;
use App\Services\Presenca\MonthlyAbsenceReportException;
use App\Services\Presenca\MonthlyAbsenceReportService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonthlyAbsenceReportServiceTest extends TestCase
{
    public function test_monta_a_url_a_partir_da_base_do_diario(): void
    {
        $this->assertSame(
            'https://idiario.exemplo.gov.br/api/v2/monthly_absence_by_student_reports/report',
            MonthlyAbsenceReportService::reportUrl('https://idiario.exemplo.gov.br/')
        );
        $this->assertSame(
            'https://idiario.exemplo.gov.br/api/v2/monthly_absence_by_student_reports/report',
            MonthlyAbsenceReportService::reportUrl('https://idiario.exemplo.gov.br/api/v1')
        );
    }

    public function test_chama_o_diario_com_a_url_do_professor_e_o_token_somente_no_header(): void
    {
        config()->set('legacy.config.url_diario_professor', 'https://idiario.exemplo.gov.br');
        config()->set('legacy.apis.access_key', 'segredo');

        Http::fake([
            'https://idiario.exemplo.gov.br/*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $pdf = (new MonthlyAbsenceReportService(new IdiarioEntityMapper()))->generate([
            'cod_escola' => 12345,
            'ano' => 2026,
            'meses' => [3, 2, 2],
            'ordenar' => 'faltas',
            'cod_serie' => null,
            'cod_turma' => null,
        ]);

        $this->assertSame('%PDF-1.4', $pdf['body']);
        $this->assertSame('faltas_mensais_12345_2026.pdf', $pdf['filename']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_starts_with($request->url(), 'https://idiario.exemplo.gov.br/api/v2/monthly_absence_by_student_reports/report')
                && $request->hasHeader('token', 'segredo')
                && !str_contains($request->url(), 'segredo')
                && $data['cod_escola'] === '12345'
                && $data['meses'] === '2,3'
                && $data['ordenar'] === 'faltas'
                && $data['exibir_sem_faltas'] === 0
                && $data['locale'] === 'pt-BR'
                && !array_key_exists('serie_id', $data)
                && !array_key_exists('turma_id', $data);
        });
    }

    public function test_envia_ids_do_diario_quando_serie_e_turma_sao_informadas(): void
    {
        config()->set('legacy.config.url_diario_professor', 'https://idiario.exemplo.gov.br');
        config()->set('legacy.apis.access_key', 'segredo');

        Http::fake([
            'https://idiario.exemplo.gov.br/*' => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $mapper = new class extends IdiarioEntityMapper {
            public function gradeId(int $codSerie): ?int
            {
                return 10;
            }

            public function classroomId(int $codTurma, int $ano): ?int
            {
                return 55;
            }
        };

        (new MonthlyAbsenceReportService($mapper))->generate([
            'cod_escola' => 12345,
            'ano' => 2026,
            'meses' => [2],
            'ordenar' => 'nome',
            'exibir_sem_faltas' => true,
            'cod_serie' => 8,
            'cod_turma' => 9,
        ]);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['serie_id'] === 10 && $data['turma_id'] === 55 && $data['exibir_sem_faltas'] === 1;
        });
    }

    public function test_traduz_erro_422_do_diario(): void
    {
        config()->set('legacy.config.url_diario_professor', 'https://idiario.exemplo.gov.br');
        config()->set('legacy.apis.access_key', 'segredo');

        Http::fake([
            'https://idiario.exemplo.gov.br/*' => Http::response(
                ['errors' => ['Escola não encontrada']],
                422,
                ['Content-Type' => 'application/json']
            ),
        ]);

        $this->expectException(MonthlyAbsenceReportException::class);
        $this->expectExceptionMessage('Escola não encontrada');

        (new MonthlyAbsenceReportService(new IdiarioEntityMapper()))->generate([
            'cod_escola' => 1,
            'ano' => 2026,
            'meses' => [1],
            'ordenar' => 'nome',
            'cod_serie' => null,
            'cod_turma' => null,
        ]);
    }

    public function test_exige_url_do_diario_e_token(): void
    {
        config()->set('legacy.config.url_diario_professor', '');
        config()->set('legacy.apis.access_key', '');

        $this->expectException(MonthlyAbsenceReportException::class);
        $this->expectExceptionMessage('URL do Diário do Professor');

        (new MonthlyAbsenceReportService(new IdiarioEntityMapper()))->generate([
            'cod_escola' => 1,
            'ano' => 2026,
            'meses' => [1],
            'ordenar' => 'nome',
            'cod_serie' => null,
            'cod_turma' => null,
        ]);
    }
}
