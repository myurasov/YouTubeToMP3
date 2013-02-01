<?php

namespace ToMP3\Controller\API;

use Exception;
use ymF\Controller\APIController;
use ymF\Helper\GearmanHelper;
use ymF\Helper\MemcachedHelper;
use ToMP3\Config;
use ToMP3\ConversionDescriptor;
use ToMP3\LinkDescriptor;
use ToMP3\Descriptor;
use ToMP3\IpLimiter;
use ymF\Util\Strings;

class Conversion extends APIController
{
  /**
   * Add convesion task
   * 
   * @return array
   */
  public function add()
  {
    // Check args
    $this->requireArguments('url', 'quality', 'format', 'linkDescriptor');

    // Check ip limit
    $ipHitsAllowed = IpLimiter::hitsAllowed($_SERVER['REMOTE_ADDR']);

    if ($ipHitsAllowed == 0)
      throw new Exception('Request limit reached, try again later');

    // Add hit to ip address
    if ($ipHitsAllowed > 0)
      IpLimiter::addHit($_SERVER['REMOTE_ADDR']);

    // Add conversion job to gearman server

    $gearmanClient = GearmanHelper::getClient(
      Config::get('GearmanHelper'));

    if ($this->request->linkDescriptor != '') // From link descriptor
    {
      // Decode link descriptor
      $linkDescriptor = LinkDescriptor::fromString($this->request->linkDescriptor);
      $conversionDescriptor = $linkDescriptor->getConversionDescriptor();
      $workerHost = $linkDescriptor->getHost(); // Worker host for the task

      $quality = $conversionDescriptor->getQuality();
      $format = $conversionDescriptor->getFormat();
      $url = (string) $conversionDescriptor;

      // Do conversion again if worker host has been removed
      if (!in_array($workerHost, Config::get('workerHosts')))
        $workerHost = null;
    }
    else // From url
    {
      $url = $this->request->url;

      if ($conversionDescriptor = ConversionDescriptor::fromString($url)) // url is ConversionDescriptor
      {
        $quality = $conversionDescriptor->getQuality();
        $format = $conversionDescriptor->getFormat();
      }
      else // simple url
      {
        $quality = $this->request->quality;
        $format = $this->request->format;
      }

      // Worker host for the task
      $workerHost = null;
    }

    $workload = array(
      'url'           => $url,
      'quality'       => $quality,
      'format'        => $format,
      'clientIp'      => $_SERVER['REMOTE_ADDR'],
      'frontendHost'  => Config::get('hostName')
    );

    // Send job to conversion server

    $jobHandle = $gearmanClient->doBackground(
      GearmanHelper::createFunction('convert', $workerHost),
      GearmanHelper::encodeData($workload));

    return self::apiResult(array('jobHandle' => $jobHandle));
  }

  /**
   * Get job status
   *
   * @return array
   */
  public function getJobStatus()
  {
    // Check args
    $this->requireArguments('jobHandle');

    // Get status

    $gearmanClient = GearmanHelper::getClient(Config::get('GearmanHelper'));
    $status = $gearmanClient->jobStatus($this->request['jobHandle']);

    // Get metadata

    $memcached = MemcachedHelper::getMemcached(
      Config::get('MemcachedHelper'));
    $key = 'JobMetadata:' . $this->request->jobHandle;
    $hasMetadata = $memcached->get($key)? true : false;

    // Response data

    $statusResponse = array(
      'isRunning'   => $status[1],
      'hasMetadata' => $hasMetadata
    );

    if ($statusResponse['isRunning'])
    {
      $statusResponse['nominator'] = $status[2];
      $statusResponse['denominator'] = $status[3];
    }

    return self::apiResult($statusResponse);
  }

