<?php

namespace ToMP3\Controller;

use ymF\Controller\ControllerBase;

class Terms extends ControllerBase
{
  protected function _default()
  {
    return array('enableFrontend'
      => \ToMP3\Config::get('enableFrontend'));
  }
}