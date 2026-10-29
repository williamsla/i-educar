<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Processamento automático do histórico escolar
    |--------------------------------------------------------------------------
    |
    | Sempre que a situação de uma matrícula muda (ex.: Cursando -> Aprovado,
    | Aprovado -> Retido, etc.) uma trigger no banco coloca a matrícula na fila
    | pmieducar.historico_processamento_fila (também quando notas/faltas mudam em
    | matrícula que já tem histórico).
    |
    | A fila é consumida automaticamente ao final da própria requisição que fez a
    | alteração (tela do i-Educar OU envio do i-Diário pela API), depois que a
    | resposta já foi enviada. NÃO depende de cron nem de processamento manual.
    |
    | O comando `historico:processar-automatico` é opcional: serve para processar
    | um ano inteiro de uma vez (--ano) e, se houver cron, como reforço.
    |
    */

    'auto_process' => [

        // Liga/desliga todo o processamento automático
        'enabled' => (bool) env('HISTORICO_AUTO_PROCESSAR', true),

        // Processa itens da fila ao final das requisições web
        'on_request_terminate' => (bool) env('HISTORICO_AUTO_PROCESSAR_FIM_REQUISICAO', true),

        // Processa itens da fila em lotes deste tamanho ao final de cada requisição
        // (inclusive as requisições da API usadas pelo i-Diário)...
        'request_batch_size' => (int) env('HISTORICO_AUTO_LOTE_REQUISICAO', 5),

        // ...até esvaziar a fila ou atingir este tempo (segundos). Roda depois que a
        // resposta já foi enviada, então não deixa a tela/sincronização mais lenta.
        'request_time_budget' => (int) env('HISTORICO_AUTO_TEMPO_REQUISICAO', 25),

        // Quantos itens o comando agendado processa por execução
        'command_batch_size' => (int) env('HISTORICO_AUTO_LOTE_COMANDO', 500),

        // Agenda o comando a cada minuto (requer o cron do Laravel: `php artisan schedule:run`)
        'schedule' => (bool) env('HISTORICO_AUTO_AGENDAR', true),

        // Cria histórico novo quando ainda não existe (false = apenas reprocessa os existentes)
        'create_new' => (bool) env('HISTORICO_AUTO_CRIAR_NOVOS', true),

        // Cria/atualiza o histórico também com o aluno Cursando (histórico parcial do ano,
        // atualizado a cada lançamento). Assim, numa transferência o aluno já sai com o
        // histórico em dia. false = só cria quando a situação final for definida.
        'create_while_in_progress' => (bool) env('HISTORICO_AUTO_CRIAR_CURSANDO', true),

        // Tentativas em caso de exceção inesperada
        'max_attempts' => (int) env('HISTORICO_AUTO_TENTATIVAS', 3),

        // Itens "processando" há mais que isso são considerados travados e reenfileirados
        'stale_minutes' => (int) env('HISTORICO_AUTO_MINUTOS_TRAVADO', 15),

        // Dias que os itens finalizados ficam na fila (para consulta) antes de serem apagados
        'keep_days' => (int) env('HISTORICO_AUTO_DIAS_RETENCAO', 90),

        // Usuário usado quando não há usuário logado nem usuário registrado na alteração
        // (vazio = primeiro usuário ativo de nível Poli-institucional/Institucional)
        'user_id' => env('HISTORICO_AUTO_USUARIO_ID'),

        /*
         * Valores usados ao CRIAR um histórico novo. Ao REPROCESSAR um histórico
         * existente, os valores dele são mantidos (dias letivos, grade, observação,
         * registro/livro/folha, posição, extra curricular...).
         */
        'defaults' => [
            // "Usar soma das cargas horárias dos componentes como carga horária total?"
            'sum_components_workload' => (bool) env('HISTORICO_AUTO_SOMAR_CH_COMPONENTES', true),

            // Grade curso quando não for possível deduzir (1 = Série, 2 = Ano, 3 = EJA)
            'grade_curso_id' => (int) env('HISTORICO_AUTO_GRADE_CURSO', 2),

            // Dias letivos quando não houver etapas nem dias letivos na série
            'dias_letivos' => (int) env('HISTORICO_AUTO_DIAS_LETIVOS', 200),

            'media_area_conhecimento' => (bool) env('HISTORICO_AUTO_MEDIA_AREA_CONHECIMENTO', false),

            'processar_media_geral' => (bool) env('HISTORICO_AUTO_PROCESSAR_MEDIA_GERAL', false),
        ],
    ],

];
