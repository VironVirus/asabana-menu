<?php
declare(strict_types=1);

define('TAPXORA_ADMIN', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/Auth.php';

$auth = new AdminAuth(BASE_PATH);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $operation = (string) ($_POST['operation'] ?? '');

        if ($operation === 'setup' && !$auth->hasAdmin()) {
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');

            if (!hash_equals($password, $confirmation)) {
                throw new RuntimeException('Passwords do not match.');
            }

            $auth->createAdmin((string) ($_POST['username'] ?? ''), $password);
            flash('success', 'Administrator account created successfully.');
            header('Location: ' . app_url('admin/'));
            exit;
        }

        if ($operation === 'login' && $auth->hasAdmin()) {
            if (!$auth->login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''), client_ip())) {
                throw new RuntimeException('The username or password is incorrect.');
            }

            header('Location: ' . app_url('admin/'));
            exit;
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if (isset($_GET['download']) && $_GET['download'] === 'menu') {
    $auth->requireAuthenticated();
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tapxora-template-menu-' . gmdate('Y-m-d') . '.json"');
    echo menu_store()->rawJson();
    exit;
}

$authenticated = $auth->isAuthenticated();
$items = $authenticated ? menu_store()->items(null, false) : [];
$menuData = $authenticated ? menu_store()->data() : ['updated_at' => null];
$flashMessage = $authenticated ? pull_flash() : null;
$categoryGroups = menu_categories();
$config = site_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0a3a2b">
    <title>Menu Manager | <?= e($config['name']) ?></title>
    <link rel="icon" href="<?= e(media_url($config['logo'])) ?>">
    <link rel="stylesheet" href="<?= e(app_url('admin/admin.css')) ?>">
    <?php if ($authenticated): ?><script src="<?= e(app_url('admin/admin.js')) ?>" defer></script><?php endif; ?>
</head>
<body class="admin-body">
<?php if (!$auth->hasAdmin()): ?>
    <main class="auth-shell">
        <section class="auth-card">
            <img src="<?= e(media_url($config['logo'])) ?>" alt="Tapxora">
            <span>One-time setup</span>
            <h1>Create the menu administrator</h1>
            <p>Create this account immediately after the first deployment. Setup permanently closes when the account is saved.</p>

            <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="operation" value="setup">
                <label>
                    Administrator username
                    <input type="text" name="username" minlength="3" maxlength="64" pattern="[A-Za-z0-9._-]+" autocomplete="username" required>
                </label>
                <label>
                    Password
                    <input type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" required>
                    <small>Use at least 12 characters with a letter and number.</small>
                </label>
                <label>
                    Confirm password
                    <input type="password" name="password_confirmation" minlength="12" maxlength="200" autocomplete="new-password" required>
                </label>
                <button class="admin-button admin-button--primary" type="submit">Create secure account</button>
            </form>
        </section>
    </main>
<?php elseif (!$authenticated): ?>
    <main class="auth-shell">
        <section class="auth-card">
            <img src="<?= e(media_url($config['logo'])) ?>" alt="Tapxora">
            <span>Private administration</span>
            <h1>Sign in to manage the menu</h1>
            <p>Access is rate-limited and protected with secure server sessions.</p>

            <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>

            <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="operation" value="login">
                <label>
                    Username
                    <input type="text" name="username" maxlength="64" autocomplete="username" required autofocus>
                </label>
                <label>
                    Password
                    <input type="password" name="password" maxlength="200" autocomplete="current-password" required>
                </label>
                <button class="admin-button admin-button--primary" type="submit">Sign in</button>
            </form>
            <a class="back-link" href="<?= e(app_url()) ?>">← Return to the menu</a>
        </section>
    </main>
