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
    'Asabana Menu | Hotel Dining',
    'Exquisite hotel dining',
    'The Asabana culinary experience',
    'Traditional flavours, thoughtful presentation, and a carefully curated menu.',
    'hero--home'
);
?>
<main id="menu-content" class="page-shell">
    <section class="intro">
        <span class="eyebrow eyebrow--dark">Chef’s selection</span>
        <h2>Today’s featured dishes</h2>
        <p>Explore guest favourites and seasonal recommendations from the Asabana kitchen.</p>
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
            <h2>Explore our food menu</h2>
            <p>Swallow, quick bites, rice, pasta, proteins, and outdoor grills.</p>
            <b>View food →</b>
        </a>
        <a class="collection-card collection-card--drinks" href="<?= e(app_url('drinks.php')) ?>">
            <span>Beverage collection</span>
            <h2>Discover our drinks</h2>
            <p>Beer, malt, energy drinks, water, juices, yoghurt, tea, and sodas.</p>
            <b>View drinks →</b>
        </a>
    </section>
</main>
<?php render_public_footer(); ?>

