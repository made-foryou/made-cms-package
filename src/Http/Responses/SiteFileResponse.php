<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Content\Models\Sitemap;

/**
 * The files of the site robots read: the sitemap and robots.txt. Plain answers, without a redirect, a launcher or a
 * login.
 */
final class SiteFileResponse
{
    /**
     * The sitemap as Google reads it, with the moment it was built: a robot that has it already gets a 304.
     */
    public static function sitemap(Sitemap $sitemap, Request $request): Response
    {
        $response = new Response($sitemap->xml, Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setLastModified($sitemap->built_at);
        $response->isNotModified($request);

        return $response;
    }

    public static function robots(string $text): Response
    {
        return new Response($text, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
