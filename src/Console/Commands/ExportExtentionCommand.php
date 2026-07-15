<?php

namespace ShieldPpPayment\Console\Commands;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ShieldPpPayment\Console\Commands\Base\Command;
use ShieldPpPayment\Library\CsPluginConfig;
use ZipArchive;

class ExportExtentionCommand extends Command {
  protected string $signature = 'export {environment}';
  protected string $description = 'Export extension file';

  public function handle(array $args = []): void {
    $start = microtime(true);

    function color($text, $color) {
      $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
      ];
      return ($colors[$color] ?? '') . $text . "\033[0m";
    }

    $srcDir = CsPluginConfig::get('plugin.plugin_startup_file');
    $rawPluginDir = plugin_dir_path($srcDir);
    $rootPath = rtrim($rawPluginDir, '/\\');;
    $exportPath = CsPluginConfig::get('app.export_path');
    if (!$exportPath) {
      $exportPath = $rootPath;
    }
    $zipName = 'pppayments.zip';
    $zipFile = $exportPath . '/' . $zipName;

    $envMode = $args['environment'] ?? 'production';
    $selectedEnvFile = ".env.$envMode";
    $selectedEnvPath = "$rootPath/$selectedEnvFile";
    if (!is_file($selectedEnvPath)) {
      exit(color("Selected env file not found: {$selectedEnvFile}\n", 'red'));
    }
    $exclude = ['.git', 'certs', 'node_modules', $zipName];

    echo color("Starting archive...\n", 'green1');
    echo 'Env source: ' . color("$selectedEnvFile\n", 'yellow');

    $zip = new ZipArchive();
    if (
      $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true
    ) {
      exit(color("Cannot create zip file\n", 'red'));
    }

    $rootLength = \strlen($rootPath) + 1;

    $t = microtime(true);
    $directory = new RecursiveDirectoryIterator(
      $rootPath,
      RecursiveDirectoryIterator::SKIP_DOTS,
    );

    $filter = new RecursiveCallbackFilterIterator($directory, function (
      $current,
    ) use ($exclude) {
      $name = $current->getFilename();
      if (\in_array($name, $exclude, true)) {
        return false;
      }
      if (str_starts_with($name, '.env')) {
        return false;
      }
      return true;
    });

    $files = new RecursiveIteratorIterator(
      $filter,
      RecursiveIteratorIterator::SELF_FIRST,
    );

    echo 'Iterator ready in ' .
      color(round(microtime(true) - $t, 4) . "s\n", 'green1');

    $totalSize = 0;
    $fileCount = 0;
    $dirCount = 0;
    $t = microtime(true);

    foreach ($files as $file) {
      $filePath = $file->getPathname();
      $relativePath = substr($filePath, $rootLength);

      if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
        $dirCount++;
      } else {
        $zip->addFile($filePath, $relativePath);
        $fileCount++;
        $totalSize += $file->getSize();
      }
    }
    $filesToInclude = [
      [
        'source' => "$rootPath/$selectedEnvFile",
        'zipName' => '.env',
        'warning' => "selected env file not found: $selectedEnvFile",
      ],
      [
        'source' => "$rootPath/.env.example",
        'zipName' => '.env.example',
        'warning' => '.env.example not found',
      ],
    ];

    foreach ($filesToInclude as $file) {
      $filePath = $file['source'];
      $fileCount++;
      $totalSize += filesize($filePath);
      if (!$zip->addFile($file['source'], $file['zipName'])) {
        $zip->close();
        exit(color("Erro: Failed to add {$file['zipName']} to zip\n", 'red'));
      }
    }
    echo 'Files processed in ' .
      color(round(microtime(true) - $t, 4) . "s\n", 'green1');
    $t = microtime(true);
    $zip->close();
    echo 'Zip finalized in ' .
      color(round(microtime(true) - $t, 4) . "s\n", 'green');

    $total = round(microtime(true) - $start, 4);
    $formatTotalFileSize = $this->formatSize($totalSize);
    $zipFileSize = $this->formatSize(filesize($zipFile));

    echo "\n";
    echo "Summary\n";
    echo "-------\n";
    echo 'Files: ' . color($fileCount, 'green') . "\n";
    echo 'Directories: ' . color($dirCount, 'green') . "\n";
    echo 'Files size: ' . color($formatTotalFileSize, 'green') . "\n";
    echo 'Zip file size: ' . color($zipFileSize, 'green') . "\n";
    echo 'Total time: ' . color("{$total}s", 'green') . "\n";
    echo 'Archive created: ' . color(basename($zipFile) . "\n", 'green1');
  }

  private function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
  }
}
