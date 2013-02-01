<?php

namespace ToMP3\ConversionServer\Model;

use ToMP3\ConversionServer\Video\AbstractVideo;
use ToMP3\ConversionServer\Config;
use ToMP3\ConversionDescriptor;
use ymF\Model\ModelBase;
use ymF\Util\Filesystem;
use Exception;

class AudioFile extends ModelBase
{
  protected $table = 'AudioFiles';
  
  protected $data = array(
    'id'                    => null,
    'conversionDescriptor'  => '',
    'timeCreated'           => 0,       // Creation time()
    'timeLastDownloaded'    => 0,       // Last download time()
    'title'                 => '',      // Title
    'description'           => '',      // Description
    'author'                => '',      // Author
    'duration'              => 0,       // Duration in seconds
    'file'                  => '',      // File path
    'thumbnailFile'         => '',      // Thumbnail file
    'thumbnailUrl'          => '',      // Thumbnail url
  );

  protected $format;
  
  /**
   * Create audio from video
   * 
   * @param AbstractVideo $video
   * @return AudioFile
   */
  public static function fromVideo(AbstractVideo $video,
    $format = ConversionDescriptor::FORMAT_MP3,
    $quaility = ConversionDescriptor::QUALITY_STD)
  {
    if (is_null($quaility))
      $quaility = $video->getConversionQuality();

    // Create new AudioFile instance

    $audio = new static();

    // Fill with data

    $audio->setFormat($format);
    
    $audio['conversionDescriptor'] =
      new ConversionDescriptor(
        $video->getSiteDecriptor(),
        $video->getSiteVideoId(),
        $quaility,
        $format);

    $audio['title'] = $video->getTitle();
    $audio['author'] = $video->getAuthor();
    $audio['description'] = $video->getDescription();

    $audio['file'] = Filesystem::createPathWithSubdirs(
        Config::get('AudioFile.outputDir.dir'),
        md5($audio['conversionDescriptor']) . '.' . $format,
        Config::get('AudioFile.outputDir.subdirs')
      );

    // Delete existing output file
    if (file_exists($audio['file']))
      @unlink($audio['file']);

    // Conversion command

    $command = Config::get('AudioFile.conversionCommands.'
      . $format . '.'
      . $quaility);

    $command = strtr($command, array(
      '%input'    => escapeshellarg($video->getFile()),
      '%output'   => escapeshellarg($audio['file']),
      '%title'    => escapeshellarg($audio['title']),
      '%artist'   => escapeshellarg($video->getAuthor()),
      '%comment'  => escapeshellarg($video->getDescription()),
      '%year'     => escapeshellarg($video->getYearUploaded()),
    ));

    $return = null;
    $output = array();
    @exec($command, $output, $return);

    if ($return != 0)
      throw new Exception("Error executing conversion command \"$command\"");

    // Get duration from command output
    $output = $output[count($output) - 2];
    $output = str_replace("\r", "\n", $output);
    $matches = array(); preg_match('#time=([0-9\\.]+).*$#', $output, $matches);
    $audio['duration'] = (int) round($matches[1]);

    // Set created time
    $audio['timeCreated'] = time();

    // Create thumbnail
    
    $thumbnailSrc = $video->getTempThumbnailFile() ?: $video->getTempThumbnailUrl();

    $thumbnailDst = Filesystem::createPathWithSubdirs(
      Config::get('AudioFile.thumbnailsDir.dir'),
      md5((string) $audio['conversionDescriptor']) . '.' .
        pathinfo($thumbnailSrc, \PATHINFO_EXTENSION),
      Config::get('AudioFile.thumbnailsDir.subdirs'));

    @copy($thumbnailSrc, $thumbnailDst);

    $audio['thumbnailFile'] = $thumbnailDst;
    $audio['thumbnailUrl'] = 'http://' . Config::get('hostName')
      . Config::get('AudioFile.thumbnailsUrlPath')
      . substr($thumbnailDst, strlen(Config::get('AudioFile.thumbnailsDir.dir')));

    return $audio;
  }

  public function getFormat()
  {
    return $this->format;
  }

  public function setFormat($format)
  {
    $this->format = $format;
  }
}