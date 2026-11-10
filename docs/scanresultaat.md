# Het resultaat van de scan

De scan leest een bestaande site en stelt voor welke striptypes die heeft, met welke velden, en wat er per pagina in staat. Dit bestand beschrijft het `ScanResult`: de vorm waarin de scan dat voorstelt. De volgende stappen van de scan bouwen erop (epic #12, stap 4):

| Stap | Wat hij met het resultaat doet |
|---|---|
| 4.3 Beoordelen (#30) | Toont de types en velden, twijfel eerst. Wijst ze aan in het iframe en laat de developer hernoemen, samenvoegen en uitsluiten. |
| 4.4 Definities (#31) | Schrijft per type een klasse in `app/Cms/Strips`. |
| 4.5 Views (#32) | Knipt per type een view uit de markup van het eerste voorbeeld. |
| 4.6 Inhoud (#33) | Maakt de pagina's met hun stroken aan, met de gelezen waarden. |
| 4.7 AI (#34) | Stuurt het compacte resultaat naar Claude voor betere namen en voorstellen. |

- **De code is leidend.** De vorm staat in `src/Content/Data/Scan*.php`. Een test (`tests/Unit/Docs/ScanResultDocumentTest.php`) leest het voorbeeld en de twijfels hieronder en faalt als ze niet meer kloppen.
- **Alleen lezen.** De herkenning schrijft niets en gebruikt geen dienst buiten de site: dezelfde HTML geeft hetzelfde resultaat. Alleen 4.4 schrijft in de site, en alleen bij `APP_ENV=local` (zie [Van type naar klasse](#van-type-naar-klasse-44)).

## Van pagina naar resultaat

1. De browser rendert elke pagina van de site in een iframe van dezelfde origin en geeft `document.documentElement.outerHTML` door (4.1). Zo leest de scan ook sites die pas in de browser renderen.
2. `RecognisePage::run(new ScannedPage($url, $html))` leest één pagina: de titel en de stroken met hun velden en waarden. Dat is een `RecognisedPage`, die met `toArray()` en `fromArray()` kan wachten tot alle pagina's binnen zijn.
3. `CombinePageScans::run($pages)` maakt van alle pagina's samen één `ScanResult`. Wat alle voorbeelden nodig heeft, valt hier: welke stroken één type zijn, welke tekst bij het ontwerp hoort, wat een keuze is, wat verplicht is, en de namen.

## Voorbeeld

Een deel van de scan van de home van TTV Merwestad (`tests/Fixtures/Scan/ttv-merwestad/home.html`), met twee van de drie items:

<!-- example:start -->
```json
{
  "version": 1,
  "types": [
    {
      "key": "over-ons",
      "label": "Over ons",
      "hint": "#over-ons",
      "fingerprint": "61f789569827f3f46ecb059a21119298",
      "count": 1,
      "certain": true,
      "doubts": [],
      "fields": [
        {"key": "label", "type": "text", "selector": "div > div > div > span", "required": true, "certain": true, "doubts": []},
        {"key": "kop", "type": "text", "selector": "div > div > div > h2", "required": true, "certain": true, "doubts": []},
        {"key": "tekst", "type": "long_text", "selector": "div > div > div > p", "required": true, "certain": true, "doubts": []},
        {
          "key": "items",
          "type": "items",
          "selector": "div > div:nth-of-type(2)",
          "required": true,
          "certain": true,
          "doubts": [],
          "item": "div",
          "min": 1,
          "max": 3,
          "fields": [
            {"key": "icoon", "type": "choice", "selector": "div > div > svg", "required": true, "certain": false, "doubts": ["icon_choice"], "options": ["users", "heart", "trophy"]},
            {"key": "kop", "type": "text", "selector": "div > h3", "required": true, "certain": true, "doubts": []},
            {"key": "tekst", "type": "long_text", "selector": "div > p", "required": true, "certain": true, "doubts": []}
          ]
        }
      ]
    }
  ],
  "pages": [
    {
      "url": "/",
      "title": "Welkom - TTV Merwestad",
      "strips": [
        {
          "type": "over-ons",
          "selector": "#over-ons",
          "values": {
            "label": "Over ons",
            "kop": "Meer dan alleen tafeltennis",
            "tekst": "Bij TTV Merwestad draait het om sport, plezier en verbinding.",
            "items": [
              {"icoon": "users", "kop": "Alle niveaus", "tekst": "Of je nu voor het eerst een batje vasthoudt …"},
              {"icoon": "heart", "kop": "Gezelligheid", "tekst": "Meer dan alleen sport. …"}
            ]
          }
        }
      ]
    }
  ]
}
```
<!-- example:end -->

## De onderdelen

**`ScanResult`**

| Sleutel | Inhoud |
|---|---|
| `version` | De versie van deze vorm, nu `1`. |
| `types` | De voorgestelde striptypes, in de volgorde waarin de scan ze tegenkwam. |
| `pages` | De gescande pagina's met hun stroken. |

**Type (`ScanType`)**

| Sleutel | Inhoud |
|---|---|
| `key` | De key van het striptype: de id van de strook (`over-ons`), anders een klasse die als naam leest (`hero`), anders algemeen naar de plek (`strook-3`). Een key die al bestaat, krijgt `-2` erachter. |
| `label` | De naam voor de redacteur, afgeleid van de key ("Over ons", "Strook 3"). |
| `hint` | Waar de naam vandaan komt: `#id` of `.klasse`, of `null`. |
| `fingerprint` | De opbouw van de strook zonder teksten, zonder het aantal items en zonder het niveau van de koppen. Dezelfde vingerafdruk is hetzelfde type, ook op andere pagina's. |
| `count` | Hoeveel stroken van dit type de scan vond. |
| `certain`, `doubts` | Twijfel over het type zelf (zie [Twijfels](#twijfels)). `certain` is `true` zonder twijfels. |
| `fields` | De velden, in de volgorde van de pagina. |

**Veld (`ScanField`)**

| Sleutel | Inhoud |
|---|---|
| `key` | De key van het veld, naar wat het is ("kop", "tekst", "label", "inhoud", "knop", "link", "afbeelding", "icoon", "lijst", "items"), genummerd als hij terugkomt (`kop_2`). De woorden komen uit `lang/{nl,en}/scan.php`, in de taal van de launcher. |
| `type` | Het veldtype, zoals `Field::type()` het noemt (zie hieronder). |
| `selector` | Waar het veld staat, vanaf de strook of vanaf het item (zie [Selectors](#selectors)). |
| `required` | Gevuld in elk voorbeeld (of elk item). |
| `certain`, `doubts` | Twijfel over dit veld. |
| `options` | Alleen bij `choice`: de opties. |
| `item`, `min`, `max`, `fields` | Alleen bij `items`: de tag van één item, het voorstel 1 en het grootste aantal items dat de scan zag, en de velden van elk item. |

**Pagina (`ScanPage`) en strook (`ScanStrip`)**

| Sleutel | Inhoud |
|---|---|
| `url`, `title` | Het adres en de titel: een eigen `h1` in de inhoud, anders `<title>`. |
| `strips[].type` | De key van het type. |
| `strips[].selector` | Waar de strook in het document staat. |
| `strips[].values` | De waarden per veld-key, in de opgeslagen vorm. Een optioneel veld zonder waarde ontbreekt. |

## Veldtypen en waarden

| `type` | Herkend aan | Waarde |
|---|---|---|
| `text` | `h1`–`h6`, of een ander element met alleen (inline) tekst | `"Over ons"` |
| `long_text` | een `p`; een herhaling binnen een item (één regel per element) | `"Kom\nspelen"` (een regel per `br`) |
| `rich_text` | een blok met twee of meer alinea's of lijsten, een lijst van gewone regels, een `p` met een link | opgeschoonde HTML (`p`, `strong`, `em`, `a`, `ul`, `ol`, `li`) |
| `button` | een `a[href]` met eigen tekst | `{"text": "Lid worden", "link": {"url": "#lid-worden"}}` |
| `link` | een `a[href]` om een kaart of afbeelding, zonder tekst, of met dezelfde tekst in elk item | `{"url": "https://…"}` |
| `choice` | een icoon (`svg`, of `i` zonder tekst) dat per item een andere klasse heeft | `"users"` |
| `items` | twee of meer broers op een rij met dezelfde tag en (bijna) dezelfde opbouw | een lijst met per item de waarden per key |
| `image` | `img`, `picture`, een `background-image: url()` in `style` | `{"url": "/foto.jpg", "alt": "…"}` |

- Het veldtype `image` bestaat nog niet (#27). Tot dan laat 4.4 het weg, met een regel commentaar in de klasse; de waarde staat wel in het resultaat.
- Adressen staan er zoals de site ze schrijft. 4.6 maakt van een intern adres een verwijzing naar een pagina.

## Selectors

- Een stap is de tag van het element, met `:nth-of-type(n)` vanaf het tweede element van die tag onder dezelfde ouder. De stappen staan achter elkaar met ` > `.
- **Strook:** vanaf het document. `#id` als geen ander element die id heeft, anders het pad vanaf `body` (`body > main > section:nth-of-type(2)`).
- **Veld:** vanaf de strook, of bij een veld in `items` vanaf het item. Leeg (`""`) is de wortel zelf, bijvoorbeeld een item dat zelf de knop is.
- **Terugvinden:** de eerste treffer is altijd het element zelf.
  - PHP: `$document->querySelector($strook.' > '.$veld)`.
  - Browser: `document.querySelector(strook + ' > ' + veld)`, of `wortel.querySelector(':scope > ' + veld)`.
- **Items:** de selector van een lijst wijst de ouder aan. De items zijn de kinderen met de tag uit `item`, zonder lege elementen.
- De selectors van een type komen van het eerste voorbeeld. Een optionele broer met een andere tag verschuift ze niet.
- **Koppen:** het niveau van een kop is inhoud, geen opbouw. Een kop lijnt uit op zijn plek onder de koppen van zijn ouder, niet op zijn selector. Staat een kop op een andere pagina op een ander niveau, dan vind je hem daar dus niet met de selector van het type (`h2`), maar met dezelfde stap op zijn eigen niveau (`h3`).

## Twijfels

Een veld of type met twijfel heeft `certain: false`. 4.3 toont die eerst. De uitleg voor de redacteur staat in `lang/{nl,en}/scan.php` (`ScanDoubt::label()`).

<!-- doubts:start -->
| Twijfel | Bij | Betekenis |
|---|---|---|
| `generic_name` | type | Zonder id of herkenbare klasse: de naam is algemeen. |
| `contains_form` | type | De strook bevat een formulier; de velden daarvan zijn niet overgenomen. |
| `decorative_image` | veld | Een afbeelding zonder alt-tekst of verborgen voor schermlezers: misschien versiering. |
| `background_image` | veld | Een achtergrondafbeelding uit de stijl van het element. |
| `icon_choice` | veld | Een keuze, geraden uit de iconen van de items. |
| `items_differ` | veld | De items van de lijst zijn niet allemaal precies gelijk opgebouwd. |
| `nested_list` | veld | Een lijst in een item: één waarde per regel, want een lijst in een lijst kan niet. |
| `same_in_every_item` | veld | Staat in elk item hetzelfde: misschien vaste tekst van het ontwerp. |
<!-- doubts:end -->

## Hoe de scan herkent

- **Stroken:** de blokken van `main`. Zonder `main` daalt de scan vanaf `body` af zolang er maar één blok inhoud heeft. Een blok dat alleen secties bevat, levert die secties. `header`, `nav`, `footer`, `aside`, verborgen elementen en de elementen van het CMS doen niet mee. Een `h1` direct in de inhoud is de titel van de pagina. Inhoud met alleen lopende tekst is één strook.
- **Overslaan:** scripts, formulieren, knoppen van de interface (`button`), teksten met `aria-hidden` en iconen buiten een lijst. Een afbeelding met `aria-hidden` telt wel, met twijfel: een foto achter een hero is vaak zo verborgen.
- **Lijsten:** broers op een rij met dezelfde tag waarvan de paden naar hun teksten, afbeeldingen en iconen voor minstens 60% overlappen (`ItemGroups::SIMILAR`). Lege broers ertussen tellen niet mee. Alinea's en koppen vormen nooit een lijst.
- **Over alle items en pagina's heen:**
  - een icoon dat per item verschilt is een keuze, een icoon dat overal gelijk is versiering;
  - een link met dezelfde tekst in elk item is een `link` (de tekst hoort bij het ontwerp), met hetzelfde adres valt hij weg, net als een afbeelding die overal gelijk is;
  - een tekst die in elk item gelijk is, valt weg als het een kort label is (hooguit twee woorden, geen cijfers, zoals "Spelers"), anders krijgt hij `same_in_every_item`;
  - een veld dat op een pagina een link in de tekst heeft, wordt overal `rich_text`.

## Van type naar klasse (4.4)

`GenerateStripTypes::run($scan)` schrijft per type van een (beoordeeld) resultaat één klasse in de map van de catalogus (`strips.path`, onder `strips.namespace`). Daarna is de klasse van de developer. Het endpoint `POST made-cms/scan/strip-types` doet dit voor een admin, alleen bij `APP_ENV=local`.

| In het resultaat | In de klasse |
|---|---|
| `key` | `key()` en de klassenaam (`over-ons` wordt `OverOns`, met `Strip` erachter als PHP of een import die naam al gebruikt) |
| `label` | `label()`, alleen als de klassenaam het niet al zo leest (`Over ons`, `Strook 1`) |
| `type` | `Text`, `LongText`, `RichText`, `Button`, `Link`, `Choice` of `Items`; `image` valt weg met een regel commentaar |
| `required` | `->required()` |
| `options` | een keuze met per waarde een label om te vertalen (`'map-pin' => 'Map pin'`) |
| `min`, `max` | `->max()`; `->min()` alleen boven 1, want `required` vraagt al om één item |
| `selector`, `doubts`, `hint`, `fingerprint` | niets: die zijn voor de review en de views |

- **Nooit overschrijven.** Een type wordt overgeslagen en gemeld als de catalogus de definitie zou weigeren (`invalid`), als de catalogus of het resultaat de key al heeft (`key_exists`), of als de map de klasse al heeft, ook met andere hoofdletters (`class_exists`). Een bestand wordt alleen aangemaakt als het er nog niet is.
- **Acceptatie.** Voor de home van TTV Merwestad schrijft de scan dezelfde acht types in dezelfde volgorde als de koppeling met de hand. Elk verschil komt door een van de grenzen hieronder (`tests/Feature/Content/Scan/TtvMerwestadDefinitionsTest.php`).

## Grenzen

- De scan ziet alleen de markup, geen berekende stijlen: een achtergrond uit een stylesheet of een element dat via CSS verborgen is, ziet hij niet.
- Een keuze ontstaat alleen uit iconen, niet uit klassen op knoppen of balken (de stijl van een knop) en niet uit tekst (een weekdag).
- Een kop in twee kleuren (`TTV <span>Merwestad</span>`) of een getal met een teken erachter (`60+`) is één tekst.
- Elementen op een rij met een eigen tag of een eigen icoon zijn losse velden, ook als ze er als een lijst uitzien.
- Een waarde die alleen in een attribuut staat, leest de scan niet, zoals de `id` die het anker van een strook is.
- Een `p` is altijd lange tekst, ook als er maar één regel in past.
- Wat binnen een item herhaalt (de klasse en de poule van een team), is één lange tekst met een regel per element.
- De scan leest alleen wat de pagina toont: een leeg veld of een tekst die pas na het versturen van een formulier verschijnt, ontbreekt. Andersom lijkt tekst die de site zelf berekent ("Vanavond!") inhoud.
- Een `mailto:`-link met tekst is een knop, ook als de site er een e-mailadres mee toont.
