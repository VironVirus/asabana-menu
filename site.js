(() => {
    'use strict';

    const CART_KEY = 'tapxora_template_cart_v1';
    const WHATSAPP_NUMBER = (document.body.dataset.whatsappNumber || '').replace(/\D/g, '');
    const ORDER_GREETING = document.body.dataset.orderGreeting || "Hello, I'd like to place an order:";
    const searchInput = document.querySelector('#menu-search');
    const categoryButtons = [...document.querySelectorAll('[data-category-filter]')];
    const menuCards = [...document.querySelectorAll('[data-menu-card]')];
    const menuSections = [...document.querySelectorAll('[data-menu-section]')];
    const emptyState = document.querySelector('[data-empty-state]');
    const overlay = document.querySelector('[data-cart-overlay]');
    const cartItems = document.querySelector('[data-cart-items]');
    const cartEmpty = document.querySelector('[data-cart-empty]');
    const cartTotal = document.querySelector('[data-cart-total]');
    const cartCounts = [...document.querySelectorAll('[data-cart-count]')];
    const toast = document.querySelector('[data-toast]');
    let activeCategory = 'all';
    let lastFocusedElement = null;
    let toastTimer = null;

    const parseCart = () => {
        try {
            const value = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
            if (!Array.isArray(value)) return [];

            return value
                .filter(item => item && typeof item.id === 'string' && typeof item.title === 'string')
                .map(item => ({
                    id: item.id.slice(0, 100),
                    title: item.title.slice(0, 160),
                    price: Math.max(0, Number.parseInt(item.price, 10) || 0),
                    image: typeof item.image === 'string' && item.image.startsWith('/') ? item.image : '',
                    quantity: Math.min(99, Math.max(1, Number.parseInt(item.quantity, 10) || 1)),
                }));
        } catch {
            return [];
        }
    };

    let cart = parseCart();

    const currency = value => `₦${Number(value).toLocaleString('en-NG')}`;

    const saveCart = () => {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    };

    const showToast = message => {
        if (!toast) return;
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.hidden = false;
        toastTimer = window.setTimeout(() => {
            toast.hidden = true;
        }, 2200);
    };

    const makeButton = (label, action, itemId) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'quantity-button';
        button.textContent = label;
        button.dataset.cartAction = action;
        button.dataset.itemId = itemId;
        button.setAttribute('aria-label', `${action === 'increase' ? 'Increase' : 'Decrease'} quantity`);
        return button;
    };

    const renderCart = () => {
        if (!cartItems || !cartEmpty || !cartTotal) return;
        cartItems.replaceChildren();

        let count = 0;
        let total = 0;

        cart.forEach(item => {
            count += item.quantity;
            total += item.price * item.quantity;

            const row = document.createElement('article');
            row.className = 'cart-item';

            if (item.image) {
                const image = document.createElement('img');
                image.src = item.image;
                image.alt = '';
                image.width = 64;
                image.height = 64;
                row.append(image);
            }

            const content = document.createElement('div');
            content.className = 'cart-item__content';
            const title = document.createElement('strong');
            title.textContent = item.title;
            const price = document.createElement('span');
            price.textContent = currency(item.price);
            content.append(title, price);

            const controls = document.createElement('div');
            controls.className = 'quantity-controls';
            const quantity = document.createElement('b');
            quantity.textContent = String(item.quantity);
            quantity.setAttribute('aria-label', `Quantity ${item.quantity}`);
            controls.append(makeButton('−', 'decrease', item.id), quantity, makeButton('+', 'increase', item.id));
            row.append(content, controls);
            cartItems.append(row);
        });

        cartEmpty.hidden = cart.length > 0;
        cartItems.hidden = cart.length === 0;
        cartTotal.textContent = currency(total);

        cartCounts.forEach(badge => {
            badge.textContent = String(count);
            badge.hidden = count === 0;
        });
    };

    const updateQuantity = (id, change) => {
        cart = cart
            .map(item => item.id === id ? {...item, quantity: item.quantity + change} : item)
            .filter(item => item.quantity > 0);
        saveCart();
        renderCart();
    };

    const addToCart = button => {
        const item = {
            id: button.dataset.itemId || '',
            title: button.dataset.itemTitle || 'Menu item',
            price: Number.parseInt(button.dataset.itemPrice, 10) || 0,
            image: button.dataset.itemImage || '',
            quantity: 1,
        };
        const existing = cart.find(entry => entry.id === item.id);

        if (existing) {
            existing.quantity = Math.min(99, existing.quantity + 1);
        } else {
            cart.push(item);
        }

        saveCart();
        renderCart();
        showToast(`${item.title} added to your order`);
    };

    const openCart = trigger => {
        if (!overlay) return;
        lastFocusedElement = trigger || document.activeElement;
        overlay.hidden = false;
        document.body.classList.add('cart-open');
        overlay.querySelector('[data-cart-close]')?.focus();
    };

    const closeCart = () => {
        if (!overlay) return;
        overlay.hidden = true;
        document.body.classList.remove('cart-open');
        if (lastFocusedElement instanceof HTMLElement) lastFocusedElement.focus();
    };

    const filterMenu = () => {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visibleCards = 0;

        menuCards.forEach(card => {
            const matchesCategory = activeCategory === 'all' || card.dataset.category === activeCategory;
            const matchesSearch = query === '' || (card.dataset.search || '').includes(query);
            const visible = matchesCategory && matchesSearch;
            card.hidden = !visible;
            if (visible) visibleCards += 1;
        });

        menuSections.forEach(section => {
            const visible = [...section.querySelectorAll('[data-menu-card]')].some(card => !card.hidden);
            section.hidden = !visible;
        });

        if (emptyState) emptyState.hidden = visibleCards !== 0 || menuCards.length === 0;
    };

    document.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const addButton = target.closest('[data-add-to-cart]');
        if (addButton instanceof HTMLButtonElement) {
            addToCart(addButton);
            return;
        }

        const openButton = target.closest('[data-cart-open]');
        if (openButton instanceof HTMLButtonElement) {
            openCart(openButton);
            return;
        }

        if (target.closest('[data-cart-close]') || target === overlay) {
            closeCart();
            return;
        }

        const quantityButton = target.closest('[data-cart-action]');
        if (quantityButton instanceof HTMLButtonElement) {
            updateQuantity(quantityButton.dataset.itemId || '', quantityButton.dataset.cartAction === 'increase' ? 1 : -1);
            return;
        }

        if (target.closest('[data-cart-clear]')) {
            cart = [];
            saveCart();
            renderCart();
            return;
        }

        if (target.closest('[data-whatsapp-order]') && cart.length > 0) {
            if (!WHATSAPP_NUMBER) {
                showToast('Add a WhatsApp number in includes/config.php to enable ordering.');
                return;
            }
            const lines = cart.map(item => `• ${item.title} (x${item.quantity}) — ${currency(item.price * item.quantity)}`);
            const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
            const message = `${ORDER_GREETING}\n\n${lines.join('\n')}\n\nTotal: ${currency(total)}`;
            window.location.href = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
        }
    });

    categoryButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeCategory = button.dataset.categoryFilter || 'all';
            categoryButtons.forEach(candidate => candidate.classList.toggle('is-active', candidate === button));
            filterMenu();
        });
    });

    searchInput?.addEventListener('input', filterMenu);

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && overlay && !overlay.hidden) closeCart();
    });

    renderCart();
    filterMenu();
})();
