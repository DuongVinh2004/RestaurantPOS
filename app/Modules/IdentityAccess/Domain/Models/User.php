<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\Models;

use App\Modules\IdentityAccess\Domain\Models\BankAccount;
use App\Support\Persistence\HasRowVersion;
use App\Modules\Loyalty\Domain\Models\LoyaltyPointTransaction;
use App\Modules\Loyalty\Domain\Models\LoyaltyTier;
use App\Modules\Loyalty\Domain\Models\UserPoint;
use App\Modules\Loyalty\Domain\Models\UserTierHistory;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Conversations\Domain\Models\Conversation;
use App\Modules\PrivacyCompliance\Domain\Models\CustomerPrivacyRequest;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasRowVersion;

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'username',
        'full_name',
        'email',
        'phone',
        'role_id',
        'current_tier_id',
        'language_pref',
        'is_deleted',
        'privacy_anonymized_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'user_id' => 'int',
        'role_id' => 'int',
        'current_tier_id' => 'int',
        'is_deleted' => 'bool',
        'privacy_anonymized_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'row_version' => 'int',
    ];

    public function getAuthPassword(): string
    {
        return (string) ($this->password_hash ?? '');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function points(): HasOne
    {
        return $this->hasOne(UserPoint::class, 'user_id', 'user_id');
    }

    public function currentTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'current_tier_id', 'tier_id');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyPointTransaction::class, 'user_id', 'user_id');
    }

    public function tierHistory(): HasMany
    {
        return $this->hasMany(UserTierHistory::class, 'user_id', 'user_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'user_id', 'user_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'user_id', 'user_id');
    }

    public function customerAccessSessions(): HasMany
    {
        return $this->hasMany(CustomerAccessSession::class, 'user_id', 'user_id');
    }

    public function createdPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by', 'user_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_id', 'user_id');
    }

    public function privacyRequests(): HasMany
    {
        return $this->hasMany(CustomerPrivacyRequest::class, 'user_id', 'user_id');
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('username', 'like', "%{$term}%")
                ->orWhere('full_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
