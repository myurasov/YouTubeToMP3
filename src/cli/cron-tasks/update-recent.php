<?php

/**
 *  Update recent statistics (optional)
 *
 * Run frequency: every minute
 */

use ymF\CLI\CLIApplication;
use ToMP3\Commands\UpdateRecent;

require __DIR__ . '/../../modules/Bootstrap.php';

$command = new UpdateRecent(new CLIApplication());
$command->run();