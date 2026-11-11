<?php

/**
 * Builds the slim, self-hosted front-end assets. No Node, no Composer, no network.
 *
 *   php tools/build-assets.php            build public/css, public/js, public/icons.svg
 *   php tools/build-assets.php --check    exit 1 if the committed files are out of date
 *
 * What it does
 *   CSS   Bootstrap 5.3 + the fida-admin stylesheet + erp.css, with every rule whose
 *         classes are never used in the application removed, then minified.
 *   Icons One SVG sprite holding only the Font Awesome icons the application uses.
 *   JS    resources/assets/js/app.js with comments and indentation stripped.
 *
 * A class or icon counts as "used" when its full name appears in resources/views, app,
 * config, lang or resources/assets/js. Always write class names in full
 * (never 'badge-'.$colour) or add them to resources/assets/safelist.txt.
 */

$root = dirname(__DIR__);
$check = in_array('--check', $argv, true);

/* ------------------------------------------------------------------ used tokens */

function files_in(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile()) {
            $out[] = $file->getPathname();
        }
    }
    sort($out);

    return $out;
}

$sources = '';
foreach (['resources/views', 'app', 'config', 'lang', 'resources/assets/js'] as $dir) {
    foreach (files_in("$root/$dir") as $file) {
        $sources .= "\n".file_get_contents($file);
    }
}
$safelist = is_file("$root/resources/assets/safelist.txt") ? file_get_contents("$root/resources/assets/safelist.txt") : '';
$sources .= "\n".preg_replace('/^\s*#(\s.*)?$/m', '', $safelist);

$tokens = array_flip(preg_split('/[^A-Za-z0-9_-]+/', $sources, -1, PREG_SPLIT_NO_EMPTY));

/* ------------------------------------------------------------------- CSS purge */

function strip_comments(string $css): string
{
    return preg_replace('~/\*.*?\*/~s', '', $css);
}

/** Split CSS into top-level blocks: [prelude, body] or [statement, null]. */
function css_blocks(string $css): array
{
    $blocks = [];
    $len = strlen($css);
    $start = 0;
    $i = 0;
    $quote = null;
    while ($i < $len) {
        $c = $css[$i];
        if ($quote !== null) {
            if ($c === '\\') {
                $i += 2;

                continue;
            }
            if ($c === $quote) {
                $quote = null;
            }
            $i++;

            continue;
        }
        if ($c === '"' || $c === "'") {
            $quote = $c;
        } elseif ($c === ';') {
            $blocks[] = [trim(substr($css, $start, $i - $start)), null];
            $start = $i + 1;
        } elseif ($c === '{') {
            $prelude = trim(substr($css, $start, $i - $start));
            $depth = 1;
            $j = $i + 1;
            $q = null;
            while ($j < $len && $depth > 0) {
                $d = $css[$j];
                if ($q !== null) {
                    if ($d === '\\') {
                        $j++;
                    } elseif ($d === $q) {
                        $q = null;
                    }
                } elseif ($d === '"' || $d === "'") {
                    $q = $d;
                } elseif ($d === '{') {
                    $depth++;
                } elseif ($d === '}') {
                    $depth--;
                }
                $j++;
            }
            $blocks[] = [$prelude, substr($css, $i + 1, $j - $i - 2)];
            $i = $j;
            $start = $j;

            continue;
        }
        $i++;
    }

    return $blocks;
}

/** Split a selector list on commas that are not inside () or []. */
function split_selectors(string $list): array
{
    $out = [];
    $depth = 0;
    $buf = '';
    foreach (str_split($list) as $c) {
        if ($c === '(' || $c === '[') {
            $depth++;
        } elseif ($c === ')' || $c === ']') {
            $depth--;
        }
        if ($c === ',' && $depth === 0) {
            $out[] = trim($buf);
            $buf = '';
        } else {
            $buf .= $c;
        }
    }
    if (trim($buf) !== '') {
        $out[] = trim($buf);
    }

    return $out;
}

function selector_is_used(string $selector, array $tokens): bool
{
    $s = preg_replace('/\[[^\]]*\]/', '', $selector);           // attribute selectors
    do {                                                         // :not(...) never requires its classes
        $s = preg_replace('/:not\((?:[^()]|\([^()]*\))*\)/', '', $s, -1, $n);
    } while ($n > 0);
    preg_match_all('/\.((?:\\\\.|[A-Za-z0-9_-])+)/', $s, $m);
    foreach ($m[1] as $class) {
        if (! isset($tokens[stripslashes($class)])) {
            return false;
        }
    }

    return true;
}

function minify_body(string $body): string
{
    $body = preg_replace('/\s+/', ' ', trim($body));
    $body = preg_replace('/\s*;\s*/', ';', $body);

    return rtrim($body, ';');
}

