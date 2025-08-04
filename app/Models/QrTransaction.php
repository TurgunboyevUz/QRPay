<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrTransaction extends Model
{
    protected $guarded = [];
    protected $casts   = [
        'details' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(QrOrder::class, 'qr_order_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
