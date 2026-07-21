<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/layout.php';

$specials = array_values(array_filter(
    menu_store()->items(null, true),
    static fn (array $item): bool => !empty($item['is_special'])
));

render_public_header(
    'home',
    'Tapxora template menu | Digital menu demo',
    'A digital menu built to convert',
    'A polished menu, ready for your brand.',
    'Fast to browse, simple to update, and designed for QR and NFC access on every screen.',
    'hero--home'
);
?>
<main id="menu-content" class="page-shell">
    <section class="intro">
        <span class="eyebrow eyebrow--dark">Popular right now</span>
        <h2>Featured menu picks</h2>
        <p>A flexible showcase for bestsellers, seasonal offers, and the items you want customers to notice first.</p>
    </section>

    <div class="special-grid" data-menu-root>
        <?php if ($specials === []): ?>
            <div class="empty-panel">
                <h2>New specials are coming soon</h2>
                <p>Browse the complete food and drinks collections in the meantime.</p>
            </div>
        <?php else: ?>
            <?php foreach ($specials as $item): ?>
                <?php render_menu_item($item); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <section class="collection-links">
        <a class="collection-card collection-card--food" href="<?= e(app_url('food.php')) ?>">
            <span>Kitchen collection</span>
            <h2>Browse the food menu</h2>
            <p>Well-organised categories, clear prices, and rich photos that make choosing easy.</p>
            <b>View food →</b>
        </a>
        <a class="collection-card collection-card--drinks" href="<?= e(app_url('drinks.php')) ?>">
            <span>Beverage collection</span>
            <h2>Browse the drinks menu</h2>
            <p>From chilled favourites to alcohol-free options, everything stays quick to find.</p>
            <b>View drinks →</b>
        </a>
    </section>
</main>
<?php render_public_footer(); ?>
