<?php

declare(strict_types=1);

/**
 * @package    Brambring.Plugins
 * @subpackage Tasks.DelTrash
 * @version    __DEPLOY_VERSION__
 * @copyright  2025 Bram Brambring (https://brambring.nl)
 * @license    GNU General Public License version 3 or later;
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') || die;
// phpcs:enable PSR1.Files.SideEffects


use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;


// phpcs:disable PSR12.Classes.AnonClassDeclaration
return new class() implements
    ServiceProviderInterface {
    // phpcs:enable PSR12.Classes.AnonClassDeclaration
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            // phpcs:disable PSR12.Classes.AnonClassDeclaration
            new class() implements
                InstallerScriptInterface {
                // phpcs:enable PSR12.Classes.AnonClassDeclaration
                /**
                 * @since  __DEPLOY_VERSION__
                 */
                private string $minimumJoomlaVersion = '5.2';
                /**
                 * @since  __DEPLOY_VERSION__
                 */
                private $minimumPHPVersion    = '8.2';

                /**
                 * @since  __DEPLOY_VERSION__
                 */
                private DatabaseInterface $db;

                /**
                 * @since  __DEPLOY_VERSION__
                 */
                private CMSApplicationInterface $app;

                /**
                 * @since  __DEPLOY_VERSION__
                 */
                public function __construct()
                {
                    $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
                    $this->app = Factory::getApplication();
                }

                /**
                 * @since  __DEPLOY_VERSION__
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    $query = $this->db->getQuery(true);
                    $query->update($this->db->quoteName('#__extensions'))
                        ->set($this->db->quoteName('enabled') . ' = 1')
                        ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                        ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote($adapter->group))
                        ->where($this->db->quoteName('element') . ' = ' . $this->db->quote($adapter->element));
                    $this->db->setQuery($query)->execute();
                    return true;
                }

                /**
                 * @since  __DEPLOY_VERSION__
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @since  __DEPLOY_VERSION__
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @since  __DEPLOY_VERSION__
                 */

                public function preflight(string $type, InstallerAdapter $adapter): bool
                {

                    if ($type !== 'uninstall') {
                        // Check for the minimum PHP version before continuing
                        if (version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
                            Log::add(
                                Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion),
                                Log::ERROR,
                                'jerror'
                            );
                            return false;
                        }
                        // Check for the minimum Joomla version before continuing
                        if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
                            Log::add(
                                Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                                Log::ERROR,
                                'jerror'
                            );
                            return false;
                        }
                    }


                    return true;
                }

                /**
                 * @since  __DEPLOY_VERSION__
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Reloads the language from the installation package
                 * @since  __DEPLOY_VERSION__
                 */
                private function loadLanguage(InstallerAdapter $adapter): void
                {

                    //There is a $adapter->loadLanguage();
                    //but why is that the sys file. That one is loaded always and everytime.

                    $folder    = $adapter->group;
                    $name      = $adapter->element;
                    $extension = strtolower('plg_' . $folder . '_' . $name);


                    $source    = $adapter->parent->getPath('source');
                    $lang      = $this->app->getLanguage();
                    if (!$lang->load($extension, $source, reload: true) && !$lang->load($extension, JPATH_ADMINISTRATOR, reload: true)) {
                        $lang->load($extension, JPATH_PLUGINS . '/' . $folder . '/' . $name, reload: true);
                    }
                }
            }
        );
    }
};
