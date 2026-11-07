<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Closure;
use Made\CmsPackage\Content\Models\Page;

/**
 * A link to a page of the site, stored as {"page": id}, or to an address, stored as {"url": "…"}.
 *
 * A page keeps working when its path changes. An address is http(s), mailto, tel,
 * a path on the site ("/lid-worden") or an anchor ("#over-ons").
 */
final class Link extends Field
{
    public const int MAX_URL_LENGTH = 2048;

    // A path never starts with two slashes, also not with "/\": the browser reads both as another site.
    private const string URL_PATTERN = '~^(?:https?://\S+|mailto:\S+|tel:\S+|/(?![/\\\\])\S*|#\S*)$~i';

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function type(): string
    {
        return 'link';
    }

    public function rules(string $path): array
    {
        return [$path => [$this->presence(), 'array:page,url', self::target()]];
    }

    public function normalize(mixed $value): mixed
    {
        return self::normalizeTarget($value);
    }

    public function render(mixed $value): ?string
    {
        return self::resolve($value);
    }

    /**
     * The rule that wants exactly one existing page or one valid address.
     */
    public static function target(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $page = $value['page'] ?? null;
            $url = $value['url'] ?? null;

            if (($page === null) === ($url === null)) {
                $fail(self::message('link'));

                return;
            }

            if ($page !== null) {
                if (! self::pageExists($page)) {
                    $fail(self::message('link_page'));
                }

                return;
            }

            if (! self::isAddress($url)) {
                $fail(self::message('link_url'));
            }
        };
    }

    /**
     * Stores a page as its id and an address without the spaces around it.
     */
    public static function normalizeTarget(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (isset($value['page']) && is_numeric($value['page'])) {
            return ['page' => (int) $value['page']];
        }

        if (isset($value['url']) && is_string($value['url'])) {
            return ['url' => trim($value['url'])];
        }

        return $value;
    }

    /**
     * The url of a stored link: a page as the current path of its address, an address as it is.
     * A page a visitor cannot open (gone, not published, without an address) has no url, and neither has an address
     * the validation would refuse, such as a script that reached the database another way (a seeder, an import).
     */
    public static function resolve(mixed $value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        $page = $value['page'] ?? null;

        if (is_int($page) || (is_string($page) && ctype_digit($page))) {
            return Page::query()->published()->whereKey((int) $page)->first()?->route?->path;
        }

        $url = $value['url'] ?? null;

        return self::isAddress($url) ? $url : null;
    }

    /**
     * Whether the value is an address an editor may give: https, http, mailto, tel, a path on the site or an anchor.
     *
     * @phpstan-assert-if-true string $value
     */
    private static function isAddress(mixed $value): bool
    {
        return is_string($value) && mb_strlen($value) <= self::MAX_URL_LENGTH && preg_match(self::URL_PATTERN, $value) === 1;
    }

    private static function pageExists(mixed $page): bool
    {
        if (! is_int($page) && ! (is_string($page) && ctype_digit($page))) {
            return false;
        }

        return Page::query()->whereKey((int) $page)->exists();
    }

    private static function message(string $key): string
    {
        return (string) __('made-cms-package::strips.validation.'.$key);
    }
}
