<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/layout.php';

$items = menu_store()->items('food', true);

render_public_header(
    'food',
    'Food Menu | Asabana Hotel',
    'Exquisite culinary collection',
    'Food made for every appetite',
    'Comforting local favourites, satisfying quick bites, and memorable grill selections.',
    'hero--food'
);
?>
<main id="menu-content" class="page-shell page-shell--menu" data-menu-root>
    <div class="menu-toolbar">
        <div>
            <span class="eyebrow eyebrow--dark">Browse by category</span>
            <h2>Food menu</h2>
        </div>
        <?php render_category_navigation('food'); ?>
    </div>
    <?php render_menu_sections('food', $items); ?>
</main>
<?php render_public_footer(); ?>

