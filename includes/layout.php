<?php
declare(strict_types=1);

function render_public_header(
    string $page,
    string $title,
    string $kicker,
    string $heading,
    string $description,
    string $heroClass = 'hero--food'
): void {
    $searchPlaceholder = match ($page) {
        'food' => 'Search food…',
        'drinks' => 'Search drinks…',
        default => 'Search specials…',
    };
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Asabana Hotel menu featuring food, drinks, and chef’s specials.">
    <meta name="theme-color" content="#17120f">
    <title><?= e($title) ?></title>
    <link rel="icon" href="<?= e(media_url('images/logo.jpg')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('style.css')) ?>">
    <script src="<?= e(app_url('site.js')) ?>" defer></script>
</head>
<body data-page="<?= e($page) ?>">
    <header class="hero <?= e($heroClass) ?>">
        <div class="hero__inner">
            <span class="eyebrow"><?= e($kicker) ?></span>
            <h1><?= e($heading) ?></h1>
            <p><?= e($description) ?></p>
            <div class="hero__actions">
                <a class="button button--gold" href="#menu-content">Explore the menu</a>
                <a class="button button--ghost" href="<?= e(app_url($page === 'drinks' ? 'food.php' : 'drinks.php')) ?>">
                    <?= $page === 'drinks' ? 'View food' : 'View drinks' ?>
                </a>
            </div>
        </div>
    </header>

    <nav class="site-nav" aria-label="Primary navigation">
        <div class="site-nav__inner">
            <a class="brand" href="<?= e(app_url()) ?>">
                <span class="brand__crest"><img src="<?= e(media_url('images/logo.jpg')) ?>" alt=""></span>
                <span>
                    <strong>Asabana Hotel</strong>
                    <small>Classic menu collection</small>
                </span>
            </a>

            <div class="site-nav__links">
                <a class="<?= $page === 'home' ? 'is-active' : '' ?>" href="<?= e(app_url()) ?>">Specials</a>
                <a class="<?= $page === 'food' ? 'is-active' : '' ?>" href="<?= e(app_url('food.php')) ?>">Food</a>
                <a class="<?= $page === 'drinks' ? 'is-active' : '' ?>" href="<?= e(app_url('drinks.php')) ?>">Drinks</a>
            </div>

            <label class="menu-search">
                <span class="sr-only"><?= e($searchPlaceholder) ?></span>
                <input id="menu-search" type="search" placeholder="<?= e($searchPlaceholder) ?>" autocomplete="off">
            </label>

            <button class="cart-trigger" type="button" data-cart-open aria-label="Open order">
                <span>Order</span>
                <span class="cart-count" data-cart-count hidden>0</span>
            </button>
        </div>
    </nav>
<?php
}

function render_category_navigation(string $type): void
{
    $categories = menu_categories()[$type] ?? [];
    ?>
    <div class="category-nav" aria-label="<?= e(ucfirst($type)) ?> categories">
        <button class="category-chip is-active" type="button" data-category-filter="all">Show all</button>
        <?php foreach ($categories as $id => $label): ?>
            <button class="category-chip" type="button" data-category-filter="<?= e($id) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
    </div>
    <?php
}

function render_menu_item(array $item): void
{
    $image = (string) ($item['image'] ?? 'images/logo.jpg');
    $thumb = (string) ($item['thumb'] ?? $image);
    $description = trim((string) ($item['description'] ?? ''));
    $searchText = strtolower((string) $item['title'] . ' ' . $description . ' ' . category_label((string) $item['category']));
    ?>
    <article
        class="menu-item"
        data-menu-card
        data-category="<?= e((string) $item['category']) ?>"
        data-search="<?= e($searchText) ?>"
    >
        <img
            class="menu-item__image"
            src="<?= e(media_url($thumb)) ?>"
            <?php if ($thumb !== $image): ?>srcset="<?= e(media_url($thumb)) ?> 480w, <?= e(media_url($image)) ?> 1600w" sizes="88px"<?php endif; ?>
            alt=""
            width="176"
            height="176"
            loading="lazy"
            decoding="async"
        >
        <div class="menu-item__content">
            <div class="menu-item__heading">
                <h3><?= e((string) $item['title']) ?></h3>
                <?php if (!empty($item['is_special'])): ?><span class="pill">Special</span><?php endif; ?>
            </div>
            <?php if ($description !== ''): ?><p><?= e($description) ?></p><?php endif; ?>
            <small><?= e(category_label((string) $item['category'])) ?></small>
        </div>
        <button
            class="price-button"
            type="button"
            data-add-to-cart
            data-item-id="<?= e((string) $item['id']) ?>"
            data-item-title="<?= e((string) $item['title']) ?>"
            data-item-price="<?= (int) $item['price'] ?>"
            data-item-image="<?= e(media_url($thumb)) ?>"
            aria-label="Add <?= e((string) $item['title']) ?> to order"
        >
            <?= e(format_price((int) $item['price'])) ?>
        </button>
    </article>
    <?php
}

function render_menu_sections(string $type, array $items): void
{
    $categories = menu_categories()[$type] ?? [];

    foreach ($categories as $category => $label) {
        $categoryItems = array_values(array_filter($items, static fn (array $item): bool => ($item['category'] ?? '') === $category));

        if ($categoryItems === []) {
            continue;
        }
        ?>
        <section class="menu-section" data-menu-section data-category="<?= e($category) ?>">
            <div class="section-heading">
                <div>
                    <span><?= e(ucfirst($type)) ?> collection</span>
                    <h2><?= e($label) ?></h2>
                </div>
                <b><?= count($categoryItems) ?> <?= count($categoryItems) === 1 ? 'item' : 'items' ?></b>
            </div>
            <div class="menu-list">
                <?php foreach ($categoryItems as $item): ?>
                    <?php render_menu_item($item); ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}

function render_public_footer(): void
{
    ?>
    <div class="empty-state" data-empty-state hidden>
        <h2>No menu items found</h2>
        <p>Try another search or category.</p>
    </div>

    <footer class="site-footer">
        <strong>Asabana Hotel</strong>
        <p>Classic dining · Food & drinks</p>
        <small>© <?= date('Y') ?> Asabana Hotel. All rights reserved.</small>
    </footer>

    <div class="cart-overlay" data-cart-overlay hidden>
        <section class="cart-drawer" role="dialog" aria-modal="true" aria-labelledby="cart-title">
            <header>
                <div>
                    <span class="eyebrow eyebrow--dark">Your selection</span>
                    <h2 id="cart-title">Order summary</h2>
                </div>
                <button class="icon-button" type="button" data-cart-close aria-label="Close order">×</button>
            </header>
            <div class="cart-items" data-cart-items></div>
            <div class="cart-empty" data-cart-empty>
                <strong>Your order is empty</strong>
                <p>Add items by selecting their prices.</p>
            </div>
            <footer>
                <div class="cart-total">
                    <span>Total</span>
                    <strong data-cart-total>₦0</strong>
                </div>
                <button class="button button--whatsapp" type="button" data-whatsapp-order>Order on WhatsApp</button>
                <button class="text-button" type="button" data-cart-clear>Clear order</button>
            </footer>
        </section>
    </div>

    <div class="toast" role="status" aria-live="polite" data-toast hidden></div>
</body>
</html>
<?php
}

