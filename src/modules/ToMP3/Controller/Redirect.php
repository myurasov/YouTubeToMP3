<?php

namespace ToMP3\Controller;

use ymF\Controller\ControllerBase;

class Redirect extends ControllerBase
{
  protected function _default()
  {
    if ($this->response instanceof \ymF\Response\HTTPResponse)
    {
      // Make redirect
      $url = \base64_decode($this->request->urlBase64);
      $this->response->setHeader('Location', $url);
      $this->response->setResponseCode(302);
      $this->response->sendHeaders();
    }
  }
}