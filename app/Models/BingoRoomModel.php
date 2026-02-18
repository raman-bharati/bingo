<?php

namespace App\Models;

use CodeIgniter\Model;

class BingoRoomModel extends Model
{
    protected $table = 'bingo_rooms';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'room_code',
        'state',
        'version',
    ];
}
