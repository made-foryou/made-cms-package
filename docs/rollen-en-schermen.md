# Rollen en schermen

Dit bestand laat zien welke rol welk scherm van het CMS opent, waarom, en wat al besloten is maar nog niet gebouwd. Het geldt voor wat de rollen in het CMS mogen; het gaat niet over de gebruikers van de site.

- **De code is leidend.** Welke rol welk scherm opent, staat alleen in `Role::screens()` (`src/Access/Enums/Role.php`). Dit bestand legt dat uit en houdt de besluiten bij.
- **Een test houdt het gelijk.** `tests/Unit/Docs/RolesDocumentTest.php` faalt als de tabel hieronder niet past bij `Role::screens()`.
- **Design:** "Made CMS — Inloggen", rij 7 en 8 (startpagina per rol): https://claude.ai/artifact/3Qe9mFpPe9TvWNurRaU62Q

## Rollen

| Rol (code) | Naam in het CMS | Wat de rol doet | Startpagina in het design |
|---|---|---|---|
| `Admin` | Admin | Toegang tot alles in het CMS, ook tot onderdelen die later worden toegevoegd. | 7.5 Meerdere rollen |
| `Webmaster` | Beheerder | Houdt de site draaiende: gebruikers en rollen, formulieren, back-ups, logboek en domein. | 7.3 Beheerder / webmaster |
| `ContentManager` | Contentbeheerder | Schrijft en publiceert pagina's en nieuws, en beheert de media en berichten. | 7.1 Contentbeheerder |
| `SeoSpecialist` | SEO-specialist | Zorgt dat de site gevonden wordt: meta's, alt-teksten, links, redirects, sitemap en zoekwoorden. | 7.2 SEO-specialist |
| `Owner` | Eigenaar | Houdt overzicht: pagina's, nieuws en de berichten van bezoekers. | 7.4 Eigenaar |

De namen en omschrijvingen komen uit `lang/nl/roles.php`. Een redacteur kan meer rollen tegelijk hebben (`cms_editors.roles`).

## Wie opent welk scherm

- ✓ betekent dat de rol het scherm opent (het staat in `Role::screens()`).
- ○ betekent: besloten, maar nog niet gebouwd. Het issue staat in de laatste kolom.
- Leeg betekent dat de rol het scherm niet opent.

