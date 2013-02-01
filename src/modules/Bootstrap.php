<?php

/**
 * ToMP3 project bootstrap file
 *
 * @copyright 2010-2011 Misha Yurasov
 * @package ymF
 */

// Constants

// Project name
define('ymF\PROJECT_NAME', 'ToMP3');

// Project version (major.minor<.change>< status>)
define('ymF\PROJECT_VERSION', '1.0.3');

// Project root directory (where "core" folder resides)
define('ymF\PATH_ROOT', realpath(__DIR__ . '/../..'));

// Include id file
require ymF\PATH_ROOT . '/id.php';

// Include ymF
require '/Projects/_libraries/php/ymF-0.5.7/src/ymF/ymF.php';

// Init ymF
ymF\Kernel::init();

// Register autoloading for ymF
ymF\Kernel::registerAutoloadNamespace('ymF', '/Projects/_libraries/php/ymF-0.5.7/src', false);

// Register autoloading for project
ymF\Kernel::registerAutoloadNamespace(\ymF\PROJECT_NAME);