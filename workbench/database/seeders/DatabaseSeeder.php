<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Made\CmsPackage\Access\Actions\CreateEditor;
use Made\CmsPackage\Access\Data\NewEditor;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Strip;
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

        $home = Page::factory()->create([
            'title' => 'Home',
            'path' => '/',
        ]);

        // Three strips to try the edit mode on at /voorbeeld. Without strip type files their keys are their labels.
        Strip::factory()->for($home)->createMany([
            ['type' => 'hero', 'position' => 0, 'content' => ['title' => 'TTV Merwestad', 'intro' => 'Sinds 1982 dé plek voor tafeltennissers van alle niveaus.']],
            ['type' => 'cijfers', 'position' => 1, 'content' => ['title' => '60+ jaar actief', 'intro' => '35+ leden, 3 competitieteams en twee keer per week training.']],
            ['type' => 'over-ons', 'position' => 2, 'content' => ['title' => 'Meer dan alleen tafeltennis', 'intro' => 'Bij TTV Merwestad draait het om sport, plezier en verbinding.']],
        ]);
    }
}
