<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Made\CmsPackage\Access\Actions\CreateEditor;
use Made\CmsPackage\Access\Data\NewEditor;
use Made\CmsPackage\Access\Enums\Role;
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

        // The home page shows every kind of value: rich text, buttons to a page and an address, cards and a hidden strip.
        Page::factory()->published()->atPosition(0)->at('/')->withStrips([
            $this->strip('hero', [
                'label' => 'Made CMS',
                'kop' => 'Welkom bij de workbench',
                'intro' => '<p>Een pagina uit <strong>stroken</strong>, gerenderd met de Blade-component.</p>',
                'knoppen' => [
                    ['knop' => ['text' => 'Over ons', 'link' => ['page' => $about->id]]],
                    ['knop' => ['text' => 'Mail ons', 'link' => ['url' => 'mailto:info@example.com']]],
                ],
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
}
