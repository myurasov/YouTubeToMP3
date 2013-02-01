<?php

// Removes old audio files, thumbnails and temporary videos

namespace ToMP3\ConversionServer;

use ymF\CLI\CLIApplication;
use ymF\Util\Strings;
use ymF\Helper\PDOHelper;

require __DIR__ . '/../../../modules/Bootstrap.php';

$cliApp = new CLIApplication(array(
    'script_name' => \ymF\PROJECT_NAME . ' cleanup utility',
    'script_version' =>  \ymF\PROJECT_VERSION,
    'script_description' =>
      'Cleans database and disk: removes old audio files, thumbnails and temporary videos, corrupt database entries.',
  ));

$cliApp->declareParameter('temp_files_lifetime', 't',
  Config::get("CLI.cleanup.tempFilesLifetime"),
  CLIApplication::PARAM_TYPE_TIME_SEC, "Lifetime of temporary files");
$cliApp->declareParameter('max_cache_size', 'c',
  Config::get('CLI.cleanup.maxCacheSize'),
  CLIApplication::PARAM_TYPE_FLOAT, "Maximum cache size in bytes");

if ($cliApp->getParameter('?'))
{
  $cliApp->displayHelp();
  return;
}

$cliApp->onStart();

$params = $cliApp->getParameters();
$removeF = function(\RecursiveDirectoryIterator $di)
  use ($cliApp, $params) {

  $getInfoString = function($prefix, $fileName, $fileSize) {
    return "$prefix " . $fileName . " (" .
          Strings::formatFileSize($fileSize) . ")";
  };

  foreach ($di as $fileInfo)
  {
    if ($fileInfo->isDir()) continue;

    $fileAge = time() - $fileInfo->getCTime();
    $fileSize = $fileInfo->getSize();
    $fileName = $fileInfo->getFilename();

    if ($fileAge > $params['temp_files_lifetime'])
    {
      if (@unlink($fileInfo->getPathname()))
      {
        $cliApp->info($getInfoString('Removed', $fileName, $fileSize));
      }
      else
      {
        $cliApp->error($getInfoString('Failed to remove', $fileName, $fileSize));
      }
    }
    else
    {
      $cliApp->info($getInfoString('Skipped', $fileName, $fileSize));
    }
  }
};

//
// Clean temporary videos
//

$cliApp->status("Removing temporary video files...");

try
{
  if (\is_dir(Config::get('Video.tempDir')))
  {
    $di = new \RecursiveDirectoryIterator(Config::get('Video.tempDir'));
    $removeF($di);
  }
}
catch (\Exception $e)
{
  $cliApp->error($e->getMessage());
}


//
// Clean temporary thumbnails
//

$cliApp->status("Removing temporary thumbnails...");

try
{
  if (\is_dir(Config::get('Video.thumbnailsTempDir')))
  {
    $di = new \RecursiveDirectoryIterator(Config::get('Video.thumbnailsTempDir'));
    $removeF($di);
  }
}
catch (\Exception $e)
{
  $cliApp->error($e->getMessage());
}

//
// Clean old audio files
// Leave most recently accessed
//

$cliApp->status("Cleaning audio files cache...");
$maxCacheSize = $params['max_cache_size'];
$pdo = PDOHelper::getPDO(Config::get('PDOHelper'));
$sql = "SELECT id, file, thumbnailFile FROM AudioFiles ORDER BY timeLastDownloaded DESC";
$stmt = $pdo->query($sql);
$totalSize = 0;

while ($row = $stmt->fetch(\PDO::FETCH_ASSOC))
{
  if ($totalSize < $maxCacheSize)
  {
    if (\file_exists($row['file']) && \file_exists($row['thumbnailFile']))
    {
      $totalSize += \filesize($row['file']) +
        \filesize($row['thumbnailFile']);
    }
    else
    {
      $sql = "DELETE FROM AudioFiles WHERE id='{$row['id']}'";

      if (false !== $pdo->exec($sql))
        $cliApp->info("Corrupt database entry AudioFiles#{$row['id']} deleted");
      else
        $cliApp->error("Failed to delete corrupt database entry AudioFiles#{$row['id']}");
    }
  }

  if ($totalSize >= $maxCacheSize)
  {
    // Delete files

    // Audio file
    if (@\unlink($row['file'])) $cliApp->info("Deleted file " . \basename($row['file']));
    else $cliApp->error("Failed to delete file " . \basename($row['file']));

    // Thumbnail
    if (@\unlink($row['thumbnailFile'])) $cliApp->info("Deleted file " . \basename($row['thumbnailFile']));
    else $cliApp->error("Failed to delete file " . \basename($row['thumbnailFile']));

    // Database entry
    
    $sql = "DELETE FROM AudioFiles WHERE id='{$row['id']}'";

    if (false !== $pdo->exec($sql))
      $cliApp->info("Deleted database entry AudioFiles#{$row['id']}");
    else
      $cliApp->error("Failed deleting database entry AudioFiles#{$row['id']}");
  }
}

//
// Delete orphaned files
//

$cliApp->status("Deleting orphaned audio files and thumbnails...");

$deleteF = function($di, $field) use ($cliApp, $pdo, $params) {
  foreach ($di as $fileInfo)
  {
    if (!$fileInfo->isDir())
    {
      if (time() - $fileInfo->getCTime() < $params['temp_files_lifetime'])
        goto skip;

      $sql = PDOHelper::prepareSQL("SELECT id FROM AudioFiles WHERE %k=v",
        array($field => $fileInfo->getPathname()));

      if (false === $pdo->query($sql)->fetch(\PDO::FETCH_ASSOC))
      {
        if (@\unlink($fileInfo->getPathname()))
          $cliApp->info("Deleted file " . $fileInfo->getFilename());
        else
          $cliApp->error("Failed to delete file " . $fileInfo->getFilename());
      }
      else
      {
        skip:
        $cliApp->info('Skipped file ' . $fileInfo->getFilename());
      }
    }
  }
};

if (\is_dir(Config::get('AudioFile.outputDir.dir')))
{
  $di = new \RecursiveDirectoryIterator(Config::get('AudioFile.outputDir.dir'));
  $di = new \RecursiveIteratorIterator($di);
  $deleteF($di, 'file');
}
else
{
  $cliApp->info("Directory " . Config::get('AudioFile.outputDir.dir') . " doesn't exist");
}

if (\is_dir(Config::get('AudioFile.thumbnailsDir.dir')))
{
  $di = new \RecursiveDirectoryIterator(Config::get('AudioFile.thumbnailsDir.dir'));
  $di = new \RecursiveIteratorIterator($di);
  $deleteF($di, 'thumbnailFile');
}
else
{
  $cliApp->info("Directory " . Config::get('AudioFile.thumbnailsDir.dir') . " doesn't exist");
}


$cliApp->onEnd();