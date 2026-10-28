<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;

/**
 * What the blocks of the start page show. Static for now, as in the design (7.1–7.5); each block later comes
 * from its context. An editor with one role gets the example of that role, an admin or an editor with several
 * roles the combined one. Every item points at a screen (null is the help of MADE) and is left out when the
 * editor does not open that screen, so the page never links to a 403.
 *
 * @phpstan-type Chip array{label: string, create: bool, target: Screen|null}
 * @phpstan-type Recent array{title: string, meta: string, status: 'draft'|'live', target: Screen|null}
 * @phpstan-type Attention array{severity: 'high'|'medium'|'low', title: string, meta: string, action: string, domain: string|null, target: Screen|null}
 * @phpstan-type Figure array{figure: string, text: string, sub: string}
 * @phpstan-type Quick array{label: string, target: Screen|null}
 * @phpstan-type Sample array{
 *     summary: string,
 *     chips: list<Chip>,
 *     recent: list<Recent>,
 *     attention: list<Attention>,
 *     attention_footer: string,
 *     figures: list<Figure>,
 *     figures_link: Quick|null,
 *     quick: list<Quick>,
 *     updated: string,
 * }
 * @phpstan-type Blocks array{
 *     summary: string,
 *     chips: list<array{label: string, create: bool, url: string}>,
 *     recent: list<array{title: string, meta: string, status: 'draft'|'live', url: string}>,
 *     recent_all: string|null,
 *     attention: list<array{severity: 'high'|'medium'|'low', title: string, meta: string, action: string, domain: string|null, url: string}>,
 *     urgent: int,
 *     attention_footer: string,
 *     figures: list<Figure>,
 *     figures_link: array{label: string, url: string}|null,
 *     quick: list<array{label: string, url: string}>,
 *     updated: string,
 * }
 */
final class StartPageSample
{
    /**
     * @param  array<Role>  $roles
     * @param  list<Screen>  $screens  the screens of the editor (ListScreens)
     * @return Blocks
     */
    public static function for(array $roles, array $screens, ?string $helpUrl): array
    {
        $role = count($roles) === 1 ? array_values($roles)[0] : Role::Admin;
        $sample = self::samples()[$role->value];

        $url = fn (?Screen $target): ?string => match (true) {
            $target === null => $helpUrl,
            in_array($target, $screens, true) => Menu::url($target),
            default => null,
        };

        $chips = [];
        foreach ($sample['chips'] as $chip) {
            if (($to = $url($chip['target'])) !== null) {
                $chips[] = ['label' => $chip['label'], 'create' => $chip['create'], 'url' => $to];
            }
        }

        $recent = [];
        foreach ($sample['recent'] as $item) {
            if (($to = $url($item['target'])) !== null) {
                $recent[] = ['title' => $item['title'], 'meta' => $item['meta'], 'status' => $item['status'], 'url' => $to];
            }
        }

        $attention = [];
        foreach ($sample['attention'] as $item) {
            if (($to = $url($item['target'])) !== null) {
                $attention[] = [
                    'severity' => $item['severity'],
                    'title' => $item['title'],
                    'meta' => $item['meta'],
                    'action' => $item['action'],
                    'domain' => $item['domain'],
                    'url' => $to,
                ];
            }
        }

        $quick = [];
        foreach ($sample['quick'] as $item) {
            if (($to = $url($item['target'])) !== null) {
                $quick[] = ['label' => $item['label'], 'url' => $to];
            }
        }

        $figuresUrl = $sample['figures_link'] === null ? null : $url($sample['figures_link']['target']);

        return [
            'summary' => $sample['summary'],
            'chips' => $chips,
            'recent' => $recent,
            'recent_all' => $url(Screen::Pages),
            'attention' => $attention,
            'urgent' => count(array_filter($attention, fn (array $item): bool => $item['severity'] === 'high')),
            'attention_footer' => $sample['attention_footer'],
            'figures' => $sample['figures'],
            'figures_link' => $sample['figures_link'] === null || $figuresUrl === null
                ? null
                : ['label' => $sample['figures_link']['label'], 'url' => $figuresUrl],
            'quick' => $quick,
            'updated' => $sample['updated'],
        ];
    }

