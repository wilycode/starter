<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filtered = new RecursiveCallbackFilterIterator(
    $directory,
    static fn (SplFileInfo $file): bool => !$file->isDir()
        || !in_array($file->getFilename(), ['vendor', '.git', 'var'], true),
);
$iterator = new RecursiveIteratorIterator($filtered);
$errors = 0;
$total = 0;

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $total++;
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $output, $code);
    if ($code !== 0) {
        echo implode("\n", $output) . "\n";
        $errors++;
    }
}

printf("PHP lint: %d files, %d errors.\n", $total, $errors);
exit($errors ? 1 : 0);
