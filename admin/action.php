<?php
declare(strict_types=1);

define('ASABANA_ADMIN', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/Auth.php';
require_once BASE_PATH . '/includes/ImageProcessor.php';

$auth = new AdminAuth(BASE_PATH);
$auth->requireAuthenticated();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    verify_csrf();
    $operation = (string) ($_POST['operation'] ?? '');
    $store = menu_store();
    $processor = new ImageProcessor(BASE_PATH);

    if ($operation === 'delete') {
        $id = (string) ($_POST['id'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_-]{3,100}$/', $id)) {
            throw new RuntimeException('Invalid menu item.');
        }

        $deleted = $store->delete($id);

        if ($deleted === null) {
            throw new RuntimeException('Menu item not found.');
        }

        $processor->deleteManagedImages($deleted['image'] ?? null, $deleted['thumb'] ?? null);
        flash('success', 'Menu item deleted.');
    } elseif ($operation === 'save') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $existing = $id !== '' ? $store->find($id) : null;

        if ($id !== '' && (!preg_match('/^[A-Za-z0-9_-]{3,100}$/', $id) || $existing === null)) {
            throw new RuntimeException('The menu item could not be found.');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $priceInput = trim((string) ($_POST['price'] ?? ''));
        $category = (string) ($_POST['category'] ?? '');
        $type = category_type($category);
        $displayOrder = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT);

        if ($title === '' || strlen($title) > 120) {
            throw new RuntimeException('Item name is required and must be no longer than 120 characters.');
        }

        if (strlen($description) > 1000) {
            throw new RuntimeException('Description must be no longer than 1,000 characters.');
        }

        if (!preg_match('/^\d{1,9}$/', $priceInput) || (int) $priceInput > 100_000_000) {
            throw new RuntimeException('Enter a valid price between ₦0 and ₦100,000,000.');
        }

        if ($type === null) {
            throw new RuntimeException('Choose a valid menu category.');
        }

        if ($displayOrder === false || $displayOrder < 0 || $displayOrder > 9999) {
            throw new RuntimeException('Display order must be between 0 and 9,999.');
        }

        $newImages = null;
        $upload = $_FILES['image'] ?? null;

        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newImages = $processor->process($upload);
        }

        $item = [
            'id' => $existing['id'] ?? ('item-' . bin2hex(random_bytes(10))),
            'title' => $title,
            'description' => $description,
            'price' => (int) $priceInput,
            'type' => $type,
            'category' => $category,
            'image' => $newImages['image'] ?? $existing['image'] ?? 'images/logo.jpg',
            'thumb' => $newImages['thumb'] ?? $existing['thumb'] ?? $existing['image'] ?? 'images/logo.jpg',
            'is_available' => isset($_POST['is_available']),
            'is_special' => isset($_POST['is_special']),
            'display_order' => (int) $displayOrder,
            'created_at' => $existing['created_at'] ?? gmdate('c'),
            'updated_at' => gmdate('c'),
        ];

        try {
            $store->upsert($item);
        } catch (Throwable $exception) {
            if ($newImages !== null) {
                $processor->deleteManagedImages($newImages['image'], $newImages['thumb']);
            }
            throw $exception;
        }

        if ($newImages !== null && $existing !== null) {
            $processor->deleteManagedImages($existing['image'] ?? null, $existing['thumb'] ?? null);
        }

        flash('success', $existing === null ? 'Menu item added and published.' : 'Menu item updated and published.');
    } else {
        throw new RuntimeException('Unknown administrator action.');
    }
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
}

header('Location: ' . app_url('admin/'));
exit;

