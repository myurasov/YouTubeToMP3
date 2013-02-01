<?php

/**
 *  Update statistics top
 *
 * Run frequency: hourly
 */

use ymF\CLI\CLIApplication;
use ToMP3\Commands\UpdateTop;

require __DIR__ . '/../../modules/Bootstrap.php';

$command = new UpdateTop(new CLIApplication());
$command->run();