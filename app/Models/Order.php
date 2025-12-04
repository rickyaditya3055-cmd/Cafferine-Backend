<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id','subtotal','tax','shipping','discount','total',
        'payment_method','payment_ref','promo_code','notes','external_id','email'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id', 'order_id');
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class, 'order_id', 'order_id')->latest();
    }
}