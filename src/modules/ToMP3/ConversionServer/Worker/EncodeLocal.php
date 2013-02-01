<?php

namespace ToMP3\ConversionServer\Worker;


use ymF\Helper\GearmanHelper;
use ymF\Helper\PDOHelper;
use ToMP3\ConversionServer\Model\AudioFile;
use ToMP3\ConversionServer\Video\AbstractVideo;
use ToMP3\ConversionServer\Config;
use ToMP3\ConversionServer\Conversion;

/**
 * Encode worker
 *
 * Args: video
 *
 */
class EncodeLocal
{
  /**
   * Encode audio
   *
   * Args:
   *
   *   array(
   *     'conversion' => Conversion,
   *   );
   *
   * @param \GearmanJob $job
   * @return AudioFile->id
   */
  public static function run(\GearmanJob $job)
  {
    try {
      $workload = GearmanHelper::decodeData($job->workload());

      /* @var $conversion Conversion */
      $conversion = $workload['conversion'];
      
      $audio = AudioFile::fromVideo(
        $conversion->getVideo(),
        $conversion->getFormat(),
        $conversion->getQuality());

      PDOHelper::setConfig(Config::get('PDOHelper'));
      $audio->save();
    }
    catch (\Exception $e) {
      $job->sendData(GearmanHelper::encodeData(
        array('error' => true, 'message' => $e->getMessage())));
      return;
    }
    
    return GearmanHelper::encodeData($audio->id);
  }
}