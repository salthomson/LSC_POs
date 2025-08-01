<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KhqrTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'khqr_string',
        'amount',
        'currency_code',
        'reference_number',
        'bank_transaction_id',
        'status',
        'response_data',
        'expires_at',
    ];

    // Define relationship to Sale model if you have one
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}