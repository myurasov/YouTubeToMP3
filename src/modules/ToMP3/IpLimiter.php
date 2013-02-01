<?php

namespace ToMP3;

use ymF\Helper\MemcachedHelper;

class IpLimiter
{
  /**
   * Add hit
   * 
   * @param string $clientIp
   */
  public static function addHit($clientIp)
  {
    $memcached = MemcachedHelper::getMemcached(Config::get('MemcachedHelper'));
    $clientIp = ip2long($clientIp);
    $hits = (int) $memcached->get($clientIp);
    $memcached->set($clientIp, ++$hits, Config::get('IpLimiter.timePeriod'));
  }

  /**
   * How many hits are allowed
   * 
   * @param string $clientIp
   * @return int
   */
  public static function hitsAllowed($clientIp)
  {
    $maxHits = Config::get('IpLimiter.maxHits');

    if ($maxHits > 0)
    {
      $memcached = MemcachedHelper::getMemcached(Config::get('MemcachedHelper'));
      $clientIp = ip2long($clientIp);
      $hits = (int) $memcached->get($clientIp);
      return max(0, $maxHits - $hits);
    }
    else
    {
      return $maxHits;
    }
  }
}