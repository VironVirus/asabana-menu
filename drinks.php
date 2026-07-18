<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/layout.php';

$items = menu_store()->items('drinks', true);

render_public_header(
    'drinks',
    'Drinks Menu | Asabana Hotel',
    'Refreshing beverage collection',
    'A drink for every occasion',
    'Premium beers, refreshing sodas, energising favourites, juices, yoghurt, tea, and water.',
    'hero--drinks'
);
?>
<main id="menu-content" class="page-shell page-shell--menu" data-menu-root>
    <div class="menu-toolbar">
        <div>
            <span class="eyebrow eyebrow--dark">Browse by category</span>
            <h2>Drinks menu</h2>
        </div>
        <?php render_category_navigation('drinks'); ?>
    </div>
    <?php render_menu_sections('drinks', $items); ?>
</main>
<?php render_public_footer(); ?>

