<?php
declare(strict_types=1);

namespace AuditStash\Test\TestCase\Model\Behavior;

use ArrayObject;
use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\Model\Behavior\AuditLogBehavior;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SplObjectStorage;

class AuditLogBehaviorTest extends TestCase
{
    /**
     * Test table reference.
     *
     * @var Table
     */
    public Table $table;

    /**
     * Mock persister reference.
     *
     * @var MockObject
     */
    public MockObject $persister;

    /**
     * Audit behavior reference.
     *
     * @var AuditLogBehavior
     */
    public AuditLogBehavior $behavior;

    /**
     * Test setup.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->table = new Table(['table' => 'articles']);
        $this->table->setPrimaryKey('id');
        $this->behavior = new AuditLogBehavior($this->table, [
            'whitelist' => ['id', 'title', 'body', 'author_id'],
        ]);
    }

    /**
     * Test that save event with whitelist keeps whitelisted column.
     *
     * @return void
     */
    public function testOnSaveCreateWithWhitelist()
    {
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));
        $result = $queue[$entity];
        $this->assertEquals($result->getOriginal(), $result->getChanged());
        unset($data['something_extra']);
        $this->assertEquals($data, $result->getChanged());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditCreateEvent::class, $result);
    }

    /**
     * Test that update event with whitelist keeps whitelisted column.
     *
     * @return void
     */
    public function testOnSaveUpdateWithWhitelist()
    {
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => false, 'markClean' => true]);
        $entity->title = 'Another Title';

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));
        $result = $queue[$entity];
        $this->assertEquals(['title' => 'Another Title'], $result->getChanged());
        $this->assertEquals(['title' => 'The Title'], $result->getOriginal());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditUpdateEvent::class, $result);
    }

    /**
     * Test that create event with blacklist removes blacklisted columns.
     *
     * @return void
     */
    public function testSaveCreateWithBlacklist()
    {
        $this->behavior->setConfig('blacklist', ['author_id']);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));
        $result = $queue[$entity];
        $this->assertEquals($result->getOriginal(), $result->getChanged());
        unset($data['something_extra'], $data['author_id']);
        $this->assertEquals($data, $result->getChanged());
    }

    /**
     * Test that update event with blacklist removes blacklisted columns.
     *
     * @return void
     */
    public function testSaveUpdateWithBlacklist()
    {
        $this->behavior->setConfig('blacklist', ['author_id']);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
        ];
        $entity = new Entity($data, ['markNew' => false, 'markClean' => true]);
        $entity->author_id = 50;

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));

        $this->assertFalse(isset($queue[$entity]));
    }

    /**
     * Test that create event whitelists the table schema.
     *
     * @return void
     */
    public function testSaveWithFieldsFromSchema()
    {
        $this->table->setSchema([
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'text'],
            'body' => ['type' => 'text'],
        ]);
        $this->behavior->setConfig('whitelist', false);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);
        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));
        $result = $queue[$entity];
        unset($data['something_extra'], $data['author_id']);
        $this->assertEquals($data, $result->getChanged());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditCreateEvent::class, $result);
    }
}
