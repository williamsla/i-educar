<?php

use Illuminate\Support\Facades\Log;
use JasperPHP\JasperPHP;

class Portabilis_Report_ReportFactoryPHPJasper extends Portabilis_Report_ReportFactory
{
    /**
     * Define as configurações dos relatórios.
     *
     * @param object $config
     * @return void
     */
    public function setSettings($config)
    {
        $this->settings['db'] = $config->app->database;
        $this->settings['logo_file_name'] = $config->report->logo_file_name;
    }

    /**
     * Retorna o diretório dos relatórios.
     *
     * @return string
     */
    public function getReportsPath()
    {
        return config('legacy.report.source_path');
    }

    /**
     * Retorna o arquivo da logo utilizada nos relatórios.
     *
     * @return string
     *
     * @throws CoreExt_Exception
     * @throws Exception
     */
    public function logoPath()
    {
        $logo = $this->settings['logo_file_name'];

        if (!$logo) {
            throw new Exception('No report.logo_file_name defined in configurations!');
        }

        if (filter_var($logo, FILTER_VALIDATE_URL)) {
            $tmpFile = sys_get_temp_dir() . '/logo_' . hash('sha256', $logo) . '.png';

            if (!file_exists($tmpFile)) {
                $imageData = file_get_contents($logo);
                if ($imageData === false) {
                    throw new Exception("Erro ao baixar logo da URL: $logo");
                }
                file_put_contents($tmpFile, $imageData);
            }

            return $tmpFile;
        }

        $rootPath = dirname(dirname(dirname(dirname(__FILE__))));
        $filePath = $rootPath . "/modules/Reports/ReportLogos/{$logo}";

        if (!file_exists($filePath)) {
            throw new CoreExt_Exception("Report logo '{$this->settings['logo_file_name']}' not found in path '$filePath'");
        }

        return $filePath;
    }

    /**
     * Renderiza o relatório.
     *
     * @param Portabilis_Report_ReportCore $report
     * @param array                        $options
     * @return void
     *
     * @throws Exception
     */
    public function dumps($report, $options = [])
    {
        $options = self::mergeOptions($options, [
            'add_logo_arg' => true,
        ]);

        if ($options['add_logo_arg']) {
            $report->addArg('logo', $this->logoPath());
        }

        $dataFile = $this->getReportsPath() . time() . '-' . mt_rand();
        $outputFile = $this->getReportsPath() . time() . '-' . mt_rand();
        $filename = $this->getReportsPath() . $report->templateName();
        $jasperFile = $filename . '.jasper';
        $jrxmlFile = $filename . '.jrxml';

        foreach ($report->args as $key => $value) {
            if (is_bool($value)) {
                $report->args[$key] = ($value ? 'true' : 'false');
            }
        }

        $builder = new JasperPHP;

        // Compila o arquivo .jrxml caso o arquivo .jasper não exista.

        if (file_exists($jasperFile) === false) {
            if (file_exists($jrxmlFile)) {
                $builder->compile($jrxmlFile, $filename, false, false);
                $this->jasperExecute($builder);
            } else {
                // FALLBACK PARA HTML
                return $this->renderHtmlFallback($report);
            }
        }

        // Com o intuito de manter a compatibilidade até finalizar a migração
        // de todos os relatórios será utilizado o método useJson() para
        // informar qual tipo de data source será utilizado.

        if ($report->useJson()) {
            $data = $report->getJsonData();
            $data = $report->modify($data);
            $json = json_encode($data);

            file_put_contents($dataFile, $json);

            $report->addArg('source', $dataFile);

            try{
                $builder->process(
                    $jasperFile,
                    $outputFile,
                    ['pdf'],
                    $report->args,
                    [
                        'driver' => 'json',
                        'json_query' => $report->getJsonQuery(),
                        'data_file' => $dataFile,
                    ],
                    false // Não executar em background garante que o erro será retornado
                );
                $this->jasperExecute($builder);

                unlink($dataFile);
            } catch (Exception $e) {
                error_log("JSON Data: $json");
                error_log("\n JASPER File: $jasperFile");
                error_log("\n OUTPUT File: $outputFile");
                
                error_log("Erro ao gerar relatório: " . $e->getMessage());
                throw $e;
            }

            
        } else {
            $builder->process(
                $jasperFile,
                $outputFile,
                ['pdf'],
                $report->args,
                [
                    'driver' => 'postgres',
                    'username' => $this->settings['db']->username,
                    'host' => $this->settings['db']->hostname,
                    'database' => $this->settings['db']->dbname,
                    'port' => $this->settings['db']->port,
                    'password' => $this->settings['db']->password,
                ],
                false // Não executar em background garante que o erro será retornado
            );
            $this->jasperExecute($builder);
        }

        $outputFile .= '.pdf';

        $result = file_exists($outputFile)
            ? file_get_contents($outputFile)
            : null;

        $this->destroyPDF($outputFile);

        return $result;
    }

    /**
     * Replica JasperPHP::execute() mas, em falha, junta toda a saida (nao so a 1a linha)
     * e regista o comando com senha JDBC mascarada — a lib original esconde o motivo real.
     */
    private function jasperExecute(JasperPHP $builder): void
    {
        try {
            $ref = new \ReflectionClass($builder);
            $theCommand = $ref->getProperty('the_command');
            $theCommand->setAccessible(true);
            $redirectOutput = $ref->getProperty('redirect_output');
            $redirectOutput->setAccessible(true);
            $background = $ref->getProperty('background');
            $background->setAccessible(true);
            $windows = $ref->getProperty('windows');
            $windows->setAccessible(true);

            $cmd = $theCommand->getValue($builder);
            if ($redirectOutput->getValue($builder) && ! $windows->getValue($builder)) {
                $cmd .= ' 2>&1';
            }
            if ($background->getValue($builder) && ! $windows->getValue($builder)) {
                $cmd .= ' &';
            }

            $output = [];
            $returnVar = 0;
            exec($cmd, $output, $returnVar);

            if ($returnVar !== 0) {
                $detail = trim(implode("\n", $output));
                if ($detail === '') {
                    $detail = '(nenhuma saida; verifique se exec() esta permitido no PHP e se java existe no PATH do container)';
                }

                $safeDetail = $this->redactJasperCliPasswords($detail);
                $safeCmd = $this->redactJasperCliPasswords($cmd);

                Log::error('JasperStarter falhou', [
                    'return' => $returnVar,
                    'detail' => $safeDetail,
                    'cmd' => $safeCmd,
                ]);

                throw new Exception(
                    'JasperStarter falhou (código ' . $returnVar . '): ' . mb_substr($safeDetail, 0, 4000),
                    1
                );
            }
        } catch (\ReflectionException) {
            $builder->execute();
        }
    }

    private function redactJasperCliPasswords(string $text): string
    {
        $out = preg_replace('/(\s-p\s+)(\S+)/', '$1***', $text);

        return is_string($out) ? $out : $text;
    }

    protected function renderHtmlFallback($report)
    {
        $htmlData = $report->getHtmlData();
        
        $data = [];

        // padrão antigo dos relatórios
        if (isset($htmlData['main'])) {
            $data = $htmlData['main'];
        }

        // // mantém compatibilidade
        // $data = array_merge($htmlData, $data);

        return view(
            $report->templateName(),
            $data
        )->render();
    }


    /**
     * Deleta o PDF gerado.
     *
     * @param string $file
     * @return void
     */
    public function destroyPDF($file)
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
