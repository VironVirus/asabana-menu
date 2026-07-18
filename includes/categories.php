<?php
declare(strict_types=1);

function menu_categories(): array
{
    return [
        'food' => [
            'swallow' => 'Swallow',
            'quick-bites' => 'Quick Bites',
            'rice' => 'Rice & Pasta',
            'protein' => 'Protein',
            'grills-and-outdoors' => 'Grills & Outdoors',
        ],
        'drinks' => [
            'alcohol' => 'Alcohol',
            'malt-energy' => 'Malt & Energy',
            'water' => 'Water',
            'juices-yoghurt-tea' => 'Juices, Yoghurt & Iced Tea',
            'sodas' => 'Sodas',
        ],
    ];
}

function category_type(string $category): ?string
{
    foreach (menu_categories() as $type => $categories) {
        if (array_key_exists($category, $categories)) {
            return $type;
        }
    }

    return null;
}

function category_label(string $category): string
{
    foreach (menu_categories() as $categories) {
        if (isset($categories[$category])) {
            return $categories[$category];
        }
    }

    return $category;
}

