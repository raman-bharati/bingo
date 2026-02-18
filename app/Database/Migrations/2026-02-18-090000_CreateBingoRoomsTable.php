<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBingoRoomsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'room_code' => [
                'type' => 'VARCHAR',
                'constraint' => 8,
            ],
            'state' => [
                'type' => 'TEXT',
            ],
            'version' => [
                'type' => 'INT',
                'unsigned' => true,
                'default' => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('room_code');
        $this->forge->createTable('bingo_rooms');
    }

    public function down()
    {
        $this->forge->dropTable('bingo_rooms');
    }
}
