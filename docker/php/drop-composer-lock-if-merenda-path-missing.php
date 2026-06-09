<?php
declare(strict_types=1);
/**
 * Build Docker: evitar PathRepository "url ... packages/merenda/merenda-escolar does not exist"
 * quando Merenda nao esta no disco mas composer.json / plug-and-play.json / lock ainda referem o path.
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

// ENABLE_PACKAGE_MERENDA != true — Merenda e opcional; sem pasta, nao pode haver repo path nem require.
if ($pathOk) {
    exit(0);
}

$encodeFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

$isMerendaPathRepo = static function (array $r): bool {
    if (($r['type'] ?? '') !== 'path') {
        return false;
    }
    $url = (string) ($r['url'] ?? '');
    if ($url === '') {
        return false;
    }
    if (($r['name'] ?? '') === 'merenda') {
        return true;
    }
    // Normaliza ./ e barras
    $norm = str_replace('\\', '/', $url);
    $norm = preg_replace('#^\./#', '', $norm) ?? $norm;

    return str_contains($norm, 'merenda/merenda-escolar')
        || str_ends_with($norm, 'merenda/merenda-escolar')
        || preg_match('#(^|/)merenda/merenda-escolar/?$#', $norm) === 1;
};

$sanitizeComposerLike = static function (string $file, $encodeFlags, $isMerendaPathRepo): bool {
    if (!is_file($file)) {
        return false;
    }
    $raw = file_get_contents($file);
    if (!is_string($raw)) {
        return false;
    }
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        return false;
    }
    $changed = false;
    if (isset($j['require']['merenda/merenda-escolar'])) {
        unset($j['require']['merenda/merenda-escolar']);
        $changed = true;
    }
    if (!empty($j['repositories']) && is_array($j['repositories'])) {
        $before = count($j['repositories']);
        $j['repositories'] = array_values(array_filter(
            $j['repositories'],
            static function ($r) use ($isMerendaPathRepo): bool {
                return !is_array($r) || !$isMerendaPathRepo($r);
            }
        ));
        if (count($j['repositories']) !== $before) {
            $changed = true;
        }
    }
    if ($changed) {
        file_put_contents($file, json_encode($j, $encodeFlags) . "\n");
        fwrite(STDERR, ">> {$file}: removidas referencias a merenda/merenda-escolar (path ausente).\n");
    }

    return $changed;
};

$sanitizeComposerLike('composer.json', $encodeFlags, $isMerendaPathRepo);
$sanitizeComposerLike('packages/plug-and-play.json', $encodeFlags, $isMerendaPathRepo);

if (is_file('composer.lock')) {
    $raw = file_get_contents('composer.lock');
    $lock = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($lock)) {
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
}
