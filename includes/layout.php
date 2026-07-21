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
    $config = site_config();
    $searchPlaceholder = match ($page) {
        'food' => 'Search food…',
        'drinks' => 'Search drinks…',
        default => 'Search featured items…',
    };
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="A polished QR and NFC-ready digital menu template by Tapxora.">
    <meta name="theme-color" content="#0a3a2b">
    <title><?= e($title) ?></title>
    <link rel="icon" href="<?= e(media_url($config['logo'])) ?>">
    <link rel="stylesheet" href="<?= e(app_url('style.css')) ?>">
    <script src="<?= e(app_url('site.js')) ?>" defer></script>
</head>
<body
    data-page="<?= e($page) ?>"
    data-whatsapp-number="<?= e($config['whatsapp_number']) ?>"
    data-order-greeting="<?= e($config['order_greeting']) ?>"
>
    <a class="skip-link" href="#menu-content">Skip to menu</a>

    <nav class="site-nav" aria-label="Primary navigation">
        <div class="site-nav__inner">
            <a class="brand" href="<?= e(app_url()) ?>" aria-label="<?= e($config['name']) ?> home">
                <span class="brand__crest"><img src="<?= e(media_url($config['logo'])) ?>" alt="Tapxora"></span>
                <span class="brand__copy">
                    <strong><?= e($config['name']) ?></strong>
                    <small><?= e($config['tagline']) ?></small>
                </span>
            </a>

            <div class="site-nav__links">
                <a class="<?= $page === 'home' ? 'is-active' : '' ?>" href="<?= e(app_url()) ?>">Featured</a>
                <a class="<?= $page === 'food' ? 'is-active' : '' ?>" href="<?= e(app_url('food.php')) ?>">Food</a>
                <a class="<?= $page === 'drinks' ? 'is-active' : '' ?>" href="<?= e(app_url('drinks.php')) ?>">Drinks</a>
            </div>

            <label class="menu-search">
                <span class="sr-only"><?= e($searchPlaceholder) ?></span>
                <input id="menu-search" type="search" placeholder="<?= e($searchPlaceholder) ?>" autocomplete="off">
            </label>

            <button class="cart-trigger" type="button" data-cart-open aria-label="Open order summary">
                <svg class="cart-trigger__icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 8h12l1 12H5L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>
                </svg>
                <span>My order</span>
                <span class="cart-count" data-cart-count hidden>0</span>
            </button>
        </div>
    </nav>

    <header class="hero <?= e($heroClass) ?>">
        <div class="hero__inner">
            <div class="hero__copy">
                <span class="eyebrow"><?= e($kicker) ?></span>
                <h1><?= e($heading) ?></h1>
                <p><?= e($description) ?></p>
                <div class="hero__actions">
                    <a class="button button--gold" href="#menu-content">Browse the menu</a>
                    <a class="button button--ghost" href="<?= e(app_url($page === 'drinks' ? 'food.php' : 'drinks.php')) ?>">
                        <?= $page === 'drinks' ? 'Explore food' : 'Explore drinks' ?>
                    </a>
                </div>
            </div>

            <div class="hero__signal" aria-hidden="true">
                <span class="hero__signal-label"><i></i> Live demo</span>
                <strong>Tap. Browse.<br>Choose.</strong>
                <p>One effortless menu experience, made for every screen.</p>
                <div class="hero__signal-row">
                    <span><b>01</b> No app</span>
                    <span><b>02</b> Quick order</span>
                </div>
            </div>
        </div>
    </header>
<?php
}

function render_category_navigation(string $type): void
{
    $categories = menu_categories()[$type] ?? [];
    ?>
    <div class="category-nav" aria-label="<?= e(ucfirst($type)) ?> categories">
        <button class="category-chip is-active" type="button" data-category-filter="all">All items</button>
        <?php foreach ($categories as $id => $label): ?>
            <button class="category-chip" type="button" data-category-filter="<?= e($id) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
    </div>
    <?php
}

function render_menu_item(array $item): void
{
    $config = site_config();
    $image = (string) ($item['image'] ?? $config['menu_placeholder']);
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
        <div class="menu-item__media">
            <img
                class="menu-item__image"
                src="<?= e(media_url($thumb)) ?>"
                <?php if ($thumb !== $image): ?>srcset="<?= e(media_url($thumb)) ?> 480w, <?= e(media_url($image)) ?> 1600w" sizes="(max-width: 640px) 100vw, 360px"<?php endif; ?>
                alt=""
                width="720"
                height="540"
                loading="lazy"
                decoding="async"
            >
            <?php if (!empty($item['is_special'])): ?><span class="pill">Featured</span><?php endif; ?>
        </div>
        <div class="menu-item__content">
            <div class="menu-item__heading">
                <small><?= e(category_label((string) $item['category'])) ?></small>
                <h3><?= e((string) $item['title']) ?></h3>
            </div>
            <?php if ($description !== ''): ?><p><?= e($description) ?></p><?php endif; ?>
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
                <span><?= e(format_price((int) $item['price'])) ?></span>
                <b aria-hidden="true">+</b>
            </button>
        </div>
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
                <b><?= count($categoryItems) ?> <?= count($categoryItems) === 1 ? 'choice' : 'choices' ?></b>
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
    $config = site_config();
    ?>
    <div class="empty-state" data-empty-state hidden>
        <span>Nothing here yet</span>
        <h2>No menu items found</h2>
        <p>Try another search or choose a different category.</p>
    </div>

    <footer class="site-footer">
        <div class="site-footer__brand">
            <img src="<?= e(media_url($config['logo'])) ?>" alt="Tapxora">
            <div>
                <strong><?= e($config['name']) ?></strong>
                <p>A reusable digital menu experience for restaurants, bars, cafés, and hospitality brands.</p>
            </div>
        </div>
        <div class="site-footer__meta">
            <span>Built for QR &amp; NFC access</span>
            <small>© <?= date('Y') ?> Tapxora. Template demo.</small>
        </div>
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
                <span>0 items</span>
                <strong>Your order is empty</strong>
                <p>Tap any price to add an item.</p>
            </div>
            <footer>
                <div class="cart-total">
                    <span>Total</span>
                    <strong data-cart-total>₦0</strong>
                </div>
                <button class="button button--whatsapp" type="button" data-whatsapp-order>Send on WhatsApp</button>
                <button class="text-button" type="button" data-cart-clear>Clear order</button>
            </footer>
        </section>
    </div>

    <div class="toast" role="status" aria-live="polite" data-toast hidden></div>
</body>
</html>
<?php
}
