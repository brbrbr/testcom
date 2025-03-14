<?php

/**
 * Prepares a minimalist framework for unit testing.
 *
 * @package    plg_task_deltrash
 *
 * @copyright 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 * @link       http://www.phpunit.de/manual/current/en/installation.html
 */

// phpcs:disable PSR1.Files.SideEffects

\define('_JEXEC', 1);

// Maximise error reporting.
//error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set fixed precision value to avoid round related issues
ini_set('precision', 14);

/*
 * Ensure that required path constants are defined.  These can be overridden within the phpunit.xml file
 * if you chose to create a custom version of that file.
 */
$rootDirectory = getcwd();
while (!is_file($rootDirectory . '/configuration.php') && $rootDirectory) {
    $rootDirectory = \dirname($rootDirectory);
}

if (!\defined('JPATH_BASE')) {
    \define('JPATH_BASE', $rootDirectory);
}

if (!\defined('JPATH_ROOT')) {
    \define('JPATH_ROOT', JPATH_BASE);
}

if (!\defined('JPATH_PLATFORM')) {
    \define('JPATH_PLATFORM', JPATH_BASE . DIRECTORY_SEPARATOR . 'libraries');
}

if (!\defined('JPATH_LIBRARIES')) {
    \define('JPATH_LIBRARIES', JPATH_BASE . DIRECTORY_SEPARATOR . 'libraries');
}
if (!\defined('JPATH_CLI')) {
    \define('JPATH_CLI', JPATH_BASE . DIRECTORY_SEPARATOR . 'cli');
}

if (!\defined('JPATH_CONFIGURATION')) {
    \define('JPATH_CONFIGURATION', JPATH_BASE);
}

if (!\defined('JPATH_SITE')) {
    \define('JPATH_SITE', JPATH_ROOT);
}

if (!\defined('JPATH_ADMINISTRATOR')) {
    \define('JPATH_ADMINISTRATOR', JPATH_ROOT . DIRECTORY_SEPARATOR . 'administrator');
}

if (!\defined('JPATH_CACHE')) {
    \define('JPATH_CACHE', JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'cache');
}

if (!\defined('JPATH_API')) {
    \define('JPATH_API', JPATH_ROOT . DIRECTORY_SEPARATOR . 'api');
}

if (!\defined('JPATH_INSTALLATION')) {
    \define('JPATH_INSTALLATION', JPATH_ROOT . DIRECTORY_SEPARATOR . 'installation');
}

if (!\defined('JPATH_MANIFESTS')) {
    \define('JPATH_MANIFESTS', JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'manifests');
}

if (!\defined('JPATH_PLUGINS')) {
    \define('JPATH_PLUGINS', JPATH_BASE . DIRECTORY_SEPARATOR . 'plugins');
}

if (!\defined('JPATH_THEMES')) {
    \define('JPATH_THEMES', JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'templates');
}

if (!\defined('JDEBUG')) {
    \define('JDEBUG', false);
}

// Import the library loader if necessary.
if (!class_exists('JLoader')) {
    require_once JPATH_LIBRARIES . '/loader.php';
    require_once JPATH_LIBRARIES . '/src/Autoload/ClassLoader.php';

    // If JLoader still does not exist panic.
    if (!class_exists('JLoader')) {
        throw new RuntimeException('Joomla Platform not loaded.');
    }
}

// Setup the autoloaders.
JLoader::setup();
JLoader::registerNamespace('Tests', __DIR__);
JLoader::registerNamespace('Joomla\Plugin\Task\Deltrash', __DIR__ . '/../src');

// Create the Composer autoloader
/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require '/var/www/projecten.dev/dev/vendor/autoload.php';

// We need to pull our decorated class loader into memory before unregistering Composer's loader
class_exists('\\Joomla\\CMS\\Autoload\\ClassLoader');

$loader->unregister();

// Decorate Composer autoloader
spl_autoload_register([new \Joomla\CMS\Autoload\ClassLoader($loader), 'loadClass'], true, true);

// Load extension classes
require_once JPATH_LIBRARIES . '/namespacemap.php';
$extensionPsr4Loader = new \JNamespacePsr4Map();
$extensionPsr4Loader->load();

// Define the Joomla version if not already defined.
\defined('JVERSION') or \define('JVERSION', (new \Joomla\CMS\Version())->getShortVersion());
if (version_compare(JVERSION, '5.0', '<')) {
    require_once JPATH_LIBRARIES . '/classmap.php';
}