  /**
   * Set job metadata
   * 
   * @access private
   */
  public function setJobMetadata()
  {
    $this->requireArguments('jobHandle',
      'thumbnailUrl', 'title', 'author',
      'duration', 'description', 'linkDescriptor',
      'sourceUrl', 'siteId', 'videoId'); // this line is for recent stats
    
    $metadata = array(
      'thumbnailUrl' => $this->request->thumbnailUrl,
      'title' => $this->request->title,
      'duration' => $this->request->duration,
      'author' => $this->request->author,
      'description' => $this->request->description,
      'linkDescriptor' => $this->request->linkDescriptor
    );

    // Format
    $metadata['duration'] = Strings::formatTime($metadata['duration'], 0, false, 0, true, true);

    $memcached = MemcachedHelper::getMemcached(
      Config::get('MemcachedHelper'));
    $memcached->set('JobMetadata:' . $this->request->jobHandle,
      $metadata, 60);

    // <editor-fold desc="Recent stats">

    unset($metadata['linkDescriptor']);

    $metadata['sourceUrlBase64'] = \rtrim(\base64_encode($this->request->sourceUrl), '=');
    $metadata['siteId'] = $this->request->siteId;
    $metadata['videoId'] = $this->request->videoId;
    
    if (false === ($recent = $memcached->get('Stats:recent')))
      $recent = array();

    \array_unshift($recent, $metadata);

    // Remove older entries of the same video
    
    for ($i = 1; $i < \count($recent); $i++)
    {
      if ($recent[$i]['siteId'] == $recent[0]['siteId'] &&
        $recent[$i]['videoId'] == $recent[0]['videoId'])
      {
        unset($recent[$i]);
      }
    }

    // Cut array to maximum length
    $recent = \array_slice($recent, 0,
      min(count($recent), Config::get('Stats.recentVideosCount')));

    // Save stats to memcache
    $memcached->set('Stats:recent', $recent);
    
    // </editor-fold>

    return self::apiResult();
  }

  /**
   * Get job metadata
   *
   * @return array
   */
  public function getJobMetadata()
  {
    $this->requireArguments('jobHandle');

    $memcached = MemcachedHelper::getMemcached(
      Config::get('MemcachedHelper'));

    $key = 'JobMetadata:' . $this->request->jobHandle;

    if (false !== ($data = $memcached->get($key)))
      $memcached->delete($key);
    else throw new Exception('Job handle hot found');

    return self::apiResult($data);
  }

  /**
   * Set job result
   *
   * @access private
   */
  public function setJobResult()
  {
    $this->requireArguments('jobHandle', 'error', 'message',
      'downloadToken', 'workerHost');

    $data = array(
      'error' => (int) $this->request->error,
      'message' => $this->request->message,
      'downloadToken' => $this->request->downloadToken,
      'workerHost' => $this->request->workerHost,
    );

    $memcached = MemcachedHelper::getMemcached(
      Config::get('MemcachedHelper'));
    $memcached->set('JobResult:' . $this->request->jobHandle,
      $data, 60);

    return self::apiResult();
  }

  /**
   * Get job metadata
   *
   * @return array
   */
  public function getJobResult()
  {
    $this->requireArguments('jobHandle');

    $memcache = MemcachedHelper::getMemcached(
      Config::get('MemcachedHelper'));

    $key = 'JobResult:' . $this->request->jobHandle;

    if (false !== ($host = $memcache->get($key)))
      $memcache->delete($key);
    else throw new Exception('Job handle hot found');

    return self::apiResult($host);
  }

  /**
   * Report if job is started
   */
  public function isJobStarted()
  {
    $this->requireArguments('jobHandle');

    // Get status
    
    $gearmanClient = GearmanHelper::getClient(Config::get('GearmanHelper'));
    $status = $gearmanClient->jobStatus($this->request['jobHandle']);
    $isRunning = $status[1];
    
    // Get result

    $memcached = MemcachedHelper::getMemcached(
      Config::get('MemcachedHelper'));
    $key = 'JobResult:' . $this->request->jobHandle;
    $hasResult = $memcached->get($key) ? true : false;

    // Ok

    if (!($status[0] || $hasResult))
      throw new Exception('Unknown job');
    
    return self::apiResult($isRunning || $hasResult);
  }
}