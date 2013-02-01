<?php

namespace ToMP3\ConversionServer\Worker;

use Exception;
use ymF\Helper\PDOHelper;
use ymF\Helper\GearmanHelper;
use ymF\Helper\MemcachedHelper;
use ymF\Controller\APIController;
use ymF\Util\Strings;
use ToMP3\ConversionDescriptor;
use ToMP3\LinkDescriptor;
use ToMP3\ConversionServer\Video\AbstractVideo;
use ToMP3\ConversionServer\Model\AudioFile;
use ToMP3\ConversionServer\DownloadLink;
use ToMP3\ConversionServer\Config;
use ToMP3\ConversionServer\Conversion;

/**
 * Args:
 *
 * array('url', 'quality', 'format', 'clientIp', 'frontendHost')
 *
 * Informmation stored on frontend:
 *
 *  JobMetadata, JobResult
 *
 * Information sent to gearman:
 *
 *   Job status
 */
class Convert
{
  public static function run(\GearmanJob $job)
  {
    try
    {
      // Decode workload
      $workload = GearmanHelper::decodeData($job->workload());

      // Configure PDOHelper
      PDOHelper::setConfig(Config::get('PDOHelper'));

      //
      // Create conversion container
      //

      $conversion = new Conversion($workload['frontendHost'], $job->handle());
      $conversion->setVideo(AbstractVideo::createVideo($workload['url']));
      $conversion->getVideo()->setConversionQuality($workload['quality']);
      $conversion->setConversionDescriptor(ConversionDescriptor::fromAbstractVideo(
        $conversion->getVideo(), $workload['format']));
      $conversion->setAudio(AudioFile::fromWhere('%k=v', array('conversionDescriptor' =>
        (string) $conversion->getConversionDescriptor())));
      $conversion->setFormat($workload['format']);
      $conversion->setQuality($workload['quality']);

      // Audio file id
      $audioFileId = null;

      // Check if audio file already exists
      if ($conversion->getAudio() === false) // New conversion
      {
        //
        // Download video
        //

        $gearmanClient = GearmanHelper::getClient(Config::get('GearmanHelper_Local'));
        $functionName = GearmanHelper::createFunction('downloadLocal', Config::get('hostName'));
        $gearmanClient->addTask($functionName, GearmanHelper::encodeData(array('conversion' => $conversion)));

        // Status callback
        $gearmanClient->setStatusCallback(function(\GearmanTask $task) use ($job) {
          $job->sendStatus($task->taskNumerator(), $task->taskDenominator());
        });

        // Data callback
        $gearmanClient->setDataCallback(function (\GearmanTask $task)  use (&$exception) {
          $data = GearmanHelper::decodeData($task->data());

          if (is_array($data) && $data['error'] == true)
            $exception = new \Exception($data['message']); // Exception occured
        });

        // Complete callback
        $gearmanClient->setCompleteCallback(function (\GearmanTask $task) use (&$conversion, $job) {
          $conversion = GearmanHelper::decodeData($task->data());
          $job->sendStatus(100, 100); // Download done
        });

        $gearmanClient->runTasks();
        unset($gearmanClient);

        if ($exception instanceof \Exception)
          throw $exception;

        //
        // Create and save audio
        //

        $gearmanClient = GearmanHelper::getClient(Config::get('GearmanHelper_Local'));
        $functionName = GearmanHelper::createFunction('encodeLocal', Config::get('hostName'));
        $gearmanClient->addTask($functionName, GearmanHelper::encodeData(array('conversion' => $conversion)));

        // Data callback
        $gearmanClient->setDataCallback(function (\GearmanTask $task) use (&$exception) {
          $data = GearmanHelper::decodeData($task->data());

          if (is_array($data) && $data['error'] == true)
            $exception = new \Exception($data['message']); // Exception occured
        });

        // Complete callback
        $gearmanClient->setCompleteCallback(function (\GearmanTask $task) use (&$audioFileId) {
          $audioFileId = GearmanHelper::decodeData($task->data());
        });

        // Run encoding
        $gearmanClient->runTasks();
        unset($gearmanClient);

        // Cleanup temporary file for video
        $conversion->getVideo()->cleanup();

        if ($exception instanceof \Exception)
          throw $exception;
      }
      else
      {
        // From cache
        $conversion->sendMetadata(); // Send metadata to frontend
        $audioFileId = $conversion->getAudio()->id; // Get Audio file id
      }

      // Add/update record to the conversion log
      $conversionRecordId = $conversion->updateConversionRecord();

      //
      // Create and save download link
      //

      $downloadLink = new DownloadLink();
      $downloadLink->setAudioFileId($audioFileId);
      $downloadLink->setConversionRecordId($conversionRecordId);
      $downloadLink->setClientIp($workload['clientIp']);
      $downloadLink->setConversionDescriptor($conversion->getConversionDescriptor());
      $downloadLink->save();

      // Send result
      $conversion->sendResult(\ymF\ERROR_OK, '', $downloadLink->getToken());
    }
    catch (\Exception $e)
    {
      try
      {
        if ($conversion instanceof Conversion)
          $conversion->sendResult(\ymF\ERROR_MISC, $e->getMessage());
        else
          echo $e->getMessage(), "\n";
      }
      catch (Exception $e)
      {
        echo $e->getMessage(), "\n"; // TODO: log error through CLIApplication
      }
    }

    return true;
  }
}