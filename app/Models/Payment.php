<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reference',
        'order_id',
        'amount',
        'payment_method',
        'status',
        'user_id',
        'description',
        'qr_string',
        'gateway_ref',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}