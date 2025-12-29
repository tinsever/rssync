<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class DefaultSeeder extends AbstractSeed
{
    public function run(): void
    {
        // Create default categories (countries)
        $categories = [
            ['name' => 'Allgemein', 'user_id' => null],
        ];
        
        $categoryTable = $this->table('categories');
        
        foreach ($categories as $category) {
            // Check if category exists
            $exists = $this->fetchRow("SELECT id FROM categories WHERE name = '{$category['name']}' AND user_id IS NULL");
            if (!$exists) {
                $categoryTable->insert($category)->saveData();
            }
        }
        
        // Get category IDs
        $categoryIds = [];
        foreach ($categories as $category) {
            $row = $this->fetchRow("SELECT id FROM categories WHERE name = '{$category['name']}' AND user_id IS NULL");
            if ($row) {
                $categoryIds[$category['name']] = $row['id'];
            }
        }
        
        // Create default sources (newspapers)
        $sources = [];
        
        $sourceTable = $this->table('sources');
        
        foreach ($sources as $source) {
            if (!isset($categoryIds[$source['category']])) {
                continue;
            }
            
            // Check if source URL already exists
            $escapedUrl = addslashes($source['url']);
            $exists = $this->fetchRow("SELECT id FROM sources WHERE url = '{$escapedUrl}'");
            if (!$exists) {
                $sourceTable->insert([
                    'category_id' => $categoryIds[$source['category']],
                    'name' => $source['name'],
                    'url' => $source['url'],
                ])->saveData();
            }
        }
        
        echo "Default categories and sources seeded successfully!\n";
    }
}

