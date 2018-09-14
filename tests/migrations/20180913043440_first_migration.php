<?php


use Phinx\Migration\AbstractMigration;

class FirstMigration extends AbstractMigration
{

    public function up()
    {
        $table = $this->table('from_migration');
        $table->insert([
            'first_field' => 'any text',
            'simple' => 'hello world'
        ])->save();
    }

    public function down()
    {
        $this->table('from_migration')->drop()->save();
    }
}