<?php

namespace MongoPhinx\Tests\integration;

use MongoDB\Client;
use MongoDB\Database;
use MongoPhinx\MongoMigrationAdapter;
use Phinx\Console\PhinxApplication;
use Phinx\Db\Adapter\AdapterFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class MigrationsTest extends TestCase
{
    /** @var PhinxApplication $app */
    protected $app;

    /** @var Database $database */
    protected $database;

    const DATABASE_NAME = 'migration_test';

    public function setUp()
    {
        AdapterFactory::instance()->registerAdapter('mongo', MongoMigrationAdapter::class);
        $app = new PhinxApplication();
        $app->setAutoExit(false);
        $this->app = $app;
        $this->database = (new Client())->selectDatabase(self::DATABASE_NAME);
    }

    /**
     * @throws \Exception
     */
    public function testInsert()
    {
        $this->app->run(new StringInput('migrate -e development -c ../phinx.yml -t 20180913043440'),
            new NullOutput());
        $row = $this->database->selectCollection('from_migration')->findOne(['simple' => 'hello world']);
        $this->assertTrue(!empty($row));
        $this->assertEquals('any text', $row['first_field']);
    }

    /**
     * @throws \Exception
     */
    public function testAddIndexes()
    {
        $this->app->run(new StringInput('migrate -e development -c ../phinx.yml -t 20180913094403'),
            new NullOutput());
        $indexes = $this->database->selectCollection('table_with_indexes')->listIndexes();
        $list = self::_GetIndexArray($indexes);
        $need_index = ['index1' => 1, 'index2' => -1];
        $this->assertContains($need_index, $list);
        $need_index = ['simple_index' => 1];
        $this->assertContains($need_index, $list);
        $row = $this->database->selectCollection('from_migration')->findOne(['simple' => 'hello world']);
        $this->assertTrue(!empty($row));
        $this->assertEquals('any text', $row['first_field']);
    }

    /**
     * @throws \Exception
     */
    public function testRollback()
    {
        $this->app->run(new StringInput('migrate -e development -c ../phinx.yml'), new NullOutput());
        $this->app->run(new StringInput('rollback -e development -c ../phinx.yml -t 0'),
            new NullOutput());
        $indexes = $this->database->selectCollection('table_with_indexes')->listIndexes();
        $list = self::_GetIndexArray($indexes);
        $need_index = ['simple_index' => 1];
        $this->assertNotContains($need_index, $list);

        $row = $this->database->selectCollection('from_migration')->findOne(['simple' => 'hello world']);
        $this->assertTrue(empty($row));
    }

    public function tearDown()
    {
        $this->database->drop();
    }

    private static function _GetIndexArray(\Iterator $indexes): array
    {
        $list = [];
        foreach ($indexes as $index) {
            $list[] = $index['key'];
        }
        return $list;
    }
}