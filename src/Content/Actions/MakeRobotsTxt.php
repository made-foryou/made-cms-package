<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\RobotsRule;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Support\Site;

final class MakeRobotsTxt
{
    use AsAction;

    public function __construct(
        private readonly SitePrefixes $prefixes,
    ) {}

    /**
     * robots.txt of the site: the rules per robot, every robot first, with a comment per origin that says who set them,
     * and the sitemap of every route group at the bottom. Without rules every robot may go everywhere: a page Google
     * may not show gets noindex, never a Disallow, so Google can read that noindex.
     */
    public function handle(): string
    {
        $groups = ['*' => ['userAgent' => '*', 'rules' => []]];

        foreach (ListRobotsRules::run() as $rule) {
            $key = strtolower($rule->userAgent);
            $groups[$key] ??= ['userAgent' => $rule->userAgent, 'rules' => []];
            $groups[$key]['rules'][] = $rule;
        }

        $blocks = array_map(self::group(...), array_values($groups));
        $sitemaps = array_map(
            static fn (string $prefix): string => 'Sitemap: '.Site::url(SitePrefixes::path($prefix, 'sitemap.xml')),
            $this->prefixes->prefixes,
        );

        if ($sitemaps !== []) {
            $blocks[] = implode("\n", $sitemaps);
        }

        return implode("\n\n", $blocks)."\n";
    }

    /**
     * @param  array{userAgent: string, rules: list<RobotsRule>}  $group
     */
    private static function group(array $group): string
    {
        $lines = ['User-agent: '.$group['userAgent']];
        $origin = null;

        foreach ($group['rules'] as $rule) {
            if ($rule->origin !== $origin) {
                $origin = $rule->origin;
                $lines[] = '# '.(string) __('made-cms-package::sitemap.robots.'.$origin->value);
            }

            $lines[] = 'Disallow: '.$rule->path;
        }

        if ($group['rules'] === []) {
            $lines[] = 'Disallow:';
        }

        return implode("\n", $lines);
    }
}
