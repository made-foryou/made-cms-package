<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\RobotsRule;
use Made\CmsPackage\Content\Enums\RobotsRuleOrigin;

final class ListRobotsRules
{
    use AsAction;

    /**
     * The Disallow rules of robots.txt: those the developer set in config (`sitemap.robots`, paths per user agent), in
     * their order. What cannot be written as a rule (no text, empty, a line break) is left out instead of refused,
     * because robots.txt must never fail: Google stops crawling a site whose robots.txt answers with an error.
     *
     * @return list<RobotsRule>
     */
    public function handle(): array
    {
        $configured = config('made-cms-package.sitemap.robots');

        if (! is_array($configured)) {
            return [];
        }

        $rules = [];

        foreach ($configured as $userAgent => $paths) {
            $userAgent = is_string($userAgent) ? self::line($userAgent) : null;

            if ($userAgent === null) {
                continue;
            }

            foreach (is_array($paths) ? $paths : [$paths] as $path) {
                $path = is_string($path) ? self::line($path) : null;

                if ($path !== null) {
                    $rules[] = new RobotsRule($userAgent, $path, RobotsRuleOrigin::Config);
                }
            }
        }

        return $rules;
    }

    /**
     * The value without the spaces around it, or null when nothing is left or it would run onto a line of its own.
     */
    private static function line(string $value): ?string
    {
        $value = trim($value);

        return $value === '' || preg_match('/[\r\n]/', $value) === 1 ? null : $value;
    }
}
