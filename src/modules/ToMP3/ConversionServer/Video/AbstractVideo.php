<?php

namespace ToMP3\ConversionServer\Video;

use Exception;
use ymF\Util\Strings;
use ymF\Util\Filesystem;
use ToMP3\ConversionDescriptor;
use ToMP3\ConversionServer\Config;
use ToMP3\ConversionServer\Audio;

abstract class AbstractVideo
{
  protected $pageUrl; // Url on the video site
  protected $siteDescriptor; // Symbolic site descriptor
  protected $siteVideoId; // Video id on site
  protected $url;
  protected $title;
  protected $author; // Video author
  protected $description; // Video description
  protected $duration; // Video duration in seconds
  protected $size; // Video file size [bytes]
  protected $file; // Local file path
  protected $tempThumbnailUrl; // Temporary thumbnail URL
  protected $tempThumbnailFile; // Temporary thumbnail local file
  protected $conversionQuality; // Preffered quality setting
  protected $yearUploaded; // Year uploaded

  /**
   * Factory method to create video converter
   *
   * @param string $pageUrl Page URL or conversion descriptor
   * @return AbstractVideo
   */
  public static function createVideo($pageUrl)
  {
    $siteId = self::getSiteId($pageUrl);
    $className = self::getPerSiteChildClass($siteId);
    return new $className($pageUrl);
  }

  /**
   * Return Per-site child class name
   * @param string $siteId
   */
  public static function getPerSiteChildClass($siteId)
  {
    $className = __NAMESPACE__;
    
    if ($siteId == 'youtube')
    {
      $className .= '\\YoutubeVideo';
    }
    else
    {
      throw new \Exception('Unsupported video site');
    }

    return $className;
  }

  /**
   * Get site id
   * 
   * @param string $url
   * @return string
   */
  public static function getSiteId($url)
  {
    if ($cd = ConversionDescriptor::fromString($url))
    {
      return $cd->getSiteId();
    }
    else
    {
      // Fix missing URL protocol
      if (is_null(parse_url($url, PHP_URL_SCHEME)))
        $url = 'http://' . $url;

      $host = parse_url($url, PHP_URL_HOST);

      if (preg_match('/youtube\\.com$/i', $host)    // youtube.com
        || preg_match('/youtu\\.be$/i', $host))    // youtu.be
      {
        return 'youtube';
      }
    }

    return false;
  }

  /**
   * Constructor
   *
   * @param string $pageUrl
   */
  public function __construct($pageUrl = null)
  {
    $this->setPageUrl($pageUrl);
  }

  /**
   * Destructor
   */
  public function cleanup()
  {
    // Delete temporary files
    
    if (file_exists($this->file))
      @unlink ($this->file);

    if (file_exists($this->tempThumbnailFile))
      @unlink ($this->tempThumbnailFile);
  }

  /**
   * Download video file
   *
   * @param <type> $callback
   */
  abstract public function fetchVideo($callback = null);

  /**
   * Fetch video metadata
   *  title
   *  author
   *  description
   *  duration
   *  upload date
   *  url
   *  temporary thumbnail Url
   */
  abstract public function fetchMetadata();

  /**
   * Download video thumbnai to temporary folder
   *
   * @param string $thumbnailUrl
   * @param string $fileExtension
   */
  protected function _downloadTemporaryThumbnail($thumbnailUrl, $fileExtension)
  {
    $tempThumbsDir = Config::get('Video.thumbnailsTempDir');

    if (!is_dir($tempThumbsDir))
      @mkdir($tempThumbsDir, 0777, true);

    $this->tempThumbnailFile = Filesystem::getTempFilePath($tempThumbsDir, $fileExtension);

    if (false === @copy($thumbnailUrl, $this->tempThumbnailFile))
      throw new Exception("Failed to download thumbnail from \"{$thumbnailUrl}\"");
  }
  
  // <editor-fold desc="Getters and setters">

  public function getSiteDecriptor()
  {
    return $this->siteDescriptor;
  }

  abstract public function getSiteVideoId();
  
  public function getPageUrl()
  {
    return $this->pageUrl;
  }

  public function setPageUrl($pageUrl)
  {
    // Fix missing URL protocol
    if (is_null(parse_url($pageUrl, PHP_URL_SCHEME)))
      $pageUrl = 'http://' . $pageUrl;
    
    $this->pageUrl = $pageUrl;
  }

  public function getUrl()
  {
    return $this->url;
  }

  public function getTitle()
  {
    return $this->title;
  }

  public function setTitle($title)
  {
    $this->title = $title;
  }

  public function getSize()
  {
    return $this->size;
  }

  public function getFile()
  {
    return $this->file;
  }

  public function getConversionQuality()
  {
    return $this->conversionQuality;
  }

  public function setConversionQuality($conversionQuality)
  {
    $this->conversionQuality = $conversionQuality;
  }

  public function getDuration()
  {
    return $this->duration;
  }

  public function getTempThumbnailUrl()
  {
    return $this->tempThumbnailUrl;
  }

  public function getTempThumbnailFile()
  {
    return $this->tempThumbnailFile;
  }

  public function getAuthor()
  {
    return $this->author;
  }

  public function getDescription()
  {
    return $this->description;
  }

  public function getYearUploaded()
  {
    return $this->yearUploaded;
  }
  
  // </editor-fold>
}