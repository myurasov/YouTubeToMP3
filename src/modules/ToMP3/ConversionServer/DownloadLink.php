<?php

namespace ToMP3\ConversionServer;

use ymF\Util\Strings;
use ymF\Helper\MemcachedHelper;
use ToMP3\ConversionServer\Config;
use ToMP3\ConversionDescriptor;

class DownloadLink
{
  protected $token;
  protected $audioFileId;
  protected $conversionRecordId;
  protected $clientIp = 0;
  protected $timeCreated;
  protected $hits = 0;
  protected $conversionDescriptor;

  public function __construct()
  {
    $this->token = Strings::createRandomString(null,
      Strings::ALPHABET_ALPHANUMERICAL, 64);
    $this->timeCreated = time();
  }

  /**
   * Increment hits counter
   * 
   * @return int
   */
  public function incrementHits()
  {
    return $this->hits++;
  }

  /**
   * Check validity of the link (hits count and lifetime)
   *
   * @return bool
   */
  public function isExpired()
  {
    if ($this->hits < Config::get('DownloadLink.maxHits'))
      if ((time() - $this->timeCreated) < Config::get('DownloadLink.lifeTime'))
        return false;

    return true;
  }

  /**
   * Check if client ip is allowed
   *
   * @param string $clientIp
   * @return bool
   */
  public function isIpAllowed($clientIp)
  {
    $clientIp = ip2long($clientIp);
    $mask = 0xff000000; // /24
    $clientIp = $clientIp & $mask;
    $linkIp = $this->clientIp & $mask;
    return ($clientIp == $linkIp);
  }

  /**
   * Save to memcache
   */
  public function save()
  {
    $memcached = MemcachedHelper::getMemcached(
      Config::get('MemcachedHelper'));
    $memcached->set('DownloadLink:' . $this->token, $this,
      Config::get('DownloadLink.lifeTime'));
  }

  /**
   * Delete from memcache
   */
  public function delete()
  {
    $memcached = MemcachedHelper::getMemcached(
      Config::get('MemcachedHelper'));
    $memcached->delete('DownloadLink:' . $this->token);
  }

  /**
   * Load from memcache
   * 
   * @param string $token
   * @return DownloadLink
   */
  public static function load($token)
  {
    $memcached = MemcachedHelper::getMemcached(
      Config::get('MemcachedHelper'));
    return $memcached->get('DownloadLink:' . $token);
  }

  // <editor-fold desc="Getters and setters">

  public function getToken()
  {
    return $this->token;
  }

  public function getAudioFileId()
  {
    return $this->audioFileId;
  }

  public function setAudioFileId($audioFileId)
  {
    $this->audioFileId = $audioFileId;
  }

  public function setClientIp($clientIp)
  {
    $this->clientIp = ip2long($clientIp);
  }

  public function setConversionRecordId($conversionRecordId)
  {
    $this->conversionRecordId = $conversionRecordId;
  }

  public function getConversionRecordId()
  {
    return $this->conversionRecordId;
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

  public function getHits()
  {
    return $this->hits;
  }

  // </editor-fold>
}