<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'order_code',
        'product_name',
        'currency',
        'amount',
        'tax',
        'service_charge',
        'delivery_charge',
        'total_amount',
        'status',
        'esewa_ref_id',
        'esewa_status',
        'callback_payload',
        'verified_at',
        'paid_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
