<?php

namespace ToMP3\ConversionServer;

use ymF\Controller\APIController;
use ymF\Util\Strings;
use ymF\Helper\PDOHelper;
use ToMP3\ConversionServer\Video\AbstractVideo;
use ToMP3\ConversionServer\Model\AudioFile;
use ToMP3\ConversionDescriptor;
use ToMP3\LinkDescriptor;
use ToMP3\ConversionServer\Model\ConversionRecord;

/**
 * Container for passing data beetween workers
 */
class Conversion
{
  /** @var AbstractVideo */
  private $video;
  /** @var AudioFile */
  private $audio;
  /** @var ConversionDescriptor */
  private $conversionDescriptor;
  private $format;
  private $quality;
  private $jobHandle;
  private $frontendHost;

  /**
   * Constructor
   *
   * @param string $frontendHost
   * @param string $jobHandle
   */
  public function __construct($frontendHost, $jobHandle)
  {
    $this->frontendHost = $frontendHost;
    $this->jobHandle = $jobHandle;
  }

  /**
   * Send metadata to frontend host
   * 
   * @param string $jobHandle
   * @param string $frontendHost
   */
  public function sendMetadata()
  {
    $linkDescriptor = new LinkDescriptor($this->conversionDescriptor, Config::get('hostName'));

    if ($this->audio instanceof AudioFile)
    {
      $metadata = array(
        'thumbnailUrl'  => $this->audio->thumbnailUrl,
        'title'         => $this->audio->title,
        'duration'      => $this->audio->duration,
        'author'        => $this->audio->author,
        'description'   => $this->audio->description,
      );
    }
    else if ($this->video instanceof AbstractVideo)
    {
      $metadata = array(
        'thumbnailUrl'  => $this->video->getTempThumbnailUrl(),
        'title'         => $this->video->getTitle(),
        'duration'      => $this->video->getDuration(),
        'author'        => $this->video->getAuthor(),
        'description'   => $this->video->getDescription()
      );
    }

    // <editor-fold desc="For recent stats">

    $videoClass = AbstractVideo::getPerSiteChildClass($this->conversionDescriptor->getSiteId());
    $metadata['sourceUrl'] = $videoClass::getCanonicalPageUrl($this->conversionDescriptor->getVideoId());
    $metadata['siteId'] = $this->conversionDescriptor->getSiteId();
    $metadata['videoId'] = $this->conversionDescriptor->getVideoId();

    // </editor-fold>

    $metadata['jobHandle'] = $this->jobHandle;
    $metadata['linkDescriptor'] = (string) $linkDescriptor;

    APIController::callRemote(
      $this->frontendHost,
      'ToMP3\\Controller\\API\\Conversion',
      'setJobMetadata', $metadata);
  }

  /**
   * Send result to frontend
   * 
   * @param int $error
   * @param string $message
   * @param string $downloadToken
   */
  public function sendResult($error = \ymF\ERROR_OK, $message = '', $downloadToken = '')
  {
    $result = array(
      'jobHandle' => $this->jobHandle,
      'error' => $error,
      'message' => $message,
      'downloadToken' => $downloadToken,
      'workerHost' => Config::get('hostName'),
    );

    APIController::callRemote(
      $this->frontendHost,
      'ToMP3\\Controller\\API\\Conversion', 'setJobResult',
      $result
    );
  }

  /**
   * Create new record in conversion log
   *
   * @return int ConversionRecord id
   */
  public function updateConversionRecord()
  {
    PDOHelper::setConfig(Config::get('PDOHelper'));
    
    $conversionRecord = ConversionRecord::fromWhere('%k=v', array(
      'conversionDescriptor' => (string) $this->conversionDescriptor
    ));

    if (false === $conversionRecord)
    {
      $conversionRecord = new ConversionRecord();
      $conversionRecord->conversionDescriptor = (string) $this->conversionDescriptor;
      $conversionRecord->siteId = $this->conversionDescriptor->getSiteId();
      $conversionRecord->videoId = $this->conversionDescriptor->getVideoId();
      $conversionRecord->quality = $this->conversionDescriptor->getQuality();
      $conversionRecord->format = $this->conversionDescriptor->getFormat();
      $conversionRecord->conversions = 1;
      $conversionRecord->lastConversionTime = time();
      $conversionRecord->save();
    }
    else
    {
      $sql = "UPDATE ConversionLog SET conversions=conversions+1, lastConversionTime=" . time() . "
        WHERE id=" . $conversionRecord->id;
      $pdo = PDOHelper::getPDO();
      $pdo->exec($sql);
    }

    return (int) $conversionRecord->id;
  }

  /**
   * Add download to conversion record
   * 
   * @param string $id
   */
  public static function addConversionRecordDownload($id)
  {
    $pdo = PDOHelper::getPDO(Config::get('PDOHelper'));
    $sql = "UPDATE ConversionLog SET downloads=downloads+1, lastDownloadTime=" . time() .
      " WHERE id=$id";
    $pdo->exec($sql);
  }

  // <editor-fold desc="Getters and setters">

  /**
   * @return AbstractVideo
   */
  public function getVideo()
  {
    return $this->video;
  }

  public function setVideo($video)
  {
    $this->video = $video;
  }

  /**
   * @return AudioFile
   */
  public function getAudio()
  {
    return $this->audio;
  }

  public function setAudio($audio)
  {
    $this->audio = $audio;
  }

  public function getFormat()
  {
    return $this->format;
  }

  public function getQuality()
  {
    return $this->quality;
  }

  public function getJobHandle()
  {
    return $this->jobHandle;
  }

  public function getFrontendHost()
  {
    return $this->frontendHost;
  }

  /**
   * @return ConversionDescriptor
   */
  public function getConversionDescriptor()
  {
    return $this->conversionDescriptor;
  }

  public function setConversionDescriptor(ConversionDescriptor $conversionDescriptor)
  {
    $this->conversionDescriptor = $conversionDescriptor;
  }

  public function setFormat($format)
  {
    $this->format = $format;
  }

  public function setQuality($quality)
  {
    $this->quality = $quality;
  }

  // </editor-fold>
}