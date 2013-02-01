<?php

namespace ToMP3\ConversionServer\Controller;

use ToMP3\ConversionServer\Config;
use ymF\Helper\PDOHelper;

class Statistics extends \ymF\Controller\APIController
{
  /**
   * Get top conversions
   *
   * @return array
   */
  public function getTop()
  {
    $this->requireArguments('count');
    
    $pdo = PDOHelper::getPDO(Config::get('PDOHelper'));
    $maxResults = intval($this->request->count);

    $sql = "SELECT c.siteId, c.videoId, c.conversions,
              a.title, a.description, a.thumbnailUrl,
              a.author, a.duration
            FROM ConversionLog c
            JOIN AudioFiles a ON c.conversionDescriptor = a.conversionDescriptor
            ORDER BY c.conversions DESC";

    $queryResult = $pdo->query($sql);

    $result = array();
    $resultsCount = 0;
    
    foreach ($queryResult as $row)
    {
      if (!isset($result[$row['siteId']][$row['videoId']]))
      {
        $videoClass = \ToMP3\ConversionServer\Video\AbstractVideo::getPerSiteChildClass($row['siteId']);
        $row['sourceUrl'] = $videoClass::getCanonicalPageUrl($row['videoId']);
        $result[$row['siteId']][$row['videoId']] = $row;
        if (++$resultsCount >= $maxResults) break;
      }
    }

    return self::apiResult($result);
  }

  public function getRecent()
  {
    $this->requireArguments('count');
    
    $pdo = PDOHelper::getPDO(Config::get('PDOHelper'));
    $maxResults = intval($this->request->count);
    
    $sql = "SELECT c.siteId, c.videoId, " . time() . "-c.lastConversionTime age,
              a.title, a.description, a.thumbnailUrl, a.author, a.duration
            FROM ConversionLog c
            JOIN AudioFiles a ON c.conversionDescriptor = a.conversionDescriptor
            ORDER BY age";
    
    $queryResult = $pdo->query($sql);
    $result = array();
    $resultsCount = 0;

    foreach ($queryResult as $row)
    {
      if (!isset($result[$row['siteId']][$row['videoId']]))
      {
        $videoClass = \ToMP3\ConversionServer\Video\AbstractVideo::getPerSiteChildClass($row['siteId']);
        $row['sourceUrl'] = $videoClass::getCanonicalPageUrl($row['videoId']);
        $result[$row['siteId']][$row['videoId']] = $row;
        if (++$resultsCount >= $maxResults) break;
      }
    }

    return self::apiResult($result);
  }
}