<?php

namespace App\Models;

use App\Enums\MerchantTeamRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantTeamMember extends Model
{
    protected $fillable = [
        'merchant_id',
        'user_id',
        'role',
        'permissions',
        'is_active',
    ];

    protected $casts = [
        'role'        => MerchantTeamRole::class,
        'permissions' => 'array',
        'is_active'   => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? $this->role->defaultPermissions();

        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }
}
