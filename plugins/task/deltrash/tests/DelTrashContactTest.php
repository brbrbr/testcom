<?php

/**
 * @version   24.44.6882
 * @package    plg_task_deltrash
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Tests;

use Joomla\CMS\Access\Access;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Task;

/**
 * Test class for Contacts
 *
 * @package    plg_task_deltrash
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */

class DelTrashContactTest extends UnitTestCase
{
    private $contactStub = ['name' => 'test', 'alias' => 'test', 'version_note' => '', 'user_id' => '', 'published' => '1', 'catid' => '4', 'access' => 1, 'misc' => '', 'created_by' => '1', 'created_by_alias' => '', 'created' => '2024-03-09 16:04:13', 'modified' => '2024-03-09 16:04:13', 'publish_up' => '', 'publish_down' => '', 'metakey' => '', 'metadesc' => '', 'language' => '*', 'featured' => '0', 'image' => '', 'con_position' => '', 'email_to' => '', 'address' => '', 'suburb' => '', 'state' => '', 'postcode' => '', 'country' => '', 'telephone' => '', 'mobile' => '', 'fax' => '', 'webpage' => '', 'sortname1' => '', 'sortname2' => '', 'sortname3' => '', 'params' => ['show_contact_category' => '', 'show_contact_list' => '', 'show_tags' => '', 'show_info' => '', 'show_name' => '', 'show_position' => '', 'show_email' => '', 'add_mailto_link' => '', 'show_street_address' => '', 'show_suburb' => '', 'show_state' => '', 'show_postcode' => '', 'show_country' => '', 'show_telephone' => '', 'show_mobile' => '', 'show_fax' => '', 'show_webpage' => '', 'show_image' => '', 'show_misc' => '', 'allow_vcard' => '', 'show_articles' => '', 'articles_display_num' => '', 'show_profile' => '', 'contact_layout' => '', 'show_links' => '', 'linka_name' => '', 'linka' => '', 'linkb_name' => '', 'linkb' => '', 'linkc_name' => '', 'linkc' => '', 'linkd_name' => '', 'linkd' => '', 'linke_name' => '', 'linke' => '', 'show_email_form' => '', 'show_email_copy' => '', 'validate_session' => '', 'custom_reply' => '', 'redirect' => ''], 'metadata' => ['robots' => '', 'rights' => ''], 'schema' => ['extendJed' => ''], 'tags' => []];
    private $model;
    //the plugin calls it component - com_categories extensions
    protected $component = 'com_contact';


    public function setUp(): void
    {
        $this->initApplication();

        $this->model = $this->getModel('com_contact', 'Contact');
        $this->model->setCurrentUser($this->app->getIdentity());
    }

    /**
     *
     * @return string[]
     *
     *
     */
    public function createEntry()
    {
        $stub = $this->contactStub;
        /* create a category entry with an 'other' extension */
        $stub['user_id'] = $this->app->getIdentity();
        $stub['alias']   = 'phpunit-' . uniqid();
        $stub['name']    = ucwords($this->component . ' ' . $stub['alias'], " -");
        $result          = $this->model->save($stub);
        $msg             = $this->model->getError() ?: '';
        $this->assertTrue($result, $msg);
        $pk = [
            'alias' => $stub['alias'],
        ];

        $contact = $this->model->getItem($pk);
        $this->assertNotFalse($contact);
        $pk['id'] = $contact->id;
        //this avoids warnings in the Log

        Access::preload([$contact->asset_id], reload: true);
        return $pk;
    }

    public function trashEntry($pk)
    {
        //trash it
        //model takes id's only
        $pks    = [$pk['id']];
        $result = $this->model->publish($pks, $this->trashedState);
        $msg    = $this->model->getError() ?: '';
        $this->assertTrue($result, $msg);


        $contact = $this->model->getItem($pk);
        $this->assertNotFalse($contact);

        $this->assertSame(
            $this->trashedState,
            $contact->published,
            (string)$contact->id
        );
    }

    public function testDeleteContactdeleteTrashEvent()
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
                'params'          => $params, //reference
            ]
        );
        $pk = $this->createEntry('_event');
        $this->trashEntry($pk);
        //nothing set
        $plugin->deleteTrash($event);
        $this->assetLoggerInfo(1, 'error');
        $this->clearMemoryLogger();

        $params->user =  $this->app->getIdentity()->id;
        $plugin->deleteTrash($event);
        $contact = $this->model->getItem($pk);
        $this->assertNotFalse($contact);
        $this->assetLoggerInfo(0, 'info');
        $this->assetLoggerInfo(0, 'error');


        //contacts set but guest user
        $params->contacts = 1;
        $this->setUser('phpunit-guest');
        $params->user =  $this->app->getIdentity()->id;
        $plugin->deleteTrash($event);
        $contact = $this->model->getItem($pk);
        $this->assertNotFalse($contact);
        $this->assetLoggerInfo(0, 'info');
        $this->assetLoggerInfo(0, 'error');

        //contacts set and valid user
        $this->setUser();
        $params->user =  $this->app->getIdentity()->id;
        $plugin->deleteTrash($event);
        $contact = $this->model->getItem($pk);
        //deleted?
        $this->assertFalse($contact);
        $this->assetLoggerInfo(1, 'info'); // DELETED
        $this->assetLoggerInfo(0, 'error');
    }



    public function testDeleteContact()
    {


        $plugin = $this->bootPlugin();

        /** @phpstan-ignore method.notFound */
        $protectedMethod = (fn () => $this->delContacts());



        $pk = $this->createEntry();
        $protectedMethod->call($plugin);

        $contact = $this->model->getItem($pk);
        $this->assertNotFalse($contact);
        $this->assetLoggerInfo(0, 'error');


        $this->trashEntry($pk);
        $protectedMethod->call($plugin);
        $contact = $this->model->getItem($pk);
        $this->assertFalse($contact);
        $this->assetLoggerInfo(1);
    }
}
