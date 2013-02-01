<?php

namespace ToMP3\Controller;

use ymF\Controller\ControllerBase;
use ymF\Helper\MemcachedHelper;
use ToMP3\Config;

class Recent extends ControllerBase
{
  protected function _default()
  {
    $data['enableFrontend'] = Config::get('enableFrontend');

    // Read statistics from memcache
    $memcached = MemcachedHelper::getMemcached();
    if (false === $data['recent'] = $memcached->get('Stats:recent'))
      $data['recent'] = null;

    return $data;
  }
}