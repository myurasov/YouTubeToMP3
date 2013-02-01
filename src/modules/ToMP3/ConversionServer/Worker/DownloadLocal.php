<?php

namespace ToMP3\ConversionServer\Worker;

use \Exception;
use ymF\Helper\PDOHelper;
use ymF\Helper\GearmanHelper;
use ymF\Helper\MemcachedHelper;
use ymF\Controller\APIController;
use ToMP3\ConversionDescriptor;
use ToMP3\LinkDescriptor;
use ToMP3\ConversionServer\Video\AbstractVideo;
use ToMP3\ConversionServer\Config;
use ymF\Util\Strings;
use ToMP3\ConversionServer\Conversion;

/**
 * Download worker
 * 
 * Args: video
 *
 */
class DownloadLocal
{
  /**
   * Download video
   *
   * Args: array(conversion)
   * 
   * @param \GearmanJob $job
   * @return Conversion
   */
  public static function run(\GearmanJob $job)
  {
    $workload = GearmanHelper::decodeData($job->workload());

    /* @var $conversion Conversion */
    $conversion = $workload['conversion'];

    /* @var $video AbstractVideo */
    $video = $conversion->getVideo();

    try {
      // Fetch metadata
      $video->fetchMetadata();

      // Send metadata to frontend
      $conversion->sendMetadata();

      // Fetch video
      $video->fetchVideo(function ($position, $total) use ($job) {
          $job->sendStatus($position, $total);
      });
    }
    catch (Exception $e) {
      $job->sendData(GearmanHelper::encodeData(
        array('error' => true, 'message' => $e->getMessage())));
      return;
    }
    
    return GearmanHelper::encodeData($conversion);
  }
}