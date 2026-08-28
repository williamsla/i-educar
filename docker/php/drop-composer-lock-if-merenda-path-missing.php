<?php
declare(strict_types=1);
/**
 * Build Docker: alinhar composer.json/lock com os path repos opcionais (merenda, despesas)
 * antes do primeiro `composer install` / `composer require` na imagem.
 */
$merendaEnabled = getenv('ENABLE_PACKAGE_MERENDA') === 'true';
$despesasEnabled = getenv('ENABLE_PACKAGE_DESPESAS') === 'true';
$merendaPath = 'packages/merenda/merenda-escolar/composer.json';
$despesasPath = 'packages/despesas-escolar/composer.json';
$merendaPathOk = is_file($merendaPath);
$despesasPathOk = is_file($despesasPath);

if ($merendaEnabled && !$merendaPathOk) {
    fwrite(STDERR, "ERRO: ENABLE_PACKAGE_MERENDA=true mas packages/merenda/merenda-escolar/composer.json nao existe.\n");
    exit(1);
}

if ($despesasEnabled && !$despesasPathOk) {
    fwrite(STDERR, "ERRO: ENABLE_PACKAGE_DESPESAS=true mas packages/despesas-escolar/composer.json nao existe.\n");
    exit(1);
}

$encodeFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

$optionalPathRepos = [
    'merenda' => [
        'enabled' => $merendaEnabled,
        'path_ok' => $merendaPathOk,
        'require' => 'merenda/merenda-escolar',
        'match' => static function (array $r): bool {
            if (($r['type'] ?? '') !== 'path') {
                return false;
            }
            if (($r['name'] ?? '') === 'merenda') {
                return true;
            }
            $norm = str_replace('\\', '/', (string) ($r['url'] ?? ''));
            $norm = preg_replace('#^\./#', '', $norm) ?? $norm;

            return str_contains($norm, 'merenda/merenda-escolar')
                || str_ends_with($norm, 'merenda/merenda-escolar')
                || preg_match('#(^|/)merenda/merenda-escolar/?$#', $norm) === 1;
        },
    ],
    'despesas' => [
        'enabled' => $despesasEnabled,
        'path_ok' => $despesasPathOk,
        'require' => 'ieducar/despesa-escolar',
        'match' => static function (array $r): bool {
            if (($r['type'] ?? '') !== 'path') {
                return false;
            }
            if (($r['name'] ?? '') === 'despesas') {
                return true;
            }
            $norm = str_replace('\\', '/', (string) ($r['url'] ?? ''));
            $norm = preg_replace('#^\./#', '', $norm) ?? $norm;

            return str_contains($norm, 'despesas-escolar')
                || str_ends_with($norm, 'despesas-escolar')
                || preg_match('#(^|/)despesas-escolar/?$#', $norm) === 1;
        },
    ],
];

$shouldKeepPathRepo = static function (array $r) use ($optionalPathRepos): bool {
    foreach ($optionalPathRepos as $cfg) {
        if (!$cfg['match']($r)) {
            continue;
        }

        return $cfg['enabled'] && $cfg['path_ok'];
    }

    return true;
};

$sanitizeComposerLike = static function (string $file, $encodeFlags, $optionalPathRepos, $shouldKeepPathRepo): bool {
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

    foreach ($optionalPathRepos as $cfg) {
        if ($cfg['enabled'] && $cfg['path_ok']) {
            continue;
        }
        $requireKey = $cfg['require'] ?? null;
        if ($requireKey !== null && isset($j['require'][$requireKey])) {
            unset($j['require'][$requireKey]);
            $changed = true;
        }
    }

    if (!empty($j['repositories']) && is_array($j['repositories'])) {
        $before = count($j['repositories']);
        $j['repositories'] = array_values(array_filter(
            $j['repositories'],
            static function ($r) use ($shouldKeepPathRepo): bool {
                return !is_array($r) || $shouldKeepPathRepo($r);
            }
        ));
        if (count($j['repositories']) !== $before) {
            $changed = true;
        }
    }

    if ($changed) {
        file_put_contents($file, json_encode($j, $encodeFlags) . "\n");
        fwrite(STDERR, ">> {$file}: removidas referencias a path repos opcionais ausentes/desabilitados.\n");
    }

    return $changed;
};

$sanitizeComposerLike('composer.json', $encodeFlags, $optionalPathRepos, $shouldKeepPathRepo);
$sanitizeComposerLike('packages/plug-and-play.json', $encodeFlags, $optionalPathRepos, $shouldKeepPathRepo);

if (is_file('composer.lock')) {
    $raw = file_get_contents('composer.lock');
    $lock = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($lock)) {
        $stalePackages = [];
        if (!$merendaEnabled || !$merendaPathOk) {
            $stalePackages[] = 'merenda/merenda-escolar';
        }
        if (!$despesasEnabled || !$despesasPathOk) {
            $stalePackages[] = 'ieducar/despesa-escolar';
        }
        $removeLock = false;
        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $p) {
            if (in_array($p['name'] ?? '', $stalePackages, true)) {
                $removeLock = true;
                break;
            }
        }
        if ($removeLock) {
            unlink('composer.lock');
            fwrite(STDERR, ">> composer.lock removido: continha pacotes path opcionais sem pasta no contexto.\n");
        }
    }
}
