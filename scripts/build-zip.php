<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = '0.1.0';
$plugin = 'tropikal-connect-wordpress';
$dist = $root . '/dist';
$build = $root . '/build/' . $plugin;

remove($root . '/build');
if (! is_dir($build) && ! mkdir($build, 0775, true) && ! is_dir($build)) {
    fwrite(STDERR, "Unable to create build directory.\n");
    exit(1);
}
if (! is_dir($dist) && ! mkdir($dist, 0775, true) && ! is_dir($dist)) {
    fwrite(STDERR, "Unable to create dist directory.\n");
    exit(1);
}

$include = [
    'src',
    'vendor',
    'tropikal-connect-wordpress.php',
    'composer.json',
    'LICENSE',
    'README.md',
    'SECURITY.md',
    'CHANGELOG.md',
];

foreach ($include as $path) {
    $source = $root . '/' . $path;
    if (! file_exists($source)) {
        continue;
    }
    copyPath($source, $build . '/' . $path);
}

$zipPath = "{$dist}/{$plugin}-{$version}.zip";
@unlink($zipPath);
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Unable to create {$zipPath}.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($build, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (! $file instanceof SplFileInfo || ! $file->isFile()) {
        continue;
    }
    $relative = $plugin . '/' . substr($file->getPathname(), strlen($build) + 1);
    $zip->addFile($file->getPathname(), $relative);
}
$zip->close();

echo $zipPath . PHP_EOL;

function copyPath(string $source, string $target): void
{
    if (is_dir($source)) {
        if (! is_dir($target) && ! mkdir($target, 0775, true) && ! is_dir($target)) {
            throw new RuntimeException("Unable to create {$target}");
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($items as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }
            $destination = $target . '/' . substr($item->getPathname(), strlen($source) + 1);
            if ($item->isDir()) {
                if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
                    throw new RuntimeException("Unable to create {$destination}");
                }
            } else {
                copy($item->getPathname(), $destination);
            }
        }

        return;
    }

    $dir = dirname($target);
    if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
        throw new RuntimeException("Unable to create {$dir}");
    }
    copy($source, $target);
}

function remove(string $path): void
{
    if (! file_exists($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        if (! $item instanceof SplFileInfo) {
            continue;
        }
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}