<!-- matrix:start -->
| Scherm | Admin | Beheerder | Contentbeheerder | SEO-specialist | Eigenaar | Groep | Stand |
|---|:-:|:-:|:-:|:-:|:-:|---|---|
| Pagina's (`pages`) | ✓ | ✓ | ✓ | ✓ | ✓ | Je site | gebouwd (#19); verder in #60 |
| Nieuws (`news`) | ✓ | ✓ | ✓ |  | ✓ | Verzamelingen | nog geen epic |
| Media (`media`) | ✓ | ✓ | ✓ |  |  | Verzamelingen | gebouwd (#55, mappen #56, bestandsacties #57) |
| Berichten (`messages`) | ✓ | ✓ | ✓ |  | ✓ | Verzamelingen | nog geen epic |
| Meta-overzicht (`meta-tags`) | ✓ |  |  | ✓ |  | Vindbaarheid | #87 (#88); het kopniveau in de bewerkschil: gebouwd (#126) |
| Alt-teksten (`alt-texts`) | ✓ |  |  | ✓ |  | Vindbaarheid | #87 (#95) |
| Links (`links`) | ✓ | ○ |  | ✓ |  | Vindbaarheid | #75 (#82); Beheerder ○ in #82 |
| Redirects (`redirects`) | ✓ | ○ |  | ✓ |  | Vindbaarheid | #87 (#93); Beheerder ○ in #93 |
| Sitemap (`sitemap`) | ✓ |  |  | ✓ |  | Vindbaarheid | #87 (#90, robots-regels #97) |
| Zoekwoorden (`keywords`) | ✓ |  |  | ✓ |  | Vindbaarheid | nog geen epic ("Google en statistieken") |
| Gebruikers en rollen (`editors`) | ✓ | ✓ |  |  |  | Beheer | #101 (#104) |
| Formulieren (`forms`) | ✓ | ✓ |  |  |  | Beheer | nog geen epic |
| Back-ups (`backups`) | ✓ | ✓ |  |  |  | Beheer | nog geen epic |
| Logboek (`log`) | ✓ | ✓ |  |  |  | Beheer | nog geen epic |
| Domein en certificaat (`domain`) | ✓ | ✓ |  |  |  | Beheer | nog geen epic |
<!-- matrix:end -->

Pagina's en Media zijn gebouwd. Elk ander scherm heeft al een route (`made-cms/<scherm>`) met de juiste toegang, maar toont nog "komt eraan" (`UpcomingScreenController`).

## Altijd open

- **De startpagina** (`made-cms/start`): voor elke ingelogde redacteur (`EnsureEditor`). Een blok of knop naar een scherm dat de redacteur niet opent, valt weg.
- **De launcher en het inloggen:** voor iedereen; het zichtbaar zijn van de launcher is geen autorisatie.
- **Het accountmenu en je account** (`made-cms/account`, nog niet gebouwd): voor elke ingelogde redacteur (`EnsureEditor`), zonder `Screen`-case. Je eigen naam, thema, wachtwoord, tweestapsverificatie en e-mailadres; je rollen zie je er alleen. Gevoelige handelingen vragen eerst om bevestiging. Zie #105, #106.

## Toegang die bij een ander scherm hoort

Sommige handelingen hebben geen eigen scherm. Ze vallen onder het scherm waar ze gebeuren.

| Handeling | Valt onder | Waarom | Bron |
|---|---|---|---|
| Een bestand uploaden en de alt-tekst van een bestand opslaan (`POST made-cms/media`, `PUT made-cms/media/{media}/alt`) | `Screen::Pages` of `Screen::Media` | Het uploadveld staat in de bewerkschil en in de bibliotheek. Uploaden hoort bij een pagina bewerken, dus ook Eigenaar en SEO-specialist kunnen een foto uploaden en beschrijven; de alt-tekst hoort bij het bestand en geldt overal. Eén route per handeling: `EnsureScreenAccess::using(Screen::Pages, Screen::Media)` laat door wie een van beide opent. | #49 (besluit 8), #51 |
| Media kiezen vanuit de bewerkschil: de kiezer met de bestanden en mappen van de bibliotheek, en de laatst gekozen map onthouden (`GET made-cms/media/files`, `GET made-cms/media/folders`, `PUT made-cms/media/chosen-folder`) | `Screen::Pages` of `Screen::Media` | Hoort bij een pagina bewerken, dus ook Eigenaar en SEO-specialist kiezen een foto. De bibliotheek leest dezelfde lijst, dus één route per handeling met `EnsureScreenAccess::using(Screen::Pages, Screen::Media)`. Het scherm Media zelf en het beheer (mappen, vervangen, verplaatsen, verwijderen) vallen onder `Screen::Media`. | #49 (besluit 8), #53 |
| Een foto bijsnijden, met brandpunt en verkleinen, en de bijsnijd-stap met het origineel tonen (`GET made-cms/media/{media}/crop`, `GET made-cms/media/{media}/original`, `POST made-cms/media/{media}/crop`) | `Screen::Pages` of `Screen::Media` | "Bijsnijden" staat in het media-element van de bewerkschil en de stap opent na een upload in de kiezer, dus ook Eigenaar en SEO-specialist snijden een foto bij. Net als de alt-tekst hoort de uitsnede bij het bestand en verandert hij de foto meteen op elke pagina; de stap zegt op hoeveel pagina's. Het origineel komt alleen via deze route, rechtop, verkleind en zonder metadata. | #54 (besluit 3 en 4) |
| Een bestand een andere naam geven (`PUT made-cms/media/{media}/name`) | `Screen::Pages` of `Screen::Media` | Staat in de bijsnijd-stap naast de alt-tekst. De bestandsnaam leest Google mee, dus juist de SEO-specialist geeft hem. | #54 (besluit 3) |
| Een bestand naar een map verplaatsen (`PUT made-cms/media/{media}/move`) | `Screen::Media` | Beheer van de bibliotheek (besluit 8 van #49). De keuzelijst Map staat in de bijsnijd-stap alleen voor wie Media opent; na een upload in de kiezer staat het bestand al in de map die de redacteur opende. | #49 (besluit 8), #54 (besluit 3), #56 |
| Meerdere bestanden tegelijk naar een map verplaatsen, en mappen aanmaken, hernoemen en verwijderen (`PUT made-cms/media/move`, `POST made-cms/media/folders`, `PUT`/`DELETE made-cms/media/folders/{folder}`) | `Screen::Media` | Beheer van de bibliotheek (besluit 8 van #49). De kiezer toont de mappen alleen. | #56 |
| Een bestand vervangen, de vorige versie terugzetten, een bestand downloaden en bestanden verwijderen (`POST made-cms/media/{media}/replace`, `POST made-cms/media/{media}/restore`, `GET made-cms/media/{media}/download`, `POST made-cms/media/delete`) | `Screen::Media` | Beheer van de bibliotheek (besluit 8 van #49). Vervangen en terugzetten veranderen het bestand meteen op elke pagina, ook gepubliceerd. Downloaden geeft van een foto het privé origineel, alleen via de app. Verwijderen kan alleen voor een bestand dat nergens staat, ook niet in een concept of op een pagina in de prullenbak. | #49 (besluit 7 en 8), #57 |
| De vaste link van een bestand openen (`GET made-cms/file/{ulid}/{naam}`) | iedereen | De link die "Link kopiëren" geeft, staat in nieuwsbrieven en mails. Hij stuurt alleen door naar het adres dat het bestand nu heeft, dat al publiek is; de sleutel staat al in dat adres, dus er wordt niets opsombaar. Zonder sessie of cookie. | #57 |
| Een link kiezen in het zijpaneel | `Screen::Pages` | Hoort bij een pagina bewerken. | #75 |
| De standaard van een linkbestemming wijzigen, en Vervang | `Screen::Links` | Raakt alle plekken van een bestemming. | #75, #82 |
| Het niveau van een kop kiezen in het zijpaneel (h1–h6 of "Geen kop"), en de weergave "Koppen" naast de Opbouw | `Screen::MetaTags` | De SEO-specialist bepaalt welke structuur Google leest, met de pagina in beeld. De weergave Koppen toont daarvoor alle koppen van de pagina met de signalen; ze is alleen UI. De anderen met Pagina's zien het niveau als label en bewerken de tekst. De server bewaakt het: `UpdateStrip` en `RestoreStrip` houden voor hen het opgeslagen niveau vast (`Visitor::choosesHeadingLevels`). | #124 (besluit 2 en 3), #126, #127 |
| Meta en "Staat in de sitemap" van één pagina | `Screen::Pages` | De paginadetail; de Vindbaarheid-schermen tonen dezelfde gegevens voor alle pagina's tegelijk. | #65, #87 (besluit 7) |
| Een redirect bij het weghalen van een pagina | `Screen::Pages` | Hoort bij verwijderen. | #94 |
| Automatisch doorsturen bij verhuizen | geen | Een Content-Action doet dit; Content kijkt nooit naar rollen. | #94 |
| Robots-regels | `Screen::Sitemap` | Staan in het scherm Sitemap. | #97 |
| De scan | Admin, alleen bij `APP_ENV=local` (`EnsureScanAccess`, `CheckScanAccess`) | Schrijft code en inhoud in de site. | #28, #31 |
| Rollen toekennen | `Screen::Editors` | Alleen een admin geeft of neemt `Admin`; de laatste admin houdt die rol; iedereen houdt minstens één rol. | `AssignRoles` |
| Uitnodigen, toegang intrekken en terugzetten, en het adres van een openstaande uitnodiging aanpassen | `Screen::Editors` | Dezelfde regels als bij rollen: alleen een admin nodigt uit met `Admin`, past een admin-uitnodiging aan of trekt een admin in; de laatste admin blijft; je trekt jezelf niet in. | #101 (besluiten 1, 2 en 8), #102, #103 |
| Een link naar een ander scherm ("Bekijk in Links", "Vul in bij Media") | het andere scherm | De link verschijnt alleen als de redacteur dat scherm opent. | CLAUDE.md |

## Regels

- **Admin opent elk scherm**, ook schermen die later worden toegevoegd.
- **Rollen koppelen aan schermen gebeurt alleen in `Role::screens()`.** Een nieuw scherm is een `Screen`-case, wordt daar toegekend en krijgt zijn groep in `Screen::group()`.
- **Toegang geldt per scherm.** Binnen een scherm zijn er geen aparte rechten. Wie het scherm opent, mag alles wat het scherm doet (#82).
- **De middleware van de route (`EnsureScreenAccess`) is de autorisatie.** Een menu-item verbergen is alleen UI. Een handeling die onder een ander scherm valt dan haar route (het kopniveau), bewaakt de server in die route zelf.
- **Content kijkt nooit naar rollen.** De toegang wordt in Http bewaakt.

## Waar het design afwijkt

- **7.3 Beheerder / webmaster:**
  - Het menu toont geen Vindbaarheid. De chips, "Snel naar" en de aandachtspunten noemen wel Redirects en gebroken links ("Herstellen").
  - Besloten: de Beheerder krijgt Links en Redirects. Het menu volgt `Role::screens()`, dus de groep Vindbaarheid verschijnt met die twee schermen.
- **7.1 Contentbeheerder** ("Menu aanpassen", "Menu's") en **7.4 Eigenaar** ("Openingstijden aanpassen", "Vraag MADE om hulp"): daar is nog geen scherm voor. Dit hoort bij menu's en globale inhoud, en daar is nog geen epic voor.

## Besluiten

| Datum | Besluit | Bron |
|---|---|---|
| 2026-10-27 | Vijf vaste rollen, met hun schermen in `Role::screens()`; Admin opent alles. | 9a881df |
| 2026-11-03 | Media kiezen en uploaden vanuit de bewerkschil valt onder Pagina's; de bibliotheek valt onder Media (voorstel). | #49 |
| 2026-11-06 | De Beheerder krijgt Redirects. | #87 (besluit 7), #93 |
| 2026-11-06 | De Beheerder krijgt Links. | #82, #75 |
| 2026-11-06 | Robots-regels vallen onder Sitemap. Meta en sitemap per pagina, en de redirect bij weghalen, vallen onder Pagina's. | #87, #94, #97 |
| 2026-11-06 | Uitnodigen, intrekken en terugzetten vallen onder Gebruikers en rollen, met de admin-regels van rollen toekennen. Een account wordt nooit verwijderd, alleen ingetrokken. | #101 (besluiten 1, 2 en 8) |
| 2026-11-06 | Het accountmenu en je account zijn altijd open voor elke ingelogde redacteur, zonder `Screen`-case. | #101 (besluit 4), #105 |
| 2026-11-07 | Het niveau van een kop kiezen in de bewerkschil valt onder Meta-overzicht; de anderen zien het niveau als label. | #124 (besluit 2), #126 |
| 2026-11-07 | De weergave Koppen naast de Opbouw valt ook onder Meta-overzicht; de anderen zien alleen de Opbouw. | #124 (besluit 3), #127 |
| 2026-11-10 | Uploaden en de alt-tekst van een bestand opslaan staan open voor wie Pagina's of Media opent: één route per handeling, voor de bewerkschil en de bibliotheek. | #51 |
| 2026-11-13 | Bijsnijden, het brandpunt en de bestandsnaam staan open voor wie Pagina's of Media opent; een bestand naar een map verplaatsen is beheer en valt onder Media, ook in de bijsnijd-stap. | #54 (besluit 3) |

## Bijwerken

**Bij een nieuw besluit:**

- zet een ○ in de tabel, met het issue in de kolom Stand;
- voeg een regel toe aan Besluiten.

**Bij het bouwen**, in één wijziging:

- pas `Role::screens()` en `tests/Unit/Access/Enums/RoleTest.php` aan;
- maak van de ○ een ✓;
- werk de omschrijving van de rol bij in `lang/nl/roles.php` en `lang/en/roles.php`;
- voeg een regel toe aan `CHANGELOG.md`.
