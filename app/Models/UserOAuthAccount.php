<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * \Pterodactyl\Models\UserOAuthAccount.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_id
 * @property string|null $email
 * @property string|null $display_name
 * @property string|null $avatar
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property \Illuminate\Support\Carbon|null $token_expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User $user
 *
 * @method static \Database\Factories\UserOAuthAccountFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|UserOAuthAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserOAuthAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserOAuthAccount query()
 *
 * @mixin \Eloquent
 */
class UserOAuthAccount extends Model
{
    /** @use HasFactory<\Database\Factories\UserOAuthAccountFactory> */
    use HasFactory;

    public const RESOURCE_NAME = 'oauth_account';

    protected $table = 'user_oauth_accounts';

    protected $fillable = [
        'provider',
        'provider_id',
        'email',
        'display_name',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
    ];

    public static array $validationRules = [
        'user_id' => 'required|exists:users,id',
        'provider' => 'required|string|max:32',
        'provider_id' => 'required|string|max:191',
        'email' => 'nullable|string|max:191',
        'display_name' => 'nullable|string|max:191',
        'avatar' => 'nullable|string|max:2048',
        'access_token' => 'nullable|string',
        'refresh_token' => 'nullable|string',
        'token_expires_at' => 'nullable|date',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
