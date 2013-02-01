<?php

namespace ToMP3\ConversionServer\Controller;

use ymF\Response\HTTPResponse;
use ymF\Util\Filesystem;
use ymF\Helper\PDOHelper;
use ToMP3\ConversionServer\Config;
use ToMP3\ConversionServer\Model\AudioFile;
use ToMP3\ConversionDescriptor;
use ToMP3\ConversionServer\DownloadLink;
use ToMP3\ConversionServer\Conversion;
use ymF\Controller\ControllerBase;

class Download extends ControllerBase
{
  /**
   * Download audio file
   *
   * Required args:
   *  token
   *
   *  Should be called with Null renderer
   */
  public function _default()
  {
    if (isset($this->request['token']))
    {
      /* @var $downloadLink DownloadLink */
      $downloadLink = DownloadLink::load($this->request->token);

      if ($downloadLink === false)
      {
        // Download link not found
        $this->response->setResponseCode(404);
        $this->response->sendHeaders();

        echo "Link not found!";
      }
      else
      {
        if ($downloadLink->isExpired())
        {
          // Link is expired
          $this->response->setResponseCode(404);
          $this->response->sendHeaders();

          echo "Link is no longer valid!";

          // Delete link
          $downloadLink->delete();
        }
        elseif (!$downloadLink->isIpAllowed($_SERVER['REMOTE_ADDR']))
        {
          $this->response->setResponseCode(403);
          $this->response->sendHeaders();
          echo "Access denied for your IP!";
        }
        else
        {
          $pdo = PDOHelper::getPDO(Config::get('PDOHelper'));

          if (false === ($audioFile = AudioFile::fromId($downloadLink->getAudioFileId())))
          {
            echo "No audio file!";
          }
          else
          {
            // Modify and save AudioFile
            $time = time();
            $sql = "UPDATE '{$audioFile->getTable()}'
              SET timeLastDownloaded='{$time}'
              WHERE id='{$audioFile['id']}';";
            $pdo->exec($sql);

            // Update hits on download link
            $downloadLink->incrementHits();
            $downloadLink->save();

            // Update record in conversion log
            Conversion::addConversionRecordDownload($downloadLink->getConversionRecordId());

            // Send headers

            $format = $downloadLink->getConversionDescriptor()->getFormat();

            $this->response->setHeader('Content-type', 'audio/mpeg');
            $this->response->setHeader('Content-Disposition', 'attachment; filename="'
              . Filesystem::cleanFilename($audioFile['title']) . ".$format\"");
            $this->response->setHeader('Content-Length', filesize($audioFile['file']));
            $this->response->setHeader('Accept-Ranges', 'none');
            $this->response->sendHeaders();

            // Send file to user
            readfile($audioFile['file']);
          }
        }
      }
    }
  }
}