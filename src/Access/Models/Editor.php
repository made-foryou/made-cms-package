<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Models;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Access\Builders\EditorBuilder;
use Made\CmsPackage\Database\Factories\Access\EditorFactory;

/**
 * An account of the CMS. Editors live next to the users of the host site and never mix with them.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string|null $remember_token
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
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
