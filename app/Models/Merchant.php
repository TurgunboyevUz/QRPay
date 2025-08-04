<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    use HasFactory;

    protected $guarded = [];
    
    protected $casts   = [
        'click' => 'array',
        'payme' => 'array',
        'uzum'  => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
