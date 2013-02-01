<?php

namespace ToMP3\ConversionServer;

use ymF\CLI\CLIApplication;
use ymF\Helper\GearmanHelper;
use ToMP3\ConversionServer\Config;

require __DIR__ . '/../../../modules/Bootstrap.php';

// Console configuration

$cliApp = new CLIApplication(array(
    'script_name' => 'Convert Worker',
    'script_version' => '0.0',
    'script_description' =>
      'Listens for conversion jobs from gearman server and executes them.',
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
    Config::get('GearmanHelper'));

  // Function name = project:function
  $functions = array();
  $functions[0] = GearmanHelper::createFunction('convert');
  $functions[1] = GearmanHelper::createFunction('convert', Config::get('hostName'));
  
  // Add functions
  $gearmanWorker->addFunction($functions[0],
    'ToMP3\ConversionServer\Worker\Convert::run');
  $gearmanWorker->addFunction($functions[1],
    'ToMP3\ConversionServer\Worker\Convert::run');
  
  $cliApp->status("Started worker with id " .
    $cliApp->getParameter('id') .
    " and functions:\n\"{$functions[0]}\"\n\"{$functions[1]}\"");

  while ($gearmanWorker->work());

  // TODO: global timeout for transfer => worker callback
  // TODO: check file size while downloading => worker callback
}