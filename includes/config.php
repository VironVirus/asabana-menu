<?php
declare(strict_types=1);

/**
 * Template identity and ordering settings.
 *
 * Change these values when reusing the template for a client. The WhatsApp
 * number must contain digits only, including the country code (for example,
 * 2348012345678). Leave it empty while the website is being demonstrated.
 */
function site_config(): array
{
    return [
        'name' => 'Tapxora template menu',
        'short_name' => 'Tapxora menu',
        'tagline' => 'Scan · Tap · Order',
        'logo' => 'images/tapxora-logo.jpg',
        'menu_placeholder' => 'images/menu-placeholder.svg',
        'whatsapp_number' => '',
        'order_greeting' => "Hello, I'd like to place an order from the Tapxora template menu:",
    ];
}

