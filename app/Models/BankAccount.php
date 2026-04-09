<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    protected $table = 'bank_accounts';
    protected $primaryKey = 'bank_account_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_holder_name',
        'bank_account_number',
        'is_default',
    ];

    protected $casts = [
        'bank_account_id' => 'int',
        'user_id' => 'int',
        'bank_name' => 'string',
        'account_holder_name' => 'string',
        'is_default' => 'bool',
        'default_user_id' => 'int',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
