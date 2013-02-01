<?php

namespace ToMP3\Commands;

use ymF\Controller\APIController;
use ymF\Util\Strings;
use ymF\CLI\CLICommand;
use ToMP3\Config;

class UpdateRecent extends CLICommand
{
  protected function _setup()
  {
    $this->cliApplication->options->set(array(
      'script_name' => \get_called_class(),
      'script_version' => \ymF\PROJECT_VERSION,
      'script_description' => 'Gather recent videos from worker hosts',
    ));
  }

  /**
   * Update recent
   */
  protected function _execute()
  {
    try
    {
      // Get data from hosts
      $maxVideos = Config::get('Stats.recentVideosCount');
      $hosts = Config::get('workerHosts');
//      $hosts = array('tomp3.org.local', 's1.tomp3.org', 's2.tomp3.org'); //xxx
      $stats = array();

      foreach ($hosts as $host)
      {
        try
        {
          $workerStats = APIController::callRemote($host,
              'ToMP3\\ConversionServer\\Controller\\Statistics',
              'getRecent', array('count' => $maxVideos));
        }
        catch (\Exception $exc)
        {
          break;
        }

        $workerStats = $workerStats['data'];

        foreach ($workerStats as $siteId => $siteVideos)
        {
          foreach ($siteVideos as $videoId => $videoData)
          {
            if (!isset($stats[$siteId][$videoId]))
            {
              // New entry
              $stats[$siteId][$videoId] = $videoData;
            }
            else if ($stats[$siteId][$videoId]['age'] > $videoData['age'])
            {
              // Update age
              $stats[$siteId][$videoId]['age'] = $videoData['age'];
            }
          }
        }
      }

      // Flattern stats array

      $statsFlat = array();

      foreach ($stats as $siteId => $siteVideos)
      {
        foreach ($siteVideos as $video)
        {
          $statsFlat[] = $video;
        }
      }

      $stats = $statsFlat;

      // Sort stats
      \usort($stats, function($a, $b) {
        return (int) $a['age'] - (int) $b['age'];
      });

      // Cut array
      $stats = \array_slice($stats, 0, min(count($stats), $maxVideos));

      // Format values
      foreach (array_keys($stats) as $key)
      {
        $stats[$key]['duration'] = Strings::formatTime(
          (int) $stats[$key]['duration'], 0, false, 0, true, true);
        $stats[$key]['sourceUrlBase64'] = \base64_encode($stats[$key]['sourceUrl']);
        $stats[$key]['sourceUrlBase64'] = \rtrim($stats[$key]['sourceUrlBase64'], '=');
        unset($stats[$key]['sourceUrl']);
      }

      // Save to memcached
      $memcached = \ymF\Helper\MemcachedHelper::getMemcached();
      $memcached->set('Stats:recent', $stats);
    }
    catch (\Exception $e)
    {
      $this->cliApplication->error($e->getMessage());
    }
  }
}