function purge(string $css, array $tokens, array &$keyframes): string
{
    $out = '';
    foreach (css_blocks($css) as [$prelude, $body]) {
        $prelude = preg_replace('/\s+/', ' ', $prelude);
        if ($prelude === '' && $body === null) {
            continue;
        }
        if ($body === null) {
            $out .= $prelude.';';
        } elseif (preg_match('/^@(-webkit-)?keyframes\s+(\S+)/', $prelude, $m)) {
            $keyframes[$m[2]][$prelude] = $prelude.'{'.preg_replace('/\s+/', ' ', trim($body)).'}';
        } elseif (preg_match('/^@(media|supports|container|layer)\b/', $prelude)) {
            $inner = purge($body, $tokens, $keyframes);
            if ($inner !== '') {
                $out .= $prelude.'{'.$inner.'}';
            }
        } elseif ($prelude[0] === '@') {
            $out .= $prelude.'{'.minify_body($body).'}';
        } else {
            $kept = array_filter(split_selectors($prelude), fn ($s) => selector_is_used($s, $tokens));
            $min = minify_body($body);
            if ($kept && $min !== '') {
                $out .= implode(',', $kept).'{'.$min.'}';
            }
        }
    }

    return $out;
}

function build_css(array $files, array $tokens): string
{
    $keyframes = [];
    $css = '';
    foreach ($files as $file) {
        $css .= purge(strip_comments(file_get_contents($file)), $tokens, $keyframes);
    }
    foreach ($keyframes as $name => $variants) {
        if (preg_match('/[\s:,]'.preg_quote($name, '/').'[\s;,}]/', $css)) {
            $css .= implode('', $variants);
        }
    }

    return "/*! Bootstrap 5.3.3 (MIT, getbootstrap.com) + fida-admin by Fida Technologies. Built file: edit resources/assets and run php tools/build-assets.php */\n".$css."\n";
}

$assets = "$root/resources/assets";
$outputs = [
    'public/css/app.css' => build_css(["$assets/css/bootstrap.min.css", "$assets/css/fida.css", "$assets/css/erp.css"], $tokens),
    'public/css/app.rtl.css' => build_css(["$assets/css/bootstrap.rtl.min.css", "$assets/css/fida.css", "$assets/css/erp.css"], $tokens),
];

/* ----------------------------------------------------------------- icon sprite */

$library = json_decode(file_get_contents("$assets/icons/fontawesome.json"), true);
$wanted = [];
// <x-icon name="box" /> and <x-icon name="eye" set="regular" />
preg_match_all('/<x-icon\b[^>]*>/', $sources, $tags);
foreach ($tags[0] as $tag) {
    if (preg_match('/\bname="([a-z0-9-]+)"/', $tag, $n)) {
        $wanted[(str_contains($tag, 'set="regular"') ? 'r' : 's').'-'.$n[1]] = true;
    }
}
// 'icon' => 'box' (menu configuration) and icon="box" (page header and other components)
preg_match_all('/(?:[\'"]icon[\'"]\s*=>\s*[\'"]|\bicon=")([a-z0-9-]+)[\'"]/', $sources, $m);
foreach ($m[1] as $name) {
    $wanted['s-'.$name] = true;
}
// #s-sun / #r-eye referenced from JavaScript, or listed in the safelist
preg_match_all('/#([sr]-[a-z0-9-]+)\b/', $sources, $m);
foreach ($m[1] as $id) {
    $wanted[$id] = true;
}
ksort($wanted);

$sprite = '<!-- Icons: Font Awesome Free 6.5.2 by @fontawesome, https://fontawesome.com, licence CC BY 4.0 -->'
    .'<svg xmlns="http://www.w3.org/2000/svg">';
$missing = [];
foreach (array_keys($wanted) as $id) {
    [$set, $name] = explode('-', $id, 2);
    if (! isset($library[$set][$name])) {
        $missing[] = $id;

        continue;
    }
    [$w, $h, $d] = $library[$set][$name];
    $sprite .= "<symbol id=\"$id\" viewBox=\"0 0 $w $h\"><path d=\"$d\"/></symbol>";
}
$sprite .= "</svg>\n";
$outputs['public/icons.svg'] = $sprite;

/* ------------------------------------------------------------------ JavaScript */

$js = '';
foreach (preg_split('/\R/', file_get_contents("$assets/js/app.js")) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '//')) {
        continue;
    }
    $js .= $line."\n";
}
$outputs['public/js/app.js'] = $js;

/* ----------------------------------------------------------------------- write */

if ($missing) {
    fwrite(STDERR, 'Unknown icons: '.implode(', ', $missing)."\n");
    exit(1);
}

$stale = [];
foreach ($outputs as $path => $content) {
    $current = is_file("$root/$path") ? file_get_contents("$root/$path") : null;
    if ($current !== $content) {
        $stale[] = $path;
        if (! $check) {
            @mkdir(dirname("$root/$path"), 0775, true);
            file_put_contents("$root/$path", $content);
        }
    }
    printf("%-24s %7.1f KB   %6.1f KB gzipped\n", $path, strlen($content) / 1024, strlen(gzencode($content, 9)) / 1024);
}

if ($check && $stale) {
    fwrite(STDERR, "Out of date: ".implode(', ', $stale)."\nRun: php tools/build-assets.php\n");
    exit(1);
}
