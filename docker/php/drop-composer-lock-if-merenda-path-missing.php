<?php
declare(strict_types=1);
/**
 * Build Docker: evitar PathDownloader "Source path packages/merenda/merenda-escolar is not found"
 * quando merenda nao entra no build mas composer.lock / plug-and-play.json ainda referem o pacote.
 */
$merendaEnabled = getenv('ENABLE_PACKAGE_MERENDA') === 'true';
$merendaPath = 'packages/merenda/merenda-escolar/composer.json';
$pathOk = is_file($merendaPath);

if ($merendaEnabled) {
    if (!$pathOk) {
        fwrite(STDERR, "ERRO: ENABLE_PACKAGE_MERENDA=true mas packages/merenda/merenda-escolar/composer.json nao existe.\n");
        exit(1);
    }
    exit(0);
}

// ENABLE_PACKAGE_MERENDA != true
$pnp = 'packages/plug-and-play.json';
if (!$pathOk && is_file($pnp)) {
    $raw = file_get_contents($pnp);
    $j = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($j)) {
        $changed = false;
        if (isset($j['require']['merenda/merenda-escolar'])) {
            unset($j['require']['merenda/merenda-escolar']);
            $changed = true;
        }
        if (!empty($j['repositories']) && is_array($j['repositories'])) {
            $filtered = array_values(array_filter(
                $j['repositories'],
                static function ($r): bool {
                    if (!is_array($r)) {
                        return true;
                    }
                    $url = $r['url'] ?? '';
                    if (($r['name'] ?? '') === 'merenda') {
                        return false;
                    }
                    if (is_string($url) && str_contains($url, 'merenda/merenda-escolar')) {
                        return false;
                    }

                    return true;
                }
            ));
            if (count($filtered) !== count($j['repositories'])) {
                $changed = true;
            }
            $j['repositories'] = $filtered;
        }
        if ($changed) {
            file_put_contents(
                $pnp,
                json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
            );
            fwrite(STDERR, ">> packages/plug-and-play.json: removidas referencias a merenda (path ausente).\n");
        }
    }
}

if (!$pathOk && is_file('composer.lock')) {
    $raw = file_get_contents('composer.lock');
    $lock = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($lock)) {
        exit(0);
    }
    $hasMerenda = false;
    foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $p) {
        if (($p['name'] ?? '') === 'merenda/merenda-escolar') {
            $hasMerenda = true;
            break;
        }
    }
    if ($hasMerenda) {
        unlink('composer.lock');
        fwrite(STDERR, ">> composer.lock removido: continha merenda/merenda-escolar sem path no contexto.\n");
    }
}
