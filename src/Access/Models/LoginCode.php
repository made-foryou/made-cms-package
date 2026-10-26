<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Access\Builders\LoginCodeBuilder;
use Made\CmsPackage\Database\Factories\Access\LoginCodeFactory;

/**
 * A one-time login code that was sent to an e-mail address, with the link in the same mail.
 * It only works in the browser that asked for it (session_hash).
 *
 * @property int $id
 * @property string $email
 * @property string $code_hash
 * @property int $attempts
 * @property string $session_hash
 * @property string $link_hash
 * @property string|null $return_to
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static LoginCodeBuilder query()
 */
class LoginCode extends Model
{
    /** @use HasFactory<LoginCodeFactory> */
    use HasFactory;

    protected $table = 'cms_login_codes';

    protected $fillable = [
        'email',
        'code_hash',
        'attempts',
        'session_hash',
        'link_hash',
        'return_to',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'code_hash',
        'session_hash',
        'link_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): LoginCodeBuilder
    {
        return new LoginCodeBuilder($query);
    }

    protected static function newFactory(): LoginCodeFactory
    {
        return LoginCodeFactory::new();
    }
}
