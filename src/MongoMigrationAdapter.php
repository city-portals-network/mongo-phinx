<?php

namespace MongoPhinx;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Phinx\Db\Action\AddIndex;
use Phinx\Db\Action\DropIndex;
use Phinx\Db\Action\DropTable;
use Phinx\Db\Adapter\AdapterInterface as PhinxAdapter;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\Table;
use Phinx\Migration\MigrationInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MongoMigrationAdapter implements PhinxAdapter
{
    const COLLECTION_NAME = 'phinx_migration';

    /** @var InputInterface $consoleInput */
    protected $consoleInput;

    /** @var OutputInterface $consoleOutput */
    protected $consoleOutput;

    /** @var Client $mongoClient */
    protected $mongoClient;

    /** @var Database $database */
    protected $database;

    /** @var Collection $collection */
    protected $collection;

    /** @var string $databaseName */
    protected $databaseName;

    /** @var string $uri */
    protected $uri;

    protected $session;

    protected $options = [
        'table_prefix' => ''
    ];

    function __construct(array $options)
    {
        $this->databaseName = $options['name'];
        $this->uri = $options['uri'];
        $this->options = $options;
        $this->connect();
    }

    public function getVersions()
    {
        return array_keys($this->getVersionLog());
    }

    public function getVersionLog()
    {
        $result = [];
        $rows = $this->getMigrationCollection()->find();
        foreach ($rows as $row){
            $result[$row['version']] = $row;
        }
        return $result;
    }

    public function setOptions(array $options)
    {
        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function hasOption($name)
    {
        return true;
    }

    public function getOption($name)
    {
        if(isset($this->options[$name])) {
            return $this->options[$name];
        }
        return null;
    }

    public function setInput(InputInterface $input)
    {
        $this->consoleInput = $input;
        return $this;
    }

    public function getInput()
    {
        return $this->consoleInput;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->consoleOutput = $output;
        return $this;
    }

    public function getOutput()
    {
        return $this->consoleOutput;
    }

    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if(strcasecmp($direction, MigrationInterface::UP) === 0) {
            $this->getMigrationCollection()->insertOne([
                'name' => $migration->getName(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'version' => $migration->getVersion(),
                'breakpoint' => false
            ]);
        } else {
            $this->getMigrationCollection()->deleteOne([
                'version' => $migration->getVersion()
            ]);
        }
        return $this;
    }

    public function toggleBreakpoint(MigrationInterface $migration)
    {
        return $this;
    }

    public function resetAllBreakpoints()
    {
    }

    public function hasSchemaTable()
    {
        return true;
    }

    public function createSchemaTable()
    {

    }

    public function getAdapterType()
    {
        return null;
    }

    public function connect()
    {
        $this->mongoClient = new Client($this->uri);
        $this->database = $this->mongoClient->selectDatabase($this->databaseName);
        $this->collection = $this->database->selectCollection(self::COLLECTION_NAME);
    }

    public function disconnect()
    {
    }

    public function hasTransactions()
    {
        return false;
    }

    public function beginTransaction()
    {
    }

    public function commitTransaction()
    {
    }

    public function rollbackTransaction()
    {
    }

    public function execute($sql)
    {
        throw new \InvalidArgumentException("Don't know how to execute");
    }

    public function executeActions(Table $table, array $actions)
    {
        foreach ($actions as $action) {
            switch (true) {
                case ($action instanceof AddIndex):
                    $columns = $action->getIndex()->getColumns();
                    $options = [];
                    $indexName = $action->getIndex()->getName();
                    if(!empty($indexName)){
                        $options['name'] = $indexName;
                    }
                    $this->getDatabase()
                        ->selectCollection($action->getTable()->getName())
                        ->createIndex($columns, $options);
                    break;

                case ($action instanceof DropIndex):
                    $indexName = $action->getIndex()->getName();
                    if(!empty($indexName)) {
                        $this->getDatabase()->selectCollection($action->getTable()->getName())
                            ->dropIndex($indexName);
                    }
                    break;

                case ($action instanceof DropTable):
                    $this->getDatabase()
                        ->dropCollection($action->getTable()->getName());
                    break;

                default:
                    throw new \InvalidArgumentException(
                        sprintf("Don't know how to execute action: '%s'", get_class($action))
                    );
            }
        }
    }

    public function getQueryBuilder()
    {
        return null;
    }

    public function query($sql)
    {
    }

    public function fetchRow($sql)
    {
    }

    public function fetchAll($sql)
    {
    }

    public function insert(Table $table, $row)
    {
        $this->getDatabase()->selectCollection($table->getName())->insertOne($row);
    }

    public function bulkinsert(Table $table, $rows)
    {
        $this->getDatabase()->selectCollection($table->getName())->insertMany($rows);
    }

    public function quoteTableName($tableName)
    {
        return str_replace('.', '`.`', $this->quoteColumnName($tableName));
    }

    public function quoteColumnName($columnName)
    {
        return '`' . str_replace('`', '``', $columnName) . '`';
    }

    public function hasTable($tableName)
    {
        return true;
    }

    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
    }

    public function truncateTable($tableName)
    {
        $this->getDatabase()->dropCollection($tableName);
    }

    public function getColumns($tableName)
    {
        return [];
    }

    public function hasColumn($tableName, $columnName)
    {
        return true;
    }

    public function hasIndex($tableName, $columns)
    {
        $indexes = $this->getDatabase()->selectCollection($tableName)->listIndexes();
        $this->hasValues($indexes, $columns);
    }

    public function hasIndexByName($tableName, $indexName)
    {
        $indexes = $this->getDatabase()->selectCollection($tableName)->listIndexes();
        $this->hasValue($indexes, $indexName);
    }

    public function hasPrimaryKey($tableName, $columns, $constraint = null)
    {
        return true;
    }

    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        return false;
    }

    public function getColumnTypes()
    {
        return [
            self::PHINX_TYPE_BIG_INTEGER,
            self::PHINX_TYPE_BOOLEAN,
            self::PHINX_TYPE_INTEGER,
            self::PHINX_TYPE_STRING,
            self::PHINX_TYPE_FLOAT
        ];
    }

    public function isValidColumnType(Column $column)
    {
        return true;
    }

    public function getSqlType($type, $limit = null)
    {
        return [];
    }

    public function createDatabase($name, $options = [])
    {
    }

    public function hasDatabase($name)
    {
        return true;
    }

    public function dropDatabase($name)
    {
        $this->mongoClient->dropDatabase($name);
    }

    public function createSchema($schemaName = 'public')
    {
    }

    public function dropSchema($schemaName)
    {
        $this->getDatabase()->dropCollection($schemaName);
    }

    public function castToBool($value)
    {
    }

    protected function getMigrationCollection()
    {
        return $this->collection;
    }

    protected function getDatabase()
    {
        return $this->database;
    }

    protected function hasValue(\Iterator $iterator, $value)
    {
        foreach ($iterator as $iteration) {
            if ($iteration == $value){
                return true;
            }
        }
        return false;
    }

    protected function hasValues(\Iterator $iterator, $values)
    {
        foreach ($iterator as $iteration) {
            if ($this->hasValue($iteration, $values)){
                return true;
            }
        }
        return false;
    }

    public function getConnection()
    {
        return $this->mongoClient;
    }
}