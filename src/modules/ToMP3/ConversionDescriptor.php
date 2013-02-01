<?php

namespace ToMP3;

use ToMP3\ConversionServer\Video\AbstractVideo;

class ConversionDescriptor
{
  const QUALITY_LOW = 'low';
  const QUALITY_STD = 'std';
  const QUALITY_HIGH = 'high';
  const FORMAT_MP3 = 'mp3';

  private $siteId;
  private $videId;
  private $quality;
  private $format;

  /**
   * Constructor
   * 
   * @param string $siteId
   * @param string $videoId
   * @param string $quality
   * @param string $format
   */
  public function __construct($siteId, $videoId, $quality = self::QUALITY_STD, $format = self::FORMAT_MP3)
  {
    $this->siteId = $siteId;
    $this->videId = $videoId;
    $this->format = $format;
    $this->quality = $quality;
  }

  /**
   * To string
   * 
   * @return string
   */
  public function __toString()
  {
    $videoId = urlencode($this->videId);
    return "{$this->siteId}:{$videoId}:{$this->quality}:{$this->format}";
  }
  
  /**
   * Create from descriptor string or URL
   *
   * @param string $descriptor
   * @return ConversionDescriptor
   */
  public static function fromString($descriptor, $quality = self::QUALITY_STD, $format = self::FORMAT_MP3)
  {
    $matches = array();

    if (preg_match('/^(.+):(.+):(.+):(.+)$/', $descriptor, $matches))
    {
      $siteId = $matches[1];
      $videId = urldecode($matches[2]);
      $quality = $matches[3];
      $format = $matches[4];

      return new self($siteId, $videId, $quality, $format);
    }

    return false;
  }

  /**
   * Create from AbstractVideo
   *
   * @param AbstractVideo $video
   * @param string $format
   * 
   * @return ConversionDescriptor
   */
  public static function fromAbstractVideo(AbstractVideo $video, $format = self::FORMAT_MP3)
  {
    return new self($video->getSiteDecriptor(),
      $video->getSiteVideoId(),
      $video->getConversionQuality(),
      $format);
  }

  // <editor-fold desc="Getters and setters">
  public function getSiteId()
  {
    return $this->siteId;
  }

  public function setSiteId($siteId)
  {
    $this->siteId = $siteId;
  }

  public function getVideoId()
  {
    return $this->videId;
  }

  public function setVideId($videId)
  {
    $this->videId = $videId;
  }

  public function getQuality()
  {
    return $this->quality;
  }

  public function setQuality($quality)
  {
    $this->quality = $quality;
  }

  public function getFormat()
  {
    return $this->format;
  }

  public function setFormat($format)
  {
    $this->format = $format;
  }
  // </editor-fold>
}