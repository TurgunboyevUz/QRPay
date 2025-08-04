<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrOrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts   = [
        'total_amount' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(QrOrder::class, 'qr_order_id', 'id');
    }
}
