<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'currier_id',
        'price',
        'date',
        'status',
        'location',
    ];
    public function currier()
    {
        return $this->belongsTo(User::class, 'currier_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
