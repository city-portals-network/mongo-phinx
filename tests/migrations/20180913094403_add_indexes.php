<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class AddIndexes extends AbstractMigration
{

    public function up()
    {
        $table = $this->table('table_with_indexes');
        $table->addIndex(['index1' => 1, 'index2' => -1], ['name' => 'index_1', 'unique' => 'true'])->save();
        $table->addIndex(['simple_index' => 1], ['name' => 'simple'])->save();
    }

    public function down()
    {
        $table = $this->table('table_with_indexes');
        $table->removeIndexByName('index_1')->removeIndexByName('simple')->save();
    }
}
