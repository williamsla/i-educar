<?php

namespace App\Console\Commands;

use App\Services\SchoolHistory\SchoolHistoryAutoProcessService;
use App\Support\Database\Connections;
use App_Model_MatriculaSituacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessSchoolHistoryQueueCommand extends Command
{
    use Connections;

    protected $signature = 'historico:processar-automatico
        {--limite= : Máximo de itens da fila processados nesta execução}
        {--matricula=* : Enfileira e processa matrícula(s) específica(s)}
        {--ano= : Enfileira as matrículas do ano (Cursando, Transferido, Aprovado, Retido, etc.) que ainda não têm histórico ou cujo histórico está com situação diferente}
        {--escola= : Restringe o --ano a uma escola}
        {--conexao=* : Conexão(ões) de banco a processar (padrão: conexão atual)}
        {--todas-conexoes : Processa todas as conexões (multi-cidade)}';

    protected $description = 'Processa a fila de processamento automático do histórico escolar';

    public function handle(SchoolHistoryAutoProcessService $service): int
    {
        if (!$service->isEnabled()) {
            $this->warn('Processamento automático de histórico desativado (HISTORICO_AUTO_PROCESSAR=false).');

            return self::SUCCESS;
        }

        $limite = (int) ($this->option('limite') ?: config('school_history.auto_process.command_batch_size', 500));
        $original = DB::getDefaultConnection();
        $falhou = false;

        foreach ($this->connectionsToProcess() as $connection) {
            try {
                DB::setDefaultConnection($connection);

                if (!$service->queueIsAvailable()) {
                    $this->line("[$connection] fila inexistente (rode as migrations), ignorado.");

                    continue;
                }

                $enfileiradas = $this->enqueueRequested($service);

                $resumo = $service->processPending($limite);
                $removidos = $service->prune();

                $texto = collect($resumo)->map(fn ($qtd, $status) => "$status: $qtd")->implode(', ') ?: 'nada pendente';
                $this->info("[$connection] {$texto}" . ($enfileiradas ? " (enfileiradas agora: $enfileiradas)" : '') . ($removidos ? " (antigos removidos: $removidos)" : ''));
            } catch (Throwable $e) {
                $falhou = true;
                $this->error("[$connection] {$e->getMessage()}");
                report($e);
            }
        }

        DB::setDefaultConnection($original);

        return $falhou ? self::FAILURE : self::SUCCESS;
    }

    private function connectionsToProcess(): array
    {
        if ($this->option('todas-conexoes')) {
            $connections = array_values(array_filter(
                $this->getConnections(),
                fn ($name) => config("database.connections.$name.driver") === 'pgsql' && $name !== 'idiario'
            ));

            return $connections ?: [DB::getDefaultConnection()];
        }

        return $this->option('conexao') ?: [DB::getDefaultConnection()];
    }

    private function enqueueRequested(SchoolHistoryAutoProcessService $service): int
    {
        $total = 0;

        foreach ((array) $this->option('matricula') as $matriculaId) {
            if (is_numeric($matriculaId)) {
                $service->enqueue((int) $matriculaId, 'comando');
                $total++;
            }
        }

        if ($ano = $this->option('ano')) {
            $situacoes = [
                App_Model_MatriculaSituacao::EM_ANDAMENTO,
                App_Model_MatriculaSituacao::TRANSFERIDO,
                App_Model_MatriculaSituacao::APROVADO,
                App_Model_MatriculaSituacao::REPROVADO,
                App_Model_MatriculaSituacao::REPROVADO_POR_FALTAS,
                App_Model_MatriculaSituacao::APROVADO_PELO_CONSELHO,
                App_Model_MatriculaSituacao::APROVADO_COM_DEPENDENCIA,
                App_Model_MatriculaSituacao::RECLASSIFICADO,
                App_Model_MatriculaSituacao::ABANDONO,
            ];

            $query = DB::table('pmieducar.matricula as m')
                ->select('m.cod_matricula')
                ->where('m.ativo', 1)
                ->where('m.ano', (int) $ano)
                ->whereIn('m.aprovado', $situacoes)
                ->whereNotExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('pmieducar.historico_escolar as he')
                        ->whereColumn('he.ref_cod_matricula', 'm.cod_matricula')
                        ->where('he.ativo', 1)
                        ->whereColumn('he.aprovado', 'm.aprovado');
                });

            if ($escola = $this->option('escola')) {
                $query->where('m.ref_ref_cod_escola', (int) $escola);
            }

            foreach ($query->orderBy('m.cod_matricula')->pluck('m.cod_matricula') as $matriculaId) {
                $service->enqueue((int) $matriculaId, 'comando');
                $total++;
            }
        }

        return $total;
    }
}
