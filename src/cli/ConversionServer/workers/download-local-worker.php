<?php

namespace ToMP3\ConversionServer;

use ymF\CLI\CLIApplication;
use ymF\Helper\GearmanHelper;
use ToMP3\ConversionServer\Config;

require __DIR__ . '/../../../modules/Bootstrap.php';

// Console configuration

$cliApp = new CLIApplication(array(
    'script_name' => 'Dowload Worker',
    'script_version' => '0.0',
    'script_description' =>
      'Downloads videos. Operates with local data.',
  ));

$cliApp->declareParameter('id', 'id', 1, CLIApplication::PARAM_TYPE_INTEGER, 'Worker id');

// Set log file

$cliApp->options['log_file'] =
  \ymF\PATH_LOGS . '/' . pathinfo(__FILE__, \PATHINFO_BASENAME) .
  '.' . $cliApp->getParameter('id') . '.log';

// Start

if ($cliApp->getParameter('?'))
{
  $cliApp->displayHelp();
}
else
{
  // Create worker
  $gearmanWorker = GearmanHelper::getWorker(
    Config::get('GearmanHelper_Local'));
  
  // Function name = project:function@hostname
  $function = GearmanHelper::createFunction('downloadLocal',
    Config::get('hostName'));
  $gearmanWorker->addFunction($function,
    'ToMP3\ConversionServer\Worker\DownloadLocal::run');

  $cliApp->status("Started worker with id " .
    $cliApp->getParameter('id') .
    " and function \"$function\"");

  while ($gearmanWorker->work());
}