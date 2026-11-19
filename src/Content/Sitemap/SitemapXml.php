<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Sitemap;

use Dom\XMLDocument;
use DOMException;
use Made\CmsPackage\Content\Data\SitemapDocument;
use Made\CmsPackage\Content\Data\SitemapEntry;
use ValueError;

/**
 * The xml of a sitemap as Google reads it (sitemaps.org 0.9), without priority or change frequency, which Google
 * ignores. One sitemap holds at most 50,000 addresses and 50 MB: the addresses after the limit are left out, so the
 * file stays valid, and the caller reports them.
 */
final readonly class SitemapXml
{
    public const int MAX_ADDRESSES = 50_000;

    public const int MAX_BYTES = 50 * 1024 * 1024;

    private const string NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public function __construct(
        private int $maxAddresses = self::MAX_ADDRESSES,
        private int $maxBytes = self::MAX_BYTES,
    ) {}

    /**
     * @param  iterable<SitemapEntry>  $entries
     */
    public function write(iterable $entries): SitemapDocument
    {
        $head = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="'.self::NAMESPACE.'">'."\n";
        $foot = '</urlset>'."\n";
        $bytes = strlen($head) + strlen($foot);
        $urls = [];

        foreach ($entries as $entry) {
            $url = self::url($entry);

            if (count($urls) === $this->maxAddresses || $bytes + strlen($url) > $this->maxBytes) {
                break;
            }

            $urls[] = $url;
            $bytes += strlen($url);
        }

        return new SitemapDocument($head.implode('', $urls).$foot, count($urls));
    }

    /**
     * The addresses a sitemap lists, also one of another site, without its namespace or of a sitemap index; null when
     * it is no xml. Entities from outside the file are never loaded.
     *
     * @return list<string>|null
     */
    public static function locations(string $xml): ?array
    {
        try {
            $document = XMLDocument::createFromString($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (DOMException|ValueError) {
            return null;
        }

        $locations = [];

        foreach ($document->getElementsByTagNameNS('*', 'loc') as $element) {
            $location = trim((string) $element->textContent);

            if ($location !== '') {
                $locations[] = $location;
            }
        }

        return $locations;
    }

    private static function url(SitemapEntry $entry): string
    {
        $lines = '  <url>'."\n".'    <loc>'.htmlspecialchars($entry->url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'."\n";

        if ($entry->lastModified !== null) {
            $lines .= '    <lastmod>'.$entry->lastModified->toW3cString().'</lastmod>'."\n";
        }

        return $lines.'  </url>'."\n";
    }
}
