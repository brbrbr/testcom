<?php

/**
 * @package    Joomla.UnitTest
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://www.phpunit.de/manual/current/en/installation.html
 */

namespace Tests;

use Joomla\CMS\Application\AdministratorApplication as Application;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\Logger\InMemoryLogger;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Task\Deltrash\Extension\Deltrash;
use PHPUnit\Framework\TestCase;

/**
 * Base Unit Test case for common behaviour across unit tests
 *
 * @since   __DEPLOY_VERSION__
 */
abstract class UnitTestCase extends TestCase
{
    protected string $folder  = '';
    protected string $element = '';
    protected string $class   = '';
    protected DatabaseInterface $db;
    protected ?CMSApplicationInterface $app = null;
    protected DispatcherInterface $dispatcher;
    protected Container $container;
    protected string $fieldContext = '';
    protected int $trashedState    = -2;

    protected function getDispatcher()
    {

        return $this->dispatcher;
    }

    protected function getContainer()
    {

        return $this->container;
    }

    protected function getApplication()
    {
        return $this->app;
    }

    protected function getDatabase()
    {
        return $this->db;
    }
    public function tearDown(): void
    {
        $this->assetLoggerInfo(0, 'error');
        $this->closeApplication();
    }

    protected function closeApplication(): void
    {
        unset($this->db, $this->container, $this->app);


        $this->app = null;
    }

    protected function initApplication(): void
    {

        if ($this->app instanceof Application) {
            return;
        }

        $_SERVER['HTTP_HOST']   = 'www.example.com:443';
        $_SERVER['SCRIPT_NAME'] = '/';
        $_SERVER['PHP_SELF']    = '/index.php';
        $_SERVER['REQUEST_URI'] = '/';

        $this->container = Factory::getContainer();
        $this->container->alias('session', 'session.cli')
            ->alias('JSession', 'session.cli')
            ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
            ->alias(\Joomla\Session\Session::class, 'session.cli')
            ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
        $this->app  = $this->container->get(Application::class);
        $lang       = $this->container->get(LanguageFactoryInterface::class)->createLanguage($this->app->get('language'), $this->app->get('debug_lang'));

        // Load the language to the API
        $this->app->loadLanguage($lang);
        $lang      = $this->app->getLanguage();
        $lang->load('com_blc', JPATH_ADMINISTRATOR);

        // Register the language object with Factory
        // Factory::$language = $this->app->getLanguage();
        Factory::$application = $this->app;
        $this->app->loadDocument();

        $this->db         = Factory::getContainer()->get(DatabaseInterface::class);
        $this->dispatcher = $this->container->get(DispatcherInterface::class);
        //to prevent a warning: Test code or tested code did not close its own output buffers
        $this->app->set('debug', false);
        // Load the behaviour plugins
        PluginHelper::importPlugin('behaviour', null, true, $this->getDispatcher());

        // Trigger the onAfterInitialise event.
        PluginHelper::importPlugin('system', null, true, $this->getDispatcher());
        if (version_compare(JVERSION, '5.0', '<')) {
            /** @disregard */
            $this->app->triggerEvent('onAfterInitialise');
        } else {
            $this->getDispatcher()->dispatch(
                'onAfterInitialise',
                new AfterInitialiseEvent('onAfterInitialise', ['subject' => $this->app])
            );
        }
        $this->setUser();
        //create and clear logger
        Log::addLogger(['logger' => 'inmemory', 'group' => 'info'], Log::INFO, []);
        Log::addLogger(['logger' => 'inmemory', 'group' => 'error'], Log::ERROR, []);
        $this->clearMemoryLogger();
    }

    protected function clearMemoryLogger()
    {
        $loggerOptions   = ['group' => 'phpunit'];
        $logger          = new InMemoryLogger($loggerOptions);
        $protectedMethod = (fn () => static::$logEntries = []);
        $protectedMethod->call($logger);
    }



    public function assetLoggerInfo(int $count = 0, string $group = 'info'): void
    {
        $loggerOptions = ['group' => $group];
        $logger        = new InMemoryLogger($loggerOptions);
        $logEntries    = $logger->getCollectedEntries();
        $this->assertEquals(
            $count,
            \count($logEntries),
            join(
                "\n",
                array_column(
                    $logEntries,
                    'message'
                )
            )
        );
    }


    protected function setUser($user = 'phpunit', $action = null, $assetKey = null): void
    {
        $isUser = $this->app->loadIdentity();
        if (! ($isUser->id ?? false)) {
            $user = $this->container->get(UserFactoryInterface::class)->loadUserByUsername($user);
            $this->app->loadIdentity($user);
        }
    }


    protected function getModel($component, $model, $client = 'Administrator', array $config = ['ignore_request' => true])
    {
        $mvcFactory = $this->app->bootComponent($component)->getMVCFactory();
        return $mvcFactory->createModel($model, $client, $config);
    }

    /**
     * this loads the plugin stand outside the joomla application
     */
    protected function bootPlugin()
    {
        $config =  (array)PluginHelper::getPlugin('tasks', 'deltrash') ?? [];


        $dispatcher = $this->getDispatcher();

        $plugin     = new Deltrash($dispatcher, $config);
        $plugin->setApplication($this->app);
        if (method_exists($plugin, 'setDatabase')) {
            $plugin->setDatabase($this->db);
        }
        //  $protectedMethod = (fn() => $this->snapshot['logCategory'] = 'phpunit');
        //  $protectedMethod->call($plugin);


        return $plugin;
    }
}
