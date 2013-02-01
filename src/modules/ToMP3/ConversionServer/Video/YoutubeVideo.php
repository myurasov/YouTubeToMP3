<?php

namespace ToMP3\ConversionServer\Video;

use Exception;
use ToMP3\ConversionServer\Config;
use ToMP3\ConversionDescriptor;
use ymF\Util\Filesystem;
use ymF\Util\Strings;
use ymF\Component\HttpMultistreamDownloader\Downloader;

class YoutubeVideo extends AbstractVideo
{
  protected $siteDescriptor = 'youtube';
  private $specialCookie; // Cookie required to download video

  // Priority of YouTube formats for each quality level
  protected $fmtPriority = array(
    ConversionDescriptor::QUALITY_LOW   => array(5, 18, 34),
    ConversionDescriptor::QUALITY_STD   => array(18, 34, 35, 5),
    ConversionDescriptor::QUALITY_HIGH  => array(35, 18, 34)
  );

  /**
   * Get video id on the site
   * 
   * @return string
   */
  public function getSiteVideoId()
  {
    if (is_null($this->siteVideoId))
    {
      // Extract video id
      
      $m = array();

      // http://www.youtube.com/watch?v=JtxH4oYdTXk
      preg_match('/[\\?&]v=([^&]+)/i', $this->pageUrl, $m)

      // http://youtu.be/JtxH4oYdTXk
      or  preg_match('/youtu.be\\/([^\\?&#]+)$/', $this->pageUrl, $m)

      // http://www.youtube.com/user/hotforwords#p/u/10/XbFUtfHZbyo
      or  preg_match('#p/u/[0-9]+/(.+)$#', $this->pageUrl, $m);

      $this->siteVideoId = $m[1];
    }

    return $this->siteVideoId;
  }

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
  public function fetchMetadata()
  {
    $pageUrl = self::getCanonicalPageUrl($this->getSiteVideoId());
    
    // Fetch page and cookie
    if (false === ($fp = @fopen($pageUrl, 'r')))
      throw new Exception("Failed to open \"{$pageUrl}\"");

    $streamMetadata = stream_get_meta_data($fp);
    $streamMetadata = implode("\n", $streamMetadata['wrapper_data']);
    $matches = array();
    preg_match('/(VISITOR_INFO1_LIVE=.+?);/', $streamMetadata, $matches);
    $this->specialCookie = $matches[1];

    if (false === ($pageSource = stream_get_contents($fp)))
    {
      fclose($fp);
      throw new Exception("Failed to fetch \"{$pageUrl}\"");
    }

    fclose($fp);

    // Get title
    $matches = array();
    if (!preg_match('#<meta name=\\"title\\" content=\\"(.+)\\">#i', $pageSource, $matches))
      throw new Exception("Failed to parse \"{$pageUrl}\"");
    $this->title = htmlspecialchars_decode($matches[1], \ENT_QUOTES);
    $this->title = htmlspecialchars_decode($this->title, \ENT_QUOTES); // For double-encoded entities

    // Get author
    $matches = array();
    if (!preg_match('#<a id="watch-user(?:name|banner)" .+? href="/user/(.*?)"#i', $pageSource, $matches))
      throw new Exception("Failed to parse \"{$pageUrl}\"");
    $this->author = htmlspecialchars_decode($matches[1], \ENT_QUOTES);

    // Get description
    $matches = array();
    if (!preg_match('#<meta name="description" content="(.*)">#i', $pageSource, $matches))
      throw new Exception("Failed to parse \"{$pageUrl}\"");
    $this->description = htmlspecialchars_decode($matches[1], \ENT_QUOTES);
    $this->description = str_replace(array('<br>', '<br/>', '<br />'), "\n", $this->description);
    $this->description = strip_tags($this->description);

    // Get year uploaded
    $matches = array();
    if (!preg_match('#<a class="author".*?\s([0-9]{4})#s', $pageSource, $matches))
      throw new Exception("Failed to parse \"{$pageUrl}\"");
    $this->yearUploaded = $matches[1];

    // Get configuration
    $matches = array();
    if (!preg_match("/'PLAYER_CONFIG': (.+)}\\);/i", $pageSource, $matches))
      throw new Exception("Failed to parse \"{$pageUrl}\"");
    $playerConfig = json_decode($matches[1]);
    $fmtUrlMap = explode(',', $playerConfig->args->fmt_url_map);
    
    // Get duration
    $this->duration = $playerConfig->args->length_seconds;

    // Get video url

    $urls = array();

    foreach ($fmtUrlMap as $url)
    {
      list($fmtId, $url) = explode('|', $url);
      $urls[(int)$fmtId] = $url;
    }

    $url = null;

    foreach ($this->fmtPriority[$this->conversionQuality] as $fmtId)
    {
      if (isset($urls[$fmtId]))
      {
        $url = $urls[$fmtId];
        break;
      }
    }

    $this->url = $url;

    /*
    // Download thumbnail
    $this->_downloadTemporaryThumbnail(
      'http://i1.ytimg.com/vi/' . $this->getSiteVideoId() . '/default.jpg', 'jpg');

    // Calculate thumbnail url
    $this->tempThumbnailUrl = 'http://' . Config::get('hostName') . Config::get('Video.thumbnailsTempUrlPath')
      . '/'.  pathinfo($this->tempThumbnailFile, \PATHINFO_BASENAME);
     */

    // temporary thumbnail url
    $this->tempThumbnailUrl = 'http://i1.ytimg.com/vi/' .
      $this->getSiteVideoId() . '/default.jpg';
  }

