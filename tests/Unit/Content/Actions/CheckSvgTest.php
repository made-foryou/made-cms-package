<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CheckSvg;

function svgDrawing(string $body, string $attributes = ''): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10"'.$attributes.'>'.$body.'</svg>';
}

function svgRefusal(string $contents): ?string
{
    try {
        CheckSvg::run($contents);

        return null;
    } catch (ValidationException $exception) {
        return $exception->errors()['file'][0] ?? '';
    }
}

it('accepts a drawing as a drawing program exports it', function (string $contents): void {
    expect(svgRefusal($contents))->toBeNull();
})->with([
    'the logo fixture' => [(string) file_get_contents(__DIR__.'/../../../Fixtures/Media/logo.svg')],
    'shapes' => [svgDrawing('<g><path d="M0 0h10v10z"/><circle cx="5" cy="5" r="2"/></g>')],
    'a reference inside the drawing' => [svgDrawing('<defs><path id="a" d="M0 0h1"/></defs><use href="#a"/><use xlink:href="#a"/>')],
    'a gradient in a style' => [svgDrawing('<style>.a{fill:url(#g)} .b{fill:url( "#g" )}</style><rect class="a" style="fill:url(#g)"/>')],
    'an embedded photo' => [svgDrawing('<image href="data:image/png;base64,iVBORw0KGgo=" width="10" height="10"/>')],
    'an animation of a colour' => [svgDrawing('<rect><animate attributeName="opacity" from="0" to="1" dur="1s"/><set attributeName="fill" to="red"/></rect>')],
    'the metadata of Inkscape' => [svgDrawing('<sodipodi:namedview xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" pagecolor="#fff"/><metadata><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/></metadata>')],
    'a link to a place in the drawing' => [svgDrawing('<a href="#top"><rect/></a>')],
]);

it('refuses a drawing with active content', function (string $contents): void {
    expect(svgRefusal($contents))->toBe(__('made-cms-package::media.errors.svg_unsafe'));
})->with([
    'a script' => [svgDrawing('<script>alert(document.cookie)</script>')],
    'a script in capitals' => [svgDrawing('<SCRIPT>alert(1)</SCRIPT>')],
    'an event attribute on the root' => [svgDrawing('<rect/>', ' onload="alert(1)"')],
    'an event attribute in capitals' => [svgDrawing('<rect ONCLICK="alert(1)"/>')],
    'html inside the drawing' => [svgDrawing('<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject>')],
    'an html script by its namespace' => [svgDrawing('<h:script xmlns:h="http://www.w3.org/1999/xhtml">alert(1)</h:script>')],
    'an iframe' => [svgDrawing('<iframe src="https://example.com"/>')],
    'a javascript link' => [svgDrawing('<a href="javascript:alert(1)"><rect/></a>')],
    'a javascript link with xlink' => [svgDrawing('<a xlink:href=" JavaScript:alert(1)"><rect/></a>')],
    'a link to elsewhere' => [svgDrawing('<a href="https://example.com"><rect/></a>')],
    'a reference to another file' => [svgDrawing('<use href="https://example.com/sprite.svg#a"/>')],
    'a photo from elsewhere' => [svgDrawing('<image href="https://example.com/track.png"/>')],
    'an embedded drawing' => [svgDrawing('<image href="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4="/>')],
    'a filter image from elsewhere' => [svgDrawing('<filter id="f"><feImage href="https://example.com/a.png"/></filter>')],
    'an animation of a link' => [svgDrawing('<a href="#a"><set attributeName="href" to="javascript:alert(1)"/><rect/></a>')],
    'an animation of an xlink' => [svgDrawing('<a xlink:href="#a"><animate attributeName="xlink:href" values="javascript:alert(1)"/><rect/></a>')],
    'an animation of an event' => [svgDrawing('<rect><set attributeName="onclick" to="alert(1)"/></rect>')],
    'a style sheet instruction' => ['<?xml-stylesheet type="text/xsl" href="https://example.com/x.xsl"?>'.svgDrawing('<rect/>')],
    'an entity' => ['<!DOCTYPE svg [<!ENTITY x "y">]>'.svgDrawing('<text>&x;</text>')],
    'an import in a style' => [svgDrawing('<style>@import url(https://example.com/x.css);</style>')],
    'a url to elsewhere in a style' => [svgDrawing('<style>.a{background:url(https://example.com/track.png)}</style>')],
    'a url to elsewhere in a style attribute' => [svgDrawing('<rect style="fill:url(\'https://example.com/a.svg#g\')"/>')],
    'an escape in a style' => [svgDrawing('<style>.a{background:\75 rl(https://example.com/a.png)}</style>')],
]);

it('refuses a file it cannot read as a drawing', function (string $contents): void {
    expect(svgRefusal($contents))->toBe(__('made-cms-package::media.errors.unreadable'));
})->with([
    'nothing' => [''],
    'broken markup' => ['<svg xmlns="http://www.w3.org/2000/svg"><g></svg>'],
    'another root' => ['<html xmlns="http://www.w3.org/1999/xhtml"><svg xmlns="http://www.w3.org/2000/svg"/></html>'],
    'an svg outside its namespace' => ['<svg viewBox="0 0 10 10"/>'],
]);
