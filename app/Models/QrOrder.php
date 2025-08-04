<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrOrder extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'amount' => 'integer'
    ];

    public function items()
    {
        return $this->hasMany(QrOrderItem::class, 'qr_order_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(QrOrder::class, 'qr_order_id', 'id');
    }
}