  /**
   * Set page URL
   * Can process conversion descriptor
   * 
   * @param string $pageUrl
   */
  public function setPageUrl($pageUrl)
  {
    if (false !== ($cd = ConversionDescriptor::fromString($pageUrl)))
    {
      $this->pageUrl = 'http://www.youtube.com/watch?v=' . $cd->getVideoId();
      $this->conversionQuality = $cd->getQuality();
      $this->siteVideoId = $cd->getVideoId();
    }
    else
    {
      parent::setPageUrl($pageUrl);
    }
  }

  /**
   * Download video file
   *
   * @param callable $callback
   * @param float $callbackMinPeriod Minimum callback period in seconds
   */
  public function fetchVideo($callback = null)
  {
    // Create temp dir
    
    $tempDir = Config::get('Video.tempDir');

    if (!is_dir($tempDir))
      mkdir($tempDir, 0777, true);

    // Create temp filename
    $this->file = Filesystem::getTempFilePath($tempDir);

    // Create httpMultistreamDownloader

    $downloaderConfig = Config::get('YoutubeVideo.downloader');

    $downloader = new Downloader($this->url);
    $downloader->setOutputFile($this->file);
    $downloader->setChunkSize($downloaderConfig['chunkSize']);
    $downloader->setNetworkTimeout($downloaderConfig['networkTimeout']);
    $downloader->setMaxParallelChunks($downloaderConfig['maxParallelChunks']);
    $downloader->setMinCallbackPeriod($downloaderConfig['minCallbackPeriod']);
    $downloader->setCookie($this->specialCookie);
    $downloader->setUserAgent(Config::get('Video.downloadUserAgent'));
    
    if (!is_null($callback))
      $downloader->setProgressCallback($callback);

    if ($downloader->getTotalBytes() > Config::get('Video.maxFileSize'))
      throw new Exception("Video file is too large at \"$this->pageUrl\"");

    // Download
    $this->size = $downloader->download();
  }

  /**
   * Get canonical page url
   * 
   * @return string
   */
  public static function getCanonicalPageUrl($videoId)
  {
    return 'http://www.youtube.com/watch?v=' . $videoId;
  }
}