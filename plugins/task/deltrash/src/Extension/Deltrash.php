<?php

/**
 * @package    plg_task_deltrash
 * @subpackage  Task.DelTrash
 *
 * @copyright 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Joomla\Plugin\Task\Deltrash\Extension;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class Deltrash extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var string[]
     * @since 4.1.0
     */
    protected const TASKS_MAP = [
        'plg_task_deltrash' => [
            'langConstPrefix' => 'PLG_TASK_DELTRASH',
            'form'            => 'deltrash_parameters',
            'method'          => 'deleteTrash',
        ],

    ];

    /**
     * The application object.
     *
     * @var  CMSApplication
     * @since 4.1.0
     */
    protected $app;

    /**
     * @var  DatabaseInterface
     * @since  4.1.0
     */
    protected $db;

    /**
     * Autoload the language file.
     *
     * @var boolean
     * @since 4.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 4.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event
     *
     * @return void
     *
     * @since 4.1.0
     * @throws Exception
     */
    public function deleteTrash(ExecuteTaskEvent $event): int
    {
        $params = $event->getArgument('params');

        $userID    = $params->user ?? 0;
        if (!$userID) {
            $this->logTask(Text::_('PLG_TASK_DELTRASH_USERREQUIRED'), 'error');
            return Status::KNOCKOUT;
        }

        $this->loginById($userID);


        if ($params->articles ?? false) {
            $this->delArticles();
        }

        if ($params->categories ?? false) {
            $components = $params->components ?? [];

            foreach ($components as $component) {
                $this->delCategories($component);
            }
        }

        if ($params->modules ?? false) {
            $module = $params->moduletype ?? [];
            $this->delModules($module);
        }

        if ($params->redirects ?? false) {
            $purge = $params->redirectspurge ?? false;
            $this->delRedirects($purge);
        }

        if ($params->tags ?? false) {
            $this->delTags();
        }

        if ($params->tasks ?? false) {
            $this->delTasks();
        }

        if ($params->contacts ?? false) {
            $this->delContacts();
        }

        if ($params->menus ?? false) {
            $menus = $params->menutype ?? [];
            $this->delMenuItems($menus);
        }

        return Status::OK;
    }

    private function delCategories($component): void
    {
        $cat    = 0;
        $noleaf = 0;
        $cmodel = $this->app->bootComponent('com_categories')
            ->getMVCFactory()
            ->createModel('Categories', 'Administrator', ['ignore_request' => true]);
        $cmodel->setState('filter.published', -2);
        $cmodel->setState('filter.extension', $component);
        $cmodel->setState('category.extension', $component);
        $this->app->input->set('extension', $component);

        $ctrashed = $cmodel->getItems();

        $model = $this->app->bootComponent('com_categories')
            ->getMVCFactory()
            ->createModel('Category', 'Administrator', ['ignore_request' => true]);
        $model->setCurrentUser($this->app->getIdentity());

        foreach ($ctrashed as $item) {
            if (!$model->delete($item->id)) {
                $noleaf++;
            }

            $cat++;
        }

        if ($cat - $noleaf > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_CATEGORIES_DELETED', $component, $cat - $noleaf), 'info');
        }

        if ($noleaf > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_NOLEAF', $component, $noleaf), 'info');
        }
    }


    private function delArticles(): void
    {
        $art = 0;
        /** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
        $model = $this->app->bootComponent('com_content')
            ->getMVCFactory()->createModel('Articles', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.published', -2);
        $atrashed = $model->getItems();

        /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
        //$amodel = $this->app->bootComponent('com_content')
        //    ->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);
        // Dirty hack for the inafmous Workflow
        $table = Table::getInstance('Content');
        foreach ($atrashed as $item) {
            if ($table->delete($item->id)) {
                $art++;
            }
            $db    = $this->getDatabase();
            // frontpage
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__content_frontpage'))
                ->where($db->quoteName('content_id') . ' = :contentid')
                ->bind(':contentid', $item->id, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();
            // tags ??
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentitem_tag_map'))
                ->where($db->quoteName('content_item_id') . '= :contentitemid')
                ->bind(':contentitemid', $item->id, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__ucm_content'))
                ->where($db->quoteName('core_content_item_id') . '= :corecontentitemid')
                ->bind(':corecontentitemid', $item->id, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__ucm_base'))
                ->where($db->quoteName('ucm_item_id') . '= :ucmitemid')
                ->bind(':ucmitemid', $item->id, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();
            // versions
            $a     = 'com_content.article.' . $item->id;
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__history'))
                ->where($db->quoteName('item_id') . '= :ucmitemid')
                ->bind(':ucmitemid', $a);
            $db->setQuery($query);
            $db->execute();
            // infamous workflow
            $extension = 'com_content.article';
            $query     = $db->getQuery(true)
                ->delete($db->quoteName('#__workflow_associations'))
                ->where($db->quoteName('item_id') . '= :wrkflid')
                ->where($db->quoteName('extension') . ' = :extension')
                ->bind(':wrkflid', $item->id, ParameterType::INTEGER)
                ->bind(':extension', $extension);
            $db->setQuery($query);
            $db->execute();
            // language associations
            /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
            $amodel = $this->app->bootComponent('com_associations')
                ->getMVCFactory()->createModel('Associations', 'Administrator', ['ignore_request' => true]);
            $amodel->clean();
        }

        if ($art > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_ARTICLES', $art), 'info');
        }
    }

    private function delModules(array $type = []): void
    {
        $mod      = 0;
        $strashed = [];
        $atrashed = [];

        if (\in_array('site', $type)) {
            /** @var \Joomla\Component\Modules\Administrator\Model\ModuleModel $model */
            $model = $this->app->bootComponent('com_modules')->getMVCFactory()
                ->createModel('Modules', 'Administrator', ['ignore_request' => true]);
            $model->setState('filter.state', -2);
            $strashed = $model->getItems();
        }

        if (\in_array('admin', $type)) {
            $gmodel = $this->app->bootComponent('com_modules')->getMVCFactory()
                ->createModel('Modules', 'Administrator', ['ignore_request' => true]);
            $gmodel->setState('filter.client_id', 1);
            $gmodel->setState('client_id', 1);
            $gmodel->setState('filter.state', -2);
            $atrashed = $gmodel->getItems();
        }

        $trashed = array_merge($strashed, $atrashed);
        /** @var \Joomla\Component\Modules\Administrator\Model\ModuleModel $model */
        $mmodel = $this->app->bootComponent('com_modules')->getMVCFactory()
            ->createModel('Module', 'Administrator', ['ignore_request' => true]);
        $mmodel->setCurrentUser($this->app->getIdentity());

        foreach ($trashed as $item) {
            if ($mmodel->delete($item->id)) {
                $mod++;
            }
        }

        if ($mod > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_MODULES_DELETED', $mod), 'info');
        }
    }

    private function delRedirects(bool $purge = false): void
    {
        $red = 0;
        /** @var \Joomla\Component\Redirect\Administrator\Model\LinksModel $model */
        $model = $this->app->bootComponent('com_redirect')
            ->getMVCFactory()->createModel('Links', 'Administrator', ['ignore_request' => true]);

        if ($purge && $model->purge()) {
            $this->logTask(Text::_('PLG_TASK_DELTRASH_REDIRECTS_PURGED'), 'info');
        }

        $model->setState('filter.state', -2);
        $trashed = $model->getItems();

        $model = $this->app->bootComponent('com_redirect')
            ->getMVCFactory()->createModel('Link', 'Administrator', ['ignore_request' => true]);

        foreach ($trashed as $item) {
            if ($model->delete($item->id)) {
                $red++;
            }
        }

        if ($red > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_REDIRECTS_TRASHED', $red), 'info');
        }
    }

    private function delTags(): void
    {
        $art = 0;
        /** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
        $model = $this->app->bootComponent('com_tags')
            ->getMVCFactory()->createModel('Tags', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.published', -2);
        $model->setState('filter.extension', '');
        $atrashed = $model->getItems();

        /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
        $amodel = $this->app->bootComponent('com_tags')
            ->getMVCFactory()->createModel('Tag', 'Administrator', ['ignore_request' => true]);

        foreach ($atrashed as $item) {
            if ($amodel->delete($item->id)) {
                $art++;
            }
        }

        if ($art > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_TAGS', $art), 'info');
        }
    }

    private function delTasks(): void
    {
        $art = 0;
        /** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
        $model = $this->app->bootComponent('com_scheduler')
            ->getMVCFactory()->createModel('Tasks', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.state', -2);
        $atrashed = $model->getItems();

        /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
        $amodel = $this->app->bootComponent('com_scheduler')
            ->getMVCFactory()->createModel('Task', 'Administrator', ['ignore_request' => true]);
        $amodel->setCurrentUser($this->app->getIdentity());

        foreach ($atrashed as $item) {
            if ($amodel->delete($item->id)) {
                $art++;
            }
        }

        if ($art > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_TASKS', $art), 'info');
        }
    }

    private function delMenuItems(array $type = []): void
    {
        $art      = 0;
        $strashed = [];
        $atrashed = [];

        if (\in_array('admin', $type)) {
            /** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
            $model = $this->app->bootComponent('com_menus')
                ->getMVCFactory()->createModel('Items', 'Administrator', ['ignore_request' => true]);
            $model->setState('filter.published', -2);
            $model->setState('filter.client_id', 1);
            $model->setState('client_id', 1);
            $atrashed = $model->getItems();
        }

        if (\in_array('site', $type)) {
            /** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
            $model = $this->app->bootComponent('com_menus')
                ->getMVCFactory()->createModel('Items', 'Administrator', ['ignore_request' => true]);
            $model->setState('filter.published', -2);
            $strashed = $model->getItems();
        }

        $trashed = array_merge($strashed, $atrashed);
        /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
        $mmodel = $this->app->bootComponent('com_menus')
            ->getMVCFactory()->createModel('Item', 'Administrator', ['ignore_request' => true]);
        $mmodel->setCurrentUser($this->app->getIdentity());

        foreach ($trashed as $item) {
            if ($mmodel->delete($item->id)) {
                $art++;
            }
        }

        if ($art > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_MENUITEMS', $art), 'info');
        }
    }

    private function delContacts(): void
    {
        $art = 0;
        /** @var \Joomla\Component\Contact\Administrator\Model\ContactsModel $model */
        $model = $this->app->bootComponent('com_contact')
            ->getMVCFactory()->createModel('Contacts', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.published', -2);
        $atrashed = $model->getItems();

        /** @var \Joomla\Component\Contact\Administrator\Model\ContactModel $model */
        $amodel = $this->app->bootComponent('com_contact')
            ->getMVCFactory()->createModel('Contact', 'Administrator', ['ignore_request' => true]);

        $amodel->setCurrentUser($this->app->getIdentity());

        foreach ($atrashed as $item) {
            if ($amodel->delete($item->id)) {
                $art++;
            }
        }

        if ($art > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_CONTACTS_DELETED', $art), 'info');
        }
    }

    private function loginById(int $userId): void
    {
        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
        $this->app->loadIdentity($user);
    }
}
