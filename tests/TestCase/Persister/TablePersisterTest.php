<?php
declare(strict_types=1);

namespace AuditStash\Test\TestCase\Persister;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Persister\TablePersister;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;

class AuditLogsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('audit_logs');
        $this->setPrimaryKey('id');

        $this->setSchema([
            'id' => 'integer',
            'transaction' => 'string',
            'type' => 'string',
            'primary_key' => 'integer',
            'display_value' => 'string',
            'source' => 'string',
            'parent_source' => 'string',
            'original' => 'string',
            'changed' => 'string',
            'meta' => 'string',
            'created' => 'datetime',
        ]);
    }
}

class TablePersisterTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \AuditStash\Persister\TablePersister
     */
    public TablePersister $TablePersister;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->TablePersister = new TablePersister();
        $this->getTableLocator()->setConfig('AuditLogs', [
            'className' => AuditLogsTable::class,
        ]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->TablePersister);

        parent::tearDown();
    }

    /**
     * Tests that TablePersister defaults are correct.
     *
     * @return void
     */
    public function testConfigDefaults()
    {
        $expected = [
            'extractMetaFields' => false,
            'logErrors' => true,
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_AUTOMATIC,
            'serializeFields' => true,
            'table' => 'AuditLogs',
            'unsetExtractedMetaFields' => true,
        ];
        $this->assertEquals($expected, $this->TablePersister->getConfig());
    }

    /**
     * Test getting the default table.
     *
     * @return void
     */
    public function testGetTableDefault()
    {
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
    }

    /**
     * Test setting the table alias.
     *
     * @return void
     */
    public function testSetTableAsAlias()
    {
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
        $this->assertInstanceOf(TablePersister::class, $this->TablePersister->setTable('Custom'));
        $this->assertInstanceOf(Table::class, $this->TablePersister->getTable());
        $this->assertEquals('Custom', $this->TablePersister->getTable()->getAlias());
    }

    /**
     * Test that a table object may be used for the persister.
     *
     * @return void
     */
    public function testSetTableAsObject()
    {
        $customTable = $this->getTableLocator()->get('Custom');
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
        $this->assertInstanceOf(TablePersister::class, $this->TablePersister->setTable($customTable));
        $this->assertSame($customTable, $this->TablePersister->getTable());
    }

    /**
     * Test that providing an invalid table fails.
     *
     * @return void
     */
    public function testSetInvalidTable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The `$table` argument must be either a table alias, or an instance of `\Cake\ORM\Table`.');
        $this->TablePersister->setTable(null);
    }

    /**
     * Test that creating an event with null values can be serialized.
     *
     * @throws \Exception
     */
    public function testSerializeNull()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, null, null, null);
        $event->setMetaInfo([]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => null,
            'original' => null,
            'changed' => null,
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => json_encode([]),
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);

        $this->TablePersister->setTable($AuditLogsTable);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that events extract provided meta fields.
     *
     * @throws \Exception
     */
    public function testExtractMetaFields()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, [], [], 'testExtractMetaFields');
        $event->setMetaInfo([
            'foo' => 'bar',
            'baz' => [
                'nested' => 'value',
                'bar' => 'foo',
            ],
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testExtractMetaFields',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '{"baz":{"bar":"foo"}}',
            'foo' => 'bar',
            'nested' => 'value',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => [
                'foo',
                'baz.nested' => 'nested',
            ],
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that all meta fields may be set to extract.
     *
     * @throws \Exception
     */
    public function testExtractAllMetaFields()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, [], [], 'testExtractAllMetaFields');
        $event->setMetaInfo([
            'foo' => 'bar',
            'baz' => [
                'nested' => 'value',
                'bar' => 'foo',
            ],
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testExtractAllMetaFields',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '[]',
            'foo' => 'bar',
            'baz' => [
                'nested' => 'value',
                'bar' => 'foo',
            ],
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => true,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that meta fields are not unset when specified.
     *
     * @throws \Exception
     */
    public function testExtractMetaFieldsDoNotUnset()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, [], [], 'testExtractMetaFieldsDoNotUnset');
        $event->setMetaInfo([
            'foo' => 'bar',
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testExtractMetaFieldsDoNotUnset',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '{"foo":"bar"}',
            'foo' => 'bar',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => [
                'foo',
            ],
            'unsetExtractedMetaFields' => false,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that all meta fields are not unset when requested.
     *
     * @throws \Exception
     */
    public function testExtractAllMetaFieldsDoNotUnset()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, [], [], 'testExtractAllMetaFieldsDoNotUnset');
        $event->setMetaInfo([
            'foo' => 'bar',
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testExtractAllMetaFieldsDoNotUnset',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '{"foo":"bar"}',
            'foo' => 'bar',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => true,
            'unsetExtractedMetaFields' => false,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that error logging works.
     *
     * @throws \Exception
     */
    public function testErrorLogging()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, [], [], 'testErrorLogging');

        /** @var \AuditStash\Persister\TablePersister|\PHPUnit\Framework\MockObject\MockObject $TablePersister */
        $TablePersister = $this
            ->getMockBuilder(TablePersister::class)
            ->onlyMethods(['log'])
            ->getMock();

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_source' => 'testErrorLogging',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '[]',
        ]);

        $logged = clone $entity;
        $logged->setError('field', ['error']);
        $logged->setSource('AuditLogs');

        $TablePersister
            ->expects($this->once())
            ->method('log');

        $TablePersister->getTable()->getEventManager()->on(
            'Model.beforeSave',
            function ($event, EntityInterface $entity) {
                $entity->setError('field', ['error']);

                return false;
            }
        );

        $TablePersister->logEvents([$event]);
    }

    /**
     * Test that error logging can be disabled.
     *
     * @throws \Exception
     */
    public function testDisableErrorLogging()
    {
        /** @var \AuditStash\Persister\TablePersister|\PHPUnit\Framework\MockObject\MockObject $TablePersister */
        $TablePersister = $this
            ->getMockBuilder(TablePersister::class)
            ->onlyMethods(['log'])
            ->getMock();

        $TablePersister
            ->expects($this->never())
            ->method('log');

        $TablePersister->setConfig([
            'logErrors' => false,
        ]);
        $TablePersister->getTable()->getEventManager()->on(
            'Model.beforeSave',
            function ($event, EntityInterface $entity) {
                return false;
            }
        );

        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, [], [], 'testDisableErrorLogging');
        $TablePersister->logEvents([$event]);
    }

    /**
     * Test that a compound primary key can be extracted according to default strategy.
     *
     * @throws \Exception
     */
    public function testCompoundPrimaryKeyExtractDefault()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', null, [], [], 'testCompoundPrimaryKeyExtractDefault');

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testCompoundPrimaryKeyExtractDefault',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => '[1,2,3]',
            'meta' => '[]',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'string');

        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that a primary key can be extracted according to the raw strategy.
     *
     * @throws \Exception
     */
    public function testPrimaryKeyExtractRaw()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, [], [], 'testPrimaryKeyExtractRaw');

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testPrimaryKeyExtractRaw',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '[]',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_RAW,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that extracting a compound primary key can be extracted according to the raw strategy.
     *
     * @throws \Exception
     */
    public function testCompoundPrimaryKeyExtractRaw()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', null, [], [], 'testCompoundPrimaryKeyExtractRaw');

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testCompoundPrimaryKeyExtractRaw',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => [1, 2, 3],
            'meta' => '[]',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'json');

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_RAW,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that a primary key can be extracted according to the properties strategy.
     *
     * @throws \Exception
     */
    public function testPrimaryKeyExtractProperties()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, [], [], 'testPrimaryKeyExtractProperties');

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testPrimaryKeyExtractProperties',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '[]',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_PROPERTIES,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that a compound primary key can be extracted according to the properties strategy.
     *
     * @throws \Exception
     */
    public function testCompoundPrimaryKeyExtractProperties()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', null, [], [], 'testCompoundPrimaryKeyExtractProperties');

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testCompoundPrimaryKeyExtractProperties',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key_0' => 1,
            'primary_key_1' => 2,
            'primary_key_2' => 3,
            'meta' => '[]',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_PROPERTIES,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that a primary key can be extracted according to the serialized strategy.
     *
     * @throws \Exception
     */
    public function testPrimaryKeyExtractSerialized()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 'pk', 'source', null, [], [], 'testPrimaryKeyExtractSerialized');

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testPrimaryKeyExtractSerialized',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => '"pk"',
            'meta' => '[]',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'string');

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_SERIALIZED,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that a compound primary key can be extracted according to the serialized strategy.
     *
     * @throws \Exception
     */
    public function testCompoundPrimaryKeyExtractSerialized()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', null, [], [], 'testCompoundPrimaryKeyExtractSerialized');

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testCompoundPrimaryKeyExtractSerialized',
            'original' => '[]',
            'changed' => '[]',
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => '[1,2,3]',
            'meta' => '[]',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'string');

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_SERIALIZED,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Test that extracted fields can be extracted without serialization.
     *
     * @throws \Exception
     */
    public function testDoNotSerializeFields()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, [], [], 'testDoNotSerializeFields');
        $event->setMetaInfo([
            'foo' => 'bar',
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'testDoNotSerializeFields',
            'original' => [],
            'changed' => [],
            'created' => new DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => [
                'foo' => 'bar',
            ],
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('original', 'json');
        $AuditLogsTable->getSchema()->setColumnType('changed', 'json');
        $AuditLogsTable->getSchema()->setColumnType('meta', 'json');

        $this->TablePersister->setConfig([
            'serializeFields' => false,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * Get the mock for this model.
     *
     * @param string $alias The model to get a mock for.
     * @param array<string> $methods The list of methods to mock
     * @param array<string, mixed> $options The config data for the mock's constructor.
     * @return Table|MockObject
     */
    public function getMockForModel(string $alias, array $methods = [], array $options = []): Table|MockObject
    {
        return parent::getMockForModel($alias, $methods, $options + [
            'className' => AuditLogsTable::class,
        ]);
    }
}
