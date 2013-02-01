<?php

namespace ToMP3\Controller;

use ymF\Controller\ControllerBase;
use ymF\Helper\MemcachedHelper;
use ToMP3\Config;

class Top extends ControllerBase
{
  protected function _default()
  {
    $data['enableFrontend'] = Config::get('enableFrontend');

    // Read statistics from memcache
    $memcached = MemcachedHelper::getMemcached();
    if (false === $data['top'] = $memcached->get('Stats:top'))
      $data['top'] = null;

    return $data;
  }
}