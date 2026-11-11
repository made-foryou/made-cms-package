<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Made\CmsPackage\Access\Actions\CreateEditor;
use Made\CmsPackage\Access\Data\NewEditor;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Content\Actions\RebuildMediaUsage;
use Made\CmsPackage\Content\Actions\UpdateAltText;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;
use Workbench\Database\Factories\UserFactory;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // UserFactory::new()->times(10)->create();

        UserFactory::new()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Logs in with a code (see storage/logs) or with the password "password".
        CreateEditor::run(new NewEditor(email: 'editor@example.com', name: 'Menno Tempelaar', password: 'password', roles: [Role::Admin]));

        // Served by Route::madeCms() in workbench/routes/web.php: "/", "/over-ons" and "/over-ons/team" open. "/concept" is
        // a draft and answers 404, except to a logged-in editor in the edit mode of the CMS: "/concept?made-cms=edit".
        // The positions give the order of the menu and of the pages screen.
        $about = Page::factory()->published()->atPosition(1)->at('/over-ons')->withStrips([
            $this->strip('hero', ['kop' => 'Over ons']),
        ])->create(['title' => 'Over ons', 'slug' => 'over-ons']);

        Page::factory()->published()->under($about)->at('/over-ons/team')->withStrips([
            $this->strip('hero', ['kop' => 'Ons team']),
        ])->create(['title' => 'Team', 'slug' => 'team']);

        // The home page shows every kind of value: rich text, buttons to a page and an address, a photo from the media
        // library (served from the public disk after `php artisan storage:link`), cards and a hidden strip.
        $photo = $this->samplePhoto();
        $this->teamsFolder();

        Page::factory()->published()->atPosition(0)->at('/')->withStrips([
            $this->strip('hero', [
                'label' => 'Made CMS',
                'kop' => 'Welkom bij de workbench',
                'intro' => '<p>Een pagina uit <strong>stroken</strong>, gerenderd met de Blade-component.</p>',
                'knoppen' => [
                    ['knop' => ['text' => 'Over ons', 'link' => ['page' => $about->id]]],
                    ['knop' => ['text' => 'Mail ons', 'link' => ['url' => 'mailto:info@example.com']]],
                ],
                'foto' => $photo->id,
            ]),
            $this->strip('cards', [
                'kop' => 'Wat je kunt doen',
                'kolommen' => '2',
                'kaarten' => [
                    ['titel' => 'Lees over ons', 'tekst' => "Wie we zijn\nen wat we doen.", 'link' => ['page' => $about->id]],
                    ['titel' => 'Bekijk de broncode', 'tekst' => null, 'link' => ['url' => 'https://github.com/made-foryou/made-cms-package']],
                    ['titel' => 'Zonder link', 'tekst' => 'Een kaart hoeft nergens heen.', 'link' => null],
                ],
            ]),
            $this->strip('hero', ['kop' => 'Verborgen strook: bezoekers zien deze niet'], visible: false),
        ])->create(['title' => 'Home', 'slug' => 'home']);

        Page::factory()->atPosition(2)->at('/concept')->withDraft([
            $this->strip('hero', ['kop' => 'Een pagina die nog niet gepubliceerd is']),
        ])->create(['title' => 'Concept', 'slug' => 'concept']);

        // The factories write the strips without the Actions, so the library learns from them where its photo is.
        RebuildMediaUsage::run();
    }

    /**
     * A strip in the form it is stored in cms_pages.
     *
     * @param  array<string, mixed>  $values
     * @return array{id: string, type: string, visible: bool, values: array<string, mixed>}
     */
    private function strip(string $type, array $values, bool $visible = true): array
    {
        return ['id' => (string) Str::uuid(), 'type' => $type, 'visible' => $visible, 'values' => $values];
    }

    /**
     * A photo in the media library, drawn here so the workbench needs no image of its own: bands of the colours of
     * the CMS, uploaded as an editor would, so it is scaled down to WebP with its original next to it.
     */
    private function samplePhoto(): Media
    {
        // The build wipes the database, so the files of the library from an earlier build belong to no row any more.
        Storage::disk(config()->string('made-cms-package.media.disk'))->deleteDirectory('media');

        return $this->drawnPhoto('Voorbeeldfoto workbench.jpg', [[14, 43, 38], [28, 61, 90], [190, 138, 28], [243, 241, 232]], null, 'Vier vlakken in de kleuren van Made CMS');
    }

    /**
     * A folder Teams for the picker: two photos, one without an alt text (its amber dot), and a document the picker of
     * an image never shows.
     */
    private function teamsFolder(): void
    {
        $teams = MediaFolder::factory()->create(['name' => 'Teams']);

        $this->drawnPhoto('Teamfoto Merwestad 1.jpg', [[28, 61, 90], [243, 241, 232]], $teams, 'Het eerste team voor de tafels');
        $this->drawnPhoto('Training dinsdag.jpg', [[190, 138, 28], [14, 43, 38], [190, 138, 28]], $teams, null);

        $path = sys_get_temp_dir().'/made-cms-document-'.Str::random(16).'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

        try {
            UploadMedia::run(new UploadedMedia($path, 'Wedstrijdschema najaar.pdf', $teams->id, null));
        } finally {
            unlink($path);
        }
    }

    /**
     * A photo uploaded as an editor would, so it is scaled down to WebP with its original next to it: upright bands of
     * colour, drawn here so the workbench needs no image of its own.
     *
     * @param  list<array{int, int, int}>  $bands
     */
    private function drawnPhoto(string $name, array $bands, ?MediaFolder $folder, ?string $alt): Media
    {
        $image = imagecreatetruecolor(2400, 1350);
        $width = intdiv(2400, count($bands));

        foreach ($bands as $band => [$red, $green, $blue]) {
            imagefilledrectangle($image, $band * $width, 0, ($band + 1) * $width - 1, 1349, (int) imagecolorallocate($image, $red, $green, $blue));
        }

        $path = sys_get_temp_dir().'/made-cms-photo-'.Str::random(16).'.jpg';
        imagejpeg($image, $path, 85);

        try {
            $media = UploadMedia::run(new UploadedMedia($path, $name, $folder?->id, null));
        } finally {
            unlink($path);
        }

        return $alt === null ? $media : UpdateAltText::run($media, $alt);
    }
}
