<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Models;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Made\CmsPackage\Access\Builders\EditorBuilder;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Database\Factories\Access\EditorFactory;

/**
 * An account of the CMS. Editors live next to the users of the host site and never mix with them.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property Collection<int, Role> $roles
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes sha256 hashes of the unused recovery codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_used_timestep
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static EditorBuilder query()
 */
class Editor extends Model implements AuthenticatableContract
{
    use Authenticatable;

    /** @use HasFactory<EditorFactory> */
    use HasFactory;

    protected $table = 'cms_editors';

    protected $fillable = [
        'name',
        'email',
        'password',
        'roles',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_last_used_timestep',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'roles' => AsEnumCollection::of(Role::class),
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'two_factor_last_used_timestep' => 'integer',
        ];
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): EditorBuilder
    {
        return new EditorBuilder($query);
    }

    protected static function newFactory(): EditorFactory
    {
        return EditorFactory::new();
    }
}