    /**
     * @return array<string, Sample>
     */
    private static function samples(): array
    {
        $updated = 'Laatst bijgewerkt vanochtend 08:12.';

        return [
            Role::ContentManager->value => [
                'summary' => 'Sinds maandag is één pagina als concept blijven staan en vragen drie onderdelen om inhoud. Verder staat alles live.',
                'chips' => [
                    self::chip('Nieuwe pagina', Screen::Pages, create: true),
                    self::chip('Nieuwsbericht', Screen::News, create: true),
                    self::chip('Foto’s uploaden', Screen::Media),
                    self::chip('Menu aanpassen', Screen::Pages),
                ],
                'recent' => [
                    self::recent('Over ons', 'Pagina · gisteren 16:12', 'draft', Screen::Pages),
                    self::recent('Trainingstijden', 'Pagina · maandag', 'live', Screen::Pages),
                    self::recent('Clubkampioenschappen 2026', 'Nieuwsbericht · vorige week', 'live', Screen::News),
                ],
                'attention' => [
                    self::attention('high', 'Over ons staat al 6 dagen als concept', 'Bezoekers zien nog de oude tekst.', 'Publiceren', Screen::Pages),
                    self::attention('medium', 'Onze teams: 3 spelerslijsten zijn leeg', 'Merwestad 1, 2 en 3 tonen nog geen namen.', 'Aanvullen', Screen::Pages),
                    self::attention('medium', 'Locatie: foto ontbreekt bij ‘Parkeren’', 'Het blok toont nu een grijs vlak.', 'Foto kiezen', Screen::Media),
                    self::attention('low', 'Contact: tekst sinds 2024 niet aangepast', 'Klopt het e-mailadres nog?', 'Bekijken', Screen::Pages),
                ],
                'attention_footer' => 'Niets urgents meer. Het CMS kijkt elke nacht opnieuw.',
                'figures' => [
                    self::figure('1.240', 'bezoekers deze week', '12% meer dan vorige week'),
                    self::figure('Trainingstijden', 'meest bekeken pagina', '318 keer, vooral op maandag'),
                    self::figure('9', 'berichten via het contactformulier', '2 nog zonder antwoord'),
                ],
                'figures_link' => null,
                'quick' => [
                    self::quick('Alle pagina’s', Screen::Pages),
                    self::quick('Nieuwsberichten', Screen::News),
                    self::quick('Mediabibliotheek', Screen::Media),
                    self::quick('Formulierinzendingen', Screen::Messages),
                    self::quick('Menu’s', Screen::Pages),
                ],
                'updated' => $updated,
            ],
            Role::SeoSpecialist->value => [
                'summary' => 'Organisch verkeer groeit, maar zeven pagina’s missen een meta-omschrijving en twaalf afbeeldingen hebben geen alt-tekst. Daar valt het meeste te halen.',
                'chips' => [
                    self::chip('Meta’s bewerken', Screen::MetaTags),
                    self::chip('Redirect', Screen::Redirects, create: true),
                    self::chip('Sitemap bekijken', Screen::Sitemap),
                    self::chip('Zoekwoorden', Screen::Keywords),
                ],
                'recent' => [
                    self::recent('Meta-omschrijvingen: Onze teams', 'SEO · gisteren 15:40', 'live', Screen::MetaTags),
                    self::recent('Redirect /lid-worden-oud', 'Redirects · maandag', 'live', Screen::Redirects),
                    self::recent('Alt-teksten: Locatie', 'Media · vorige week', 'draft', Screen::AltTexts),
                ],
                'attention' => [
                    self::attention('high', '7 pagina’s zonder meta-omschrijving', 'Google schrijft er nu zelf een.', 'Schrijven', Screen::MetaTags),
                    self::attention('high', '12 afbeeldingen zonder alt-tekst', 'Vooral in Onze teams en Locatie.', 'Aanvullen', Screen::AltTexts),
                    self::attention('medium', 'Titel van Trainingstijden is 71 tekens', 'Google kapt af bij ongeveer 60.', 'Inkorten', Screen::MetaTags),
                    self::attention('medium', '2 pagina’s delen dezelfde titel', 'Contact en Lid worden.', 'Bekijken', Screen::MetaTags),
                    self::attention('low', 'Sitemap 3 dagen niet opgehaald door Google', 'Meestal vanzelf opgelost, soms niet.', 'Opnieuw indienen', Screen::Sitemap),
                ],
                'attention_footer' => 'Gecontroleerd vannacht om 03:10 tegen de laatste crawl.',
                'figures' => [
                    self::figure('812', 'bezoekers uit Google deze week', '18% meer dan vorige maand'),
                    self::figure('3', 'positie voor ‘tafeltennis dordrecht’', 'was 5 vorige week'),
                    self::figure('Over ons', 'grootste stijger', '40% meer organisch verkeer'),
                ],
                'figures_link' => self::quick('Naar zoekwoorden en posities', Screen::Keywords),
                'quick' => [
                    self::quick('Meta-overzicht', Screen::MetaTags),
                    self::quick('Redirects', Screen::Redirects),
                    self::quick('Sitemap', Screen::Sitemap),
                    self::quick('Zoekwoorden', Screen::Keywords),
                ],
                'updated' => $updated,
            ],
            Role::Webmaster->value => [
                'summary' => 'De site draait, maar drie links zijn gebroken en vannacht mislukte één formulierverzending. Het certificaat is tot maart geldig.',
                'chips' => [
                    self::chip('Gebruiker uitnodigen', Screen::Editors, create: true),
                    self::chip('Back-up maken', Screen::Backups),
                    self::chip('Logboek', Screen::Log),
                ],
                'recent' => [
                    self::recent('Gebruikers en rollen', 'Instellingen · gisteren 11:05', 'live', Screen::Editors),
                    self::recent('Formulieren', 'Instellingen · maandag', 'live', Screen::Forms),
                    self::recent('Domein en certificaat', 'Instellingen · vorige week', 'live', Screen::Domain),
                ],
                'attention' => [
                    self::attention('high', '3 gebroken links, waarvan 2 in het menu', 'Bezoekers komen op een 404.', 'Herstellen', Screen::Pages),
                    self::attention('high', 'Contactformulier: 1 verzending mislukt', 'SMTP weigerde vannacht om 02:14; het bericht staat in de wachtrij.', 'Opnieuw sturen', Screen::Forms),
                    self::attention('medium', 'Beheerder Piet is 90 dagen niet ingelogd', 'Overweeg zijn toegang in te trekken.', 'Toegang nakijken', Screen::Editors),
                    self::attention('low', 'Made CMS 1.4 is beschikbaar', 'Wordt in overleg met MADE geïnstalleerd.', 'Lees wat er nieuw is', Screen::Log),
                ],
                'attention_footer' => 'Certificaat geldig tot 14 maart 2027. Laatste back-up: vannacht 03:00.',
                'figures' => [
                    self::figure('100%', 'bereikbaar, afgelopen 30 dagen', 'geen storingen gemeten'),
                    self::figure('0,8 s', 'laadtijd van de homepage', 'goed; grens is 2,5 s'),
                    self::figure('0', 'fouten in het logboek sinds maandag', '3 waarschuwingen, geen actie nodig'),
                ],
                'figures_link' => self::quick('Naar het logboek', Screen::Log),
                'quick' => [
                    self::quick('Gebruikers en rollen', Screen::Editors),
                    self::quick('Formulieren', Screen::Forms),
                    self::quick('Back-ups', Screen::Backups),
                    self::quick('Domein en certificaat', Screen::Domain),
                ],
                'updated' => $updated,
            ],
            Role::Owner->value => [
                'summary' => 'Je site draait goed. Deze week kwamen 1.240 mensen langs en stuurden 9 mensen een bericht. Twee daarvan wachten nog op antwoord.',
                'chips' => [
                    self::chip('Nieuwsbericht', Screen::News, create: true),
                    self::chip('Openingstijden aanpassen', Screen::Pages),
                    self::chip('Vraag MADE om hulp', null),
                ],
                'recent' => [
                    self::recent('Clubkampioenschappen 2026', 'Nieuwsbericht · vorige week', 'live', Screen::News),
                    self::recent('Trainingstijden', 'Pagina · maandag', 'live', Screen::Pages),
                    self::recent('Contact', 'Pagina · september', 'live', Screen::Pages),
                ],
                'attention' => [
                    self::attention('high', '2 berichten wachten op antwoord', 'Via het contactformulier, de oudste van dinsdag.', 'Beantwoorden', Screen::Messages),
                    self::attention('medium', 'Over ons staat klaar om te publiceren', 'Je redacteur heeft de tekst af.', 'Bekijken', Screen::Pages),
                    self::attention('low', 'Je laatste nieuwsbericht is van 3 weken geleden', 'Leden kijken vaak naar nieuws.', 'Nieuws schrijven', Screen::News),
                ],
                'attention_footer' => 'Meer is er niet. Fijne dag.',
                'figures' => [
                    self::figure('1.240', 'bezoekers deze week', '12% meer dan vorige week'),
                    self::figure('9', 'berichten via contact', '2 nog zonder antwoord'),
                    self::figure('Trainingstijden', 'meest bekeken pagina', 'vooral op maandag'),
                ],
                'figures_link' => null,
                'quick' => [
                    self::quick('Berichten', Screen::Messages),
                    self::quick('Nieuws', Screen::News),
                    self::quick('Pagina’s', Screen::Pages),
                    self::quick('Hulp van MADE', null),
                ],
                'updated' => $updated,
            ],
            Role::Admin->value => [
                'summary' => 'Sinds maandag: drie gebroken links, zeven pagina’s zonder meta-omschrijving en Over ons dat al zes dagen als concept staat. Verkeer uit Google groeit.',
                'chips' => [
                    self::chip('Nieuwe pagina', Screen::Pages, create: true),
                    self::chip('Nieuwsbericht', Screen::News, create: true),
                    self::chip('Redirect', Screen::Redirects, create: true),
                    self::chip('Meta’s bewerken', Screen::MetaTags),
                    self::chip('Logboek', Screen::Log),
                ],
                'recent' => [
                    self::recent('Over ons', 'Pagina · gisteren 16:12', 'draft', Screen::Pages),
                    self::recent('Meta-omschrijvingen: Onze teams', 'SEO · gisteren 15:40', 'live', Screen::MetaTags),
                    self::recent('Gebruikers en rollen', 'Instellingen · maandag', 'live', Screen::Editors),
                ],
                'attention' => [
                    self::attention('high', '3 gebroken links, waarvan 2 in het menu', 'Bezoekers komen op een 404.', 'Herstellen', Screen::Links, 'Beheer'),
                    self::attention('high', '7 pagina’s zonder meta-omschrijving', 'Google schrijft er nu zelf een.', 'Schrijven', Screen::MetaTags, 'SEO'),
                    self::attention('high', 'Over ons staat al 6 dagen als concept', 'Bezoekers zien nog de oude tekst.', 'Publiceren', Screen::Pages, 'Content'),
                    self::attention('medium', '12 afbeeldingen zonder alt-tekst', 'Vooral in Onze teams en Locatie.', 'Aanvullen', Screen::AltTexts, 'SEO'),
                    self::attention('medium', 'Onze teams: 3 spelerslijsten zijn leeg', 'Merwestad 1, 2 en 3 tonen nog geen namen.', 'Aanvullen', Screen::Pages, 'Content'),
                ],
                'attention_footer' => 'Nog 4 punten met lage prioriteit. Je ziet hier de 5 belangrijkste uit alle rollen.',
                'figures' => [
                    self::figure('1.240', 'bezoekers deze week', '12% meer dan vorige week'),
                    self::figure('812', 'daarvan uit Google', '18% meer dan vorige maand'),
                    self::figure('100%', 'bereikbaar, 30 dagen', 'laadtijd 0,8 s'),
                ],
                'figures_link' => null,
                'quick' => [
                    self::quick('Alle pagina’s', Screen::Pages),
                    self::quick('Meta-overzicht', Screen::MetaTags),
                    self::quick('Redirects', Screen::Redirects),
                    self::quick('Gebruikers en rollen', Screen::Editors),
                    self::quick('Logboek', Screen::Log),
                ],
                'updated' => $updated,
            ],
        ];
    }

    /**
     * @return Chip
     */
    private static function chip(string $label, ?Screen $target, bool $create = false): array
    {
        return ['label' => $label, 'create' => $create, 'target' => $target];
    }

    /**
     * @param  'draft'|'live'  $status
     * @return Recent
     */
    private static function recent(string $title, string $meta, string $status, ?Screen $target): array
    {
        return ['title' => $title, 'meta' => $meta, 'status' => $status, 'target' => $target];
    }

    /**
     * @param  'high'|'medium'|'low'  $severity
     * @return Attention
     */
    private static function attention(string $severity, string $title, string $meta, string $action, ?Screen $target, ?string $domain = null): array
    {
        return ['severity' => $severity, 'title' => $title, 'meta' => $meta, 'action' => $action, 'domain' => $domain, 'target' => $target];
    }

    /**
     * @return Figure
     */
    private static function figure(string $figure, string $text, string $sub): array
    {
        return ['figure' => $figure, 'text' => $text, 'sub' => $sub];
    }

    /**
     * @return Quick
     */
    private static function quick(string $label, ?Screen $target): array
    {
        return ['label' => $label, 'target' => $target];
    }
}
