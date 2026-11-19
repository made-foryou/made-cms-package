<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Symfony\Component\HttpFoundation\Response;

/**
 * The answers to the tree of the pages screen, which reads the screen again after every change.
 */
final class PageTreeResponse
{
    /**
     * The page is in its new place; there is nothing to answer.
     */
    public static function moved(): Response
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
