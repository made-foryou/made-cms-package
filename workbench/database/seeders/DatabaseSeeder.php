<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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

        Page::factory()->create([
            'title' => 'Home',
            'slug' => 'home',
        ]);
    }
}
