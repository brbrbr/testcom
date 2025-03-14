<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Tests;



use Joomla\Plugin\Task\Deltrash\Extension\Deltrash;
use Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;
use Joomla\Component\Categories\Administrator\Table\CategoryTable;
use Joomla\CMS\Access\Access;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Task;

/**
 * Test class for Contacts
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */

class DelTrashCategoryTest extends UnitTestCase
{
    private $model;
    private $table;
    protected $entryStub = [
        'id' => 0,
        'parent_id' => '1',
        'extension' => '',
        'title' => '',
        'alias' => '',
        'version_note' => '',
        'note' => '',
        'description' => '',
        'published' => '1',
        'access' => 1,
        'metadesc' => '',
        'metakey' => '',
        'created_user_id' => '1',
        'language' => '*',
        'params' => ['category_layout' => '', 'image' => '', 'image_alt' => '',],
        'metadata' => ['author' => '', 'robots' => '',],
        'tags' => [],
    ];
    //the plugin calls it component - com_categories extensions
    protected $component = 'com_phpunit';


    public function setUp(): void
    {
        $this->initApplication();
        $this->model = $this->getModel('com_categories', 'Category');
        $this->model->setCurrentUser($this->app->getIdentity());
        //categories can't be load by 'true' pk only by id.
        $this->table = new CategoryTable($this->getDatabase());
    }

    /**
     * this will count all entries that are not in our extensioncomponent
     */

    protected function countCategories()
    {
        $db = $this->getDatabase();
        $query = $db->createQuery();
        $query->select('count(*)')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' != :extension')
            ->bind(':extension', $this->component);
        $db->setquery($query);
        return $db->loadResult();
    }

    /**
     * @param string $suffix
     * @return string[]
     * 
     * 
     */
    public function createEntry(string $suffix = '')
    {

        $stub = $this->entryStub;
        /* create a category entry with an 'other' extension */
        $stub['extension'] = $this->component . $suffix;
        $stub['alias'] = 'phpunit-' . uniqid();
        $stub['title'] = ucwords($this->component . ' ' . $stub['alias'], " -");
        $result = $this->model->save($stub);
        $msg = $this->model->getError() ?: '';
        $this->assertTrue($result, $msg);
        $pk = [
            'extension' =>  $stub['extension'],
            'alias' => $stub['alias'],
        ];

        $result = $this->table->load($pk);
        $this->assertTrue($result);
        $pk['id'] = $this->table->id;
        //this avoids warnings in the Log
        Access::preload([$this->table->asset_id], reload: true);
        return $pk;
    }

    public function trashEntry($pk)
    {
        //trash it
        //model takes id's only
        $pks = [$pk['id']];
        $this->getApplication()->getInput()->set('extension',  $pk['extension']);
        $result = $this->model->publish($pks, $this->trashedState);
        $msg = $this->model->getError() ?: '';
        $this->assertTrue($result, $msg);


        $result = $this->table->load($pk);
        $this->assertTrue($result);

        $this->assertSame(
            $this->trashedState,
            $this->table->published,
            (string)$this->table->id
        );
    }

    public function testDeleteCategorydeleteTrashEvent()
    {
        $params = new \stdClass();
       
        $plugin = $this->bootPlugin();
      
        $event = ExecuteTaskEvent::create(
            'onExecuteTask',
            [
                'eventClass'      => ExecuteTaskEvent::class,
                'subject'         => $this->createStub(Task::class),
                'routineId'       => 'plg_task_deltrash',
                'langConstPrefix' => 'PLG_TASK_DELTRASH',
                'params'          => $params,
            ]
        );
        $pkOurs = $this->createEntry('_event');
        $this->trashEntry($pkOurs);
        //nothing set
        $plugin->deleteTrash($event);
        $this->assetLoggerInfo(1,'error');
        $this->clearMemoryLogger();

        $params->user =  $this->app->getIdentity()->id;
        $plugin->deleteTrash($event);
        $result = $this->table->load($pkOurs);
        $this->assertTrue($result);
        $this->assetLoggerInfo(0,'info');
        $this->assetLoggerInfo(0,'error');

        $params->categories = 1;
        $plugin->deleteTrash($event);
        $result = $this->table->load($pkOurs);
        //not deleted?
        $this->assertTrue($result);
        $this->assetLoggerInfo(0,'info');
        $this->assetLoggerInfo(0,'error');

        //category  set do but wrong component
        $params->components = [
            "com_other",

        ];
        $plugin->deleteTrash($event);
        $result = $this->table->load($pkOurs);
        //not deleted?
        $this->assertTrue($result);
        $this->assetLoggerInfo(0,'info');
        $this->assetLoggerInfo(0,'error');

        //category  set correct component  guest user 
        $this->setUser('phpunit-guest');
        $params->user =  $this->app->getIdentity()->id;
        $params->components = [
            "com_other",
            $pkOurs['extension'],
        ];
        $plugin->deleteTrash($event);
        $result = $this->table->load($pkOurs);
        //deleted?
        $this->assertTrue($result);
        $this->assetLoggerInfo(1,'info'); // NOLEAF
        $this->assetLoggerInfo(0,'error');
        $this->clearMemoryLogger();
        //category  set correct component  valid user
        $this->setUser();
        $params->user =  $this->app->getIdentity()->id;
        $params->components = [
            "com_other",
            $pkOurs['extension'],
        ];
        $plugin->deleteTrash($event);
        $result = $this->table->load($pkOurs);
        //deleted?
        $this->assertFalse($result);
        $this->assetLoggerInfo(1,'info'); // DELETED
        $this->assetLoggerInfo(0,'error');
        
    }
    public function testDeleteCategory()
    {

        $plugin = $this->bootPlugin();
        /** @phpstan-ignore method.notFound */
        $protectedMethod = (fn($extension) => $this->delCategories($extension));


        //create 'other' stub an trashit
        $pkOther = $this->createEntry('_other');
        $this->trashEntry($pkOther);

        /* this will include the pkOther entry */
        $startOtherCategories = $this->countCategories();

        $pkOurs = $this->createEntry('');

        /* test no deleted */
        $protectedMethod->call($plugin, $pkOurs['extension']);

        $result = $this->table->load($pkOurs);
        $this->assertTrue($result);

        //set delete state - model takes id's
        $this->trashEntry($pkOurs);

        $msg = $this->model->getError() ?: '';
        $this->assertTrue($result, $msg);

        // deleted state = trashedState

        $result = $this->table->load($pkOurs);
        $this->assertTrue($result);

        $this->assertSame(
            $this->trashedState,
            $this->table->published,
            (string)$this->table->id
        );

        /* delete it */

        $protectedMethod->call($plugin, $pkOurs['extension']);
        $this->assetLoggerInfo(1);
        //us deleted ?

        $result = $this->table->load($pkOurs);
        $this->assertFalse($result);

        //check np other categories are deleted including the _other
        $endOtherCategories = $this->countCategories();
        $this->assertSame($startOtherCategories, $endOtherCategories);
        //explicitly check the _other entry
        //categories can't be load by 'true' pk only by id.

        $result = $this->table->load($pkOther);
        $this->assertTrue($result);

        /* test the _other deleted */
        $protectedMethod->call($plugin, $pkOther['extension']);
        $this->assetLoggerInfo(2);


        $result = $this->table->load($pkOther);
        $this->assertFalse($result);
    }
}