<?php else: ?>
    <header class="admin-header">
        <a class="admin-brand" href="<?= e(app_url('admin/')) ?>">
            <img src="<?= e(media_url($config['logo'])) ?>" alt="">
            <span><strong>Tapxora menu manager</strong><small>Signed in as <?= e($auth->username()) ?></small></span>
        </a>
        <nav>
            <a href="<?= e(app_url()) ?>" target="_blank" rel="noopener">View website</a>
            <a href="?download=menu">Export menu</a>
            <form action="<?= e(app_url('admin/logout.php')) ?>" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit">Sign out</button>
            </form>
        </nav>
    </header>

    <main class="admin-shell">
        <?php if ($flashMessage): ?>
            <div class="alert <?= $flashMessage['type'] === 'success' ? 'alert--success' : 'alert--error' ?>">
                <?= e((string) $flashMessage['message']) ?>
            </div>
        <?php endif; ?>

        <section class="dashboard-heading">
            <div>
                <span>Template control room</span>
                <h1>Manage the live menu</h1>
                <p>Add, edit, hide, feature, or delete items. Every image is resized, stripped of metadata, and compressed before publishing.</p>
            </div>
            <button class="admin-button admin-button--primary" type="button" data-open-item-modal>Add menu item</button>
        </section>

        <section class="stat-grid" aria-label="Menu statistics">
            <article><strong><?= count($items) ?></strong><span>Total items</span></article>
            <article><strong><?= count(array_filter($items, static fn (array $item): bool => ($item['type'] ?? '') === 'food')) ?></strong><span>Food items</span></article>
            <article><strong><?= count(array_filter($items, static fn (array $item): bool => ($item['type'] ?? '') === 'drinks')) ?></strong><span>Drink items</span></article>
            <article><strong><?= count(array_filter($items, static fn (array $item): bool => !empty($item['is_special']))) ?></strong><span>Specials</span></article>
        </section>

        <section class="manager-card">
            <header class="manager-card__header">
                <div>
                    <h2>Menu items</h2>
                    <small>Last published <?= e($menuData['updated_at'] ? date('j M Y, g:i a', strtotime((string) $menuData['updated_at'])) : 'during installation') ?></small>
                </div>
                <label class="admin-search">
                    <span class="sr-only">Search menu items</span>
                    <input type="search" placeholder="Search menu items…" data-admin-search>
                </label>
            </header>

            <div class="admin-item-list" data-admin-item-list>
                <?php foreach ($items as $item): ?>
                    <?php
                    $image = (string) ($item['thumb'] ?? $item['image'] ?? $config['menu_placeholder']);
                    $search = strtolower((string) $item['title'] . ' ' . category_label((string) $item['category']));
                    ?>
                    <article class="admin-item" data-admin-item data-search="<?= e($search) ?>">
                        <img src="<?= e(media_url($image)) ?>" alt="" width="64" height="64" loading="lazy">
                        <div class="admin-item__content">
                            <div>
                                <h3><?= e((string) $item['title']) ?></h3>
                                <?php if (empty($item['is_available'])): ?><span class="status status--hidden">Hidden</span><?php endif; ?>
                                <?php if (!empty($item['is_special'])): ?><span class="status status--special">Special</span><?php endif; ?>
                            </div>
                            <p><?= e(category_label((string) $item['category'])) ?> · <?= e(format_price((int) $item['price'])) ?></p>
                        </div>
                        <div class="admin-item__actions">
                            <button
                                type="button"
                                data-edit-item
                                data-id="<?= e((string) $item['id']) ?>"
                                data-title="<?= e((string) $item['title']) ?>"
                                data-price="<?= (int) $item['price'] ?>"
                                data-category="<?= e((string) $item['category']) ?>"
                                data-description="<?= e((string) ($item['description'] ?? '')) ?>"
                                data-order="<?= (int) ($item['display_order'] ?? 0) ?>"
                                data-available="<?= !empty($item['is_available']) ? '1' : '0' ?>"
                                data-special="<?= !empty($item['is_special']) ? '1' : '0' ?>"
                                data-image="<?= e($image) ?>"
                            >Edit</button>
                            <form action="<?= e(app_url('admin/action.php')) ?>" method="post" data-delete-form>
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="operation" value="delete">
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <button class="danger-link" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if ($items === []): ?>
                    <div class="admin-empty">No menu items yet. Add the first item to get started.</div>
                <?php endif; ?>
            </div>
            <div class="admin-empty" data-admin-no-results hidden>No items match your search.</div>
        </section>
    </main>

    <div class="admin-modal" data-item-modal hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="item-modal-title">
            <header>
                <div>
                    <span>Menu item</span>
                    <h2 id="item-modal-title" data-modal-title>Add menu item</h2>
                </div>
                <button type="button" data-close-item-modal aria-label="Close">×</button>
            </header>

            <form action="<?= e(app_url('admin/action.php')) ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="operation" value="save">
                <input type="hidden" name="id" value="" data-field-id>

                <div class="form-grid">
                    <label class="form-grid__wide">
                        Item name
                        <input type="text" name="title" maxlength="120" required data-field-title>
                    </label>
                    <label>
                        Price (₦)
                        <input type="number" name="price" min="0" max="100000000" step="1" required data-field-price>
                    </label>
                    <label>
                        Category
                        <select name="category" required data-field-category>
                            <?php foreach ($categoryGroups as $type => $categories): ?>
                                <optgroup label="<?= e(ucfirst($type)) ?>">
                                    <?php foreach ($categories as $id => $label): ?>
                                        <option value="<?= e($id) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-grid__wide">
                        Description
                        <textarea name="description" rows="4" maxlength="1000" data-field-description></textarea>
                    </label>
                    <label>
                        Display order
                        <input type="number" name="display_order" min="0" max="9999" step="1" value="0" data-field-order>
                    </label>
                    <label>
                        Image
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-field-image>
                        <small>JPEG, PNG, or WebP up to 8 MB. Automatically resized and compressed.</small>
                    </label>
                    <div class="image-preview form-grid__wide" data-image-preview-wrap hidden>
                        <img src="" alt="Current menu item image" data-image-preview>
                        <span>Current image</span>
                    </div>
                    <label class="check-label">
                        <input type="checkbox" name="is_available" value="1" checked data-field-available>
                        Visible on menu
                    </label>
                    <label class="check-label">
                        <input type="checkbox" name="is_special" value="1" data-field-special>
                        Feature as special
                    </label>
                </div>

                <footer>
                    <button class="admin-button" type="button" data-close-item-modal>Cancel</button>
                    <button class="admin-button admin-button--primary" type="submit">Save and publish</button>
                </footer>
            </form>
        </section>
    </div>
<?php endif; ?>
</body>
</html>
