<?php
$paths = array('/var/www/html/wp-content/plugins/so-short-code', '/opt/wp-local');
$count = 0;
foreach ($paths as $path) {
    $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        static function ($file) { return $file->getFilename() !== 'vendor'; }
    ));
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') { continue; }
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
        if ($code !== 0) { fwrite(STDERR, implode("\n", $output)); exit(1); }
        ++$count;
        $output = array();
    }
}
echo "PHP lint passed: {$count} files\n";
