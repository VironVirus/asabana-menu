/**
 * GLOBAL MENU DATA - Fetched from menu.json
 */
let MENU_DATA = { food: [], drinks: [], specials: [] };

async function fetchMenu() {
    try {
        // Cache busting helps see changes faster after a rebuild
        const res = await fetch(`menu.json?v=${Date.now()}`);
        MENU_DATA = await res.json();
    } catch (e) { console.error("Menu data sync failed:", e); }
}

const CATEGORY_NAMES = {
    'swallow': 'Swallow',
    'quick-bites': 'Quick Bites',
    'rice': 'Rice & Pasta',
    'protein': 'Protein',
    'grills-and-outdoors': 'Grills & Outdoors',
    'alcohol': 'Alcohol',
    'malt-energy': 'Malt & Energy',
    'water': 'Water',
    'juices-yoghurt-tea': 'Juices & Tea',
    'sodas': 'Sodas',
    'specials': 'Chef\'s Specials'
};

function debounce(fn, delay = 100) {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => fn(...args), delay);
    };
}

/**
 * PRICE MANAGEMENT
 */
function getPrice(itemId, defaultPrice) {
    let overrides = {};
    try {
        overrides = JSON.parse(localStorage.getItem('asabana_price_overrides') || '{}');
    } catch { overrides = {}; }

    if (overrides[itemId]) {
        const val = overrides[itemId].replace(/[^0-9]/g, '');
        return parseInt(val) || defaultPrice;
    }
    return defaultPrice;
}

/**
 * VANILLA HELPERS
 */
const backToTopBtn = document.getElementById('backToTop');
const searchInput = document.getElementById('menuSearch');
const pageType = document.body?.dataset.page || 'site';

function normalizeUnsplashUrl(rawUrl, width) {
    try {
        const url = new URL(rawUrl);
        if (!url.hostname.includes('unsplash.com')) return rawUrl;
        url.searchParams.set('auto', 'format');
        url.searchParams.set('fit', 'crop');
        url.searchParams.set('q', '76');
        if (width) url.searchParams.set('w', String(width));
        return url.toString();
    } catch { return rawUrl; }
}


function optimizeImages() {
    const images = Array.from(document.querySelectorAll('img'));

    images.forEach((img, index) => {
        const isThumb = img.classList.contains('w-16') || img.classList.contains('h-16');
        const isPriority = index < 2 || img.closest('.hero-shell');

        img.decoding = 'async';
        img.referrerPolicy = 'no-referrer';

        if (isPriority) {
            img.loading = 'eager';
            img.fetchPriority = 'high';
        } else {
            img.loading = 'lazy';
            img.fetchPriority = 'low';
        }

        if (!img.hasAttribute('width') || !img.hasAttribute('height')) {
            if (isThumb) {
                img.width = 64;
                img.height = 64;
            } else if (img.closest('.special-card__media') || img.closest('.category-card__media')) {
                img.width = 1200;
                img.height = 800;
            } else {
                img.width = 960;
                img.height = 640;
            }
        }

        if (img.currentSrc.includes('unsplash.com') || img.src.includes('unsplash.com')) {
            const srcBase = img.getAttribute('src') || img.src;
            const widths = isThumb ? [64, 96, 128] : [480, 768, 1200, 1600];
            img.srcset = widths
                .map((width) => `${normalizeUnsplashUrl(srcBase, width)} ${width}w`)
                .join(', ');
            img.sizes = isThumb ? '64px' : '(max-width: 768px) 100vw, 50vw';
            img.src = normalizeUnsplashUrl(srcBase, isThumb ? 128 : 1200);
        }
    });
}

// Function to calculate the total height of sticky elements and set CSS variables
let totalStickyHeaderHeight = 0;
function updateStickyHeaderHeight() {
    const stickyNav = document.querySelector('.sticky-nav');
    const topCategoryWrapper = document.querySelector('.sticky-category-wrapper--top');
    const navHeight = stickyNav ? stickyNav.offsetHeight : 0;

    let catHeight = (topCategoryWrapper && !topCategoryWrapper.classList.contains('is-hidden')) 
        ? topCategoryWrapper.offsetHeight : 0;

    totalStickyHeaderHeight = navHeight + catHeight;

    if (navHeight > 0) {
        document.documentElement.style.setProperty('--sticky-nav-height', `${navHeight}px`);
    }

    document.querySelectorAll('.menu-section').forEach(section => {
        section.style.scrollMarginTop = `${totalStickyHeaderHeight + 20}px`; 
    });
}

function handleBackToTopVisibility() {
    if (!backToTopBtn) {
        return;
    }

    if (window.scrollY > 420) {
        backToTopBtn.style.visibility = 'visible';
        backToTopBtn.style.opacity = '1';
    } else {
        backToTopBtn.style.visibility = 'hidden';
        backToTopBtn.style.opacity = '0';
    }
}

function applyTimeBasedTheme() {
    const hour = new Date().getHours();
    // 7 AM (7) to 7 PM (19) is light mode
    const isDark = hour < 7 || hour >= 19;
    if (isDark) {
        document.documentElement.classList.add('dark-mode');
    } else {
        document.documentElement.classList.remove('dark-mode');
    }
}

let lastScrollY = window.scrollY;

function handleNavVisibility() {
    const currentScrollY = window.scrollY;
    const topWrapper = document.querySelector('.sticky-category-wrapper--top');
    const bottomWrapper = document.querySelector('.sticky-category-wrapper--bottom');

    if (Math.abs(currentScrollY - lastScrollY) < 2) return;

    const isScrollingDown = currentScrollY > lastScrollY;
    const threshold = 300; 

    if (currentScrollY < threshold) {
        if (topWrapper) topWrapper.classList.remove('is-hidden');
        if (bottomWrapper) bottomWrapper.classList.add('is-hidden');
    } else {
        if (isScrollingDown) {
            if (topWrapper) topWrapper.classList.add('is-hidden');
            if (bottomWrapper) bottomWrapper.classList.remove('is-hidden');
        } else {
            if (topWrapper) topWrapper.classList.remove('is-hidden');
            if (bottomWrapper) bottomWrapper.classList.add('is-hidden');
        }
    }

    // Optimized call to avoid layout thrashing
    if (Math.abs(currentScrollY - lastScrollY) > 50) updateStickyHeaderHeight();
    
    lastScrollY = currentScrollY;
}

/**
 * REACT CART COMPONENT
 * Integrated directly into the existing script
 */
const { useState, useEffect, useCallback, useMemo } = React;

const MenuItem = ({ item, onAdd }) => {
    // If MENU_DATA is empty, don't render yet
    if (!item) return null;

    const [isPressed, setIsPressed] = useState(false);
    const displayPrice = getPrice(item.id, item.price);

    const handleAdd = () => {
        setIsPressed(true);
        onAdd(item);
        setTimeout(() => setIsPressed(false), 300);
    };

    return (
        <div className="menu-item" data-menu-item data-item-id={item.id}>
            <img src={item.img} alt={item.title} loading="lazy" />
            <div className="menu-item__meta">
                <p className="menu-item__title">{item.title}</p>
                {item.description && <p className="menu-item__description line-clamp-2">{item.description}</p>}
            </div>

            <div className="menu-item__actions">
                <button
                    onClick={handleAdd}
                    data-cart-action="add"
                    className={`menu-item__price menu-item__price-btn ${isPressed ? 'is-pressed' : ''}`}
                    type="button"
                    aria-label={`Add ${item.title} to cart`}
                    title={`Add ${item.title} to cart`}
                    aria-pressed={isPressed}
                >
                    ₦{displayPrice.toLocaleString()}
                </button>
            </div>
        </div>
    );
};

function App() {
    const [isLoaded, setIsLoaded] = useState(false);

    // New effect to fetch data on mount
    useEffect(() => {
        fetchMenu().then(() => setIsLoaded(true));
    }, []);

    if (!isLoaded) {
        return <div className="text-center py-20 text-stone-400">Loading Menu Collection...</div>;
    }

    // Cart State
    const [cart, setCart] = useState(() => { 
        const saved = localStorage.getItem('asabana_cart');
        return saved ? JSON.parse(saved) : [];
    });
    
    // Navigation State
    const [activeCategory, setActiveCategory] = useState(() => {
        const hash = window.location.hash.substring(1);
        return hash || (pageType === 'food' ? 'swallow' : (pageType === 'drinks' ? 'alcohol' : 'specials'));
    });
    const [searchQuery, setSearchQuery] = useState('');
    
    // UI State
    const [toast, setToast] = useState(null);
    const [isOpen, setIsOpen] = useState(false);
    const [view, setView] = useState('cart'); // 'cart' or 'checkout'

    // Filtered Data Logic
    const menuItems = useMemo(() => {
        const source = pageType === 'drinks' ? MENU_DATA.drinks : 
                      (pageType === 'food' ? MENU_DATA.food : MENU_DATA.specials);
        
        return source.filter(item => {
            const matchesSearch = item.title.toLowerCase().includes(searchQuery) || 
                                 (item.description && item.description.toLowerCase().includes(searchQuery));
            
            // If searching, ignore category filter to show all matches
            const matchesCategory = searchQuery.length > 0 || activeCategory === 'all' || activeCategory === 'specials' || item.category === activeCategory;
            
            return matchesSearch && matchesCategory;
        });
    }, [searchQuery, activeCategory, isLoaded]); // Added isLoaded to trigger recalculation after fetch

    const categories = useMemo(() => {
        let source = [];
        if (pageType === 'drinks') source = MENU_DATA.drinks;
        else if (pageType === 'food') source = MENU_DATA.food;
        else source = MENU_DATA.specials; // Handle home page specials

        if (!source || source.length === 0) return [];

        const uniqueCats = [...new Set(source.map(i => i.category))];
        return uniqueCats.map(cat => ({
            id: cat,
            name: CATEGORY_NAMES[cat] || cat
        }));
    }, [isLoaded]); // Added isLoaded to trigger recalculation after fetch

    const cartCount = cart.reduce((acc, item) => acc + item.quantity, 0);

    // Effect: Hash Navigation Sync
    useEffect(() => {
        const handleHash = () => {
            const hash = window.location.hash.substring(1);
            if (hash) {
                setActiveCategory(hash);
                // Smooth scroll to section
                const target = document.getElementById(hash);
                if (target && hash !== 'all') {
                    window.scrollTo({
                        top: target.getBoundingClientRect().top + window.scrollY - (totalStickyHeaderHeight + 10),
                        behavior: 'smooth'
                    });
                }
            }
        };

        window.addEventListener('hashchange', handleHash);
        return () => window.removeEventListener('hashchange', handleHash);
    }, []);

    // Effect: Sync with Vanilla Search Input
    useEffect(() => {
        const searchEl = document.getElementById('menuSearch');
        if (!searchEl) return;

        const handler = debounce((e) => {
            setSearchQuery(e.target.value.toLowerCase());
        }, 150);

        searchEl.addEventListener('input', handler);
        return () => searchEl.removeEventListener('input', handler);
    }, []);

    // Effect: Handle Active Link Highlighting (Vanilla)
    useEffect(() => {
        const updateLinks = () => {
            document.querySelectorAll('a[href^="#"]').forEach(link => {
                const href = link.getAttribute('href').substring(1);
                const isActive = activeCategory === href || (activeCategory === 'all' && href === 'all');
                
                if (link.classList.contains('category-chip')) {
                    link.classList.toggle('active-link', isActive);
                    if (isActive) {
                        // Scroll chip into view on mobile
                        link.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }
                }
                
                if (link.closest('.page-sidebar')) {
                    link.classList.toggle('is-current', isActive);
                }
            });
        };
        updateLinks();
    }, [activeCategory]);

    // Effect: Intersection Observer for ScrollSpy
    useEffect(() => {
        if (activeCategory === 'all' || searchQuery) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && entry.intersectionRatio > 0.5) {
                    const id = entry.target.id;
                    if (id !== activeCategory) {
                        setActiveCategory(id);
                        // Silently update hash without triggering scroll
                        history.replaceState(null, null, `#${id}`);
                    }
                }
            });
        }, { threshold: [0.1, 0.5], rootMargin: '-20% 0px -30% 0px' });

        document.querySelectorAll('.menu-section').forEach(s => observer.observe(s));
        return () => observer.disconnect();
    }, [activeCategory, searchQuery]);

    useEffect(() => {
        localStorage.setItem('asabana_cart', JSON.stringify(cart));
        // Update legacy HTML badges
        const badges = document.querySelectorAll('.cart-badge');
        badges.forEach(b => {
            b.textContent = cartCount;
            b.classList.toggle('hidden', cartCount === 0);
        });
    }, [cart, cartCount]);

    useEffect(() => {
        document.body.classList.toggle('cart-open', isOpen);

        if (!isOpen) {
            return undefined;
        }

        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                setIsOpen(false);
            }
        };

        document.addEventListener('keydown', handleEscape);
        return () => {
            document.body.classList.remove('cart-open');
            document.removeEventListener('keydown', handleEscape);
        };
    }, [isOpen]);

    const addToCart = useCallback((item) => {
        setCart(prev => {
            const existing = prev.find(i => i.id === item.id);
            if (existing) {
                return prev.map(i => i.id === item.id ? { ...i, quantity: i.quantity + 1 } : i);
            }
            return [...prev, { ...item, quantity: 1 }];
        });
        
        setToast(`${item.title} added to selection`);
        setTimeout(() => setToast(null), 2500);
    }, []);

    // Intercept clicks on menu items
    useEffect(() => {
        const handleMenuClick = (e) => {
            const addButton = e.target.closest('[data-cart-action="add"]');
            if (!addButton) return;

            const itemEl = addButton.closest('[data-menu-item]');
            if (!itemEl) return;

            const title =
                itemEl.querySelector('.menu-item__title')?.textContent?.trim() ||
                itemEl.querySelector('h3')?.textContent?.trim() ||
                'Menu item';
            const id = itemEl.dataset.itemId || title;
            const priceEl = itemEl.querySelector('.menu-item__price') || itemEl.querySelector('.special-price');
            // Handle price ranges by taking the first price
            const priceText = priceEl ? priceEl.textContent.split('/')[0] : '0';
            const price = parseInt(priceText.replace(/[^0-9]/g, '')) || 0;
            const img = itemEl.querySelector('img')?.src;

            addButton.classList.remove('is-pressed');
            window.requestAnimationFrame(() => addButton.classList.add('is-pressed'));
            window.setTimeout(() => addButton.classList.remove('is-pressed'), 280);

            addToCart({ id, title, price, img });
        };

        document.addEventListener('click', handleMenuClick);
        const trigger = document.getElementById('cartTrigger');
        if (trigger) trigger.onclick = () => {
            setView('cart');
            setIsOpen(true);
        };

        return () => {
            document.removeEventListener('click', handleMenuClick);
            if (trigger) {
                trigger.onclick = null;
            }
        };
    }, [addToCart]);

    const updateQty = (id, delta) => {
        setCart(prev => prev.map(item => 
            item.id === id ? { ...item, quantity: Math.max(0, item.quantity + delta) } : item
        ).filter(item => item.quantity > 0));
    };

    const clearCart = () => {
        if (cart.length > 0 && window.confirm("Are you sure you want to clear your entire selection?")) {
            setCart([]);
        }
    };

    const total = cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);

    const checkout = () => {
        let msg = "Hello Asabana Hotel, I'd like to order:\n\n";
        cart.forEach(item => {
            msg += `• ${item.title} (x${item.quantity}) - ₦${(item.price * item.quantity).toLocaleString()}\n`;
        });
        msg += `\n*Total: ₦${total.toLocaleString()}*`;
        window.location.href = `https://wa.me/2349037102853?text=${encodeURIComponent(msg)}`;
    };

    return (
        <>
            {toast && <div className="cart-toast">{toast}</div>}

            {/* Reactive Menu Rendering */}
            {document.getElementById('menu-root') && ReactDOM.createPortal(
                <div className="category-stack category-stack--compact">
                    {searchQuery && menuItems.length === 0 ? (
                        <div className="text-center py-12">
                            <p className="text-stone-400">No items match your search.</p>
                        </div>
                    ) : (
                        categories.filter(cat => activeCategory === 'all' || cat.id === activeCategory).map(cat => {
                            const itemsInCat = menuItems.filter(i => i.category === cat.id);
                            if (itemsInCat.length === 0) return null;
                            
                            return (
                                <section key={cat.id} id={cat.id} className="menu-section category-card mb-12">
                                    <div className="category-card__header mb-6">
                                        <h3 className="category-card__title">{cat.name}</h3>
                                        <span className={`category-card__tag ${pageType === 'drinks' ? 'category-card__tag--blue' : ''}`}>Selection</span>
                                    </div>
                                    <div className="menu-list">
                                        {itemsInCat.map(item => (
                                            <MenuItem key={item.id} item={item} onAdd={addToCart} isDrinksPage={pageType === 'drinks'} />
                                        ))}
                                    </div>
                                </section>
                            );
                        })
                    )}
                </div>,
                document.getElementById('menu-root')
            )}

            {/* Persistent Floating Cart Button */}
            <button 
                onClick={() => { setView('cart'); setIsOpen(true); }}
                className={`floating-cart-btn ${cartCount > 0 ? 'is-visible' : ''}`}
            >
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                {cartCount > 0 && (
                    <span className="floating-cart-badge cart-bump">
                        {cartCount}
                    </span>
                )}
            </button>

        <div className={`cart-overlay ${isOpen ? 'is-active' : ''}`} onClick={() => setIsOpen(false)}>
            <div className="cart-drawer" onClick={e => e.stopPropagation()}>
                <div className="p-6 border-b border-stone-100 flex justify-between items-center bg-white sticky top-0 z-10">
                    <h2 className="text-xl font-serif font-bold">
                        {view === 'checkout' ? 'Checkout Review' : 'Your Selection'}
                    </h2>
                    <div className="flex items-center gap-4">
                        {cart.length > 0 && (
                            <button 
                                onClick={clearCart}
                                className="text-[10px] font-bold text-red-600 hover:text-red-800 uppercase tracking-tight bg-red-50 px-2 py-1 rounded border border-red-100 transition-colors"
                            >
                                Clear All
                            </button>
                        )}
                        <button onClick={() => setIsOpen(false)} className="text-stone-400 hover:text-stone-900">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div className="flex-grow overflow-y-auto p-6 space-y-6">
                    {cart.length === 0 ? (
                        <div className="text-center py-20 text-stone-400 space-y-3">
                            <p className="text-lg font-medium text-stone-500">Your selection is empty</p>
                            <p className="text-sm">Browse the menu and add items to your order.</p>
                        </div>
                    ) : (
                        cart.map(item => (
                            <div key={item.id} className="flex gap-4 items-center">
                                {item.img && <img src={item.img} className="w-12 h-12 rounded-lg object-cover" />}
                                <div className="flex-grow">
                                    <h4 className="font-semibold text-sm">{item.title}</h4>
                                    <p className="text-amber-700 text-xs font-bold">₦{item.price.toLocaleString()}</p>
                                </div>
                                <div className="flex items-center gap-2 bg-stone-100 rounded-full px-2 py-1">
                                    <button onClick={() => updateQty(item.id, -1)} className="w-6 h-6 flex items-center justify-center text-stone-500">-</button>
                                    <span className="text-xs font-bold w-4 text-center">{item.quantity}</span>
                                    <button onClick={() => updateQty(item.id, 1)} className="w-6 h-6 flex items-center justify-center text-stone-500">+</button>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {cart.length > 0 && (
                    <div className="p-6 border-t border-stone-100 bg-white sticky bottom-0">
                        <div className="flex justify-between items-center mb-4">
                            <span className="text-stone-500 font-medium">Subtotal</span>
                            <span className="text-xl font-bold">₦{total.toLocaleString()}</span>
                        </div>
                        
                        {view === 'cart' ? (
                            <button 
                                onClick={() => setView('checkout')}
                                className="w-full bg-amber-600 hover:bg-amber-700 text-white py-4 rounded-xl font-bold transition-all shadow-lg mb-2"
                            >
                                Proceed to Checkout
                            </button>
                        ) : (
                            <p className="text-xs text-stone-400 mb-4 text-center italic">Review your order before proceeding to WhatsApp for payment.</p>
                        )}

                        <button 
                            onClick={checkout}
                            className="w-full bg-green-600 hover:bg-green-700 text-white py-4 rounded-xl font-bold transition-all shadow-lg shadow-green-100 flex items-center justify-center gap-2"
                        >
                            <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                            Order on WhatsApp
                        </button>
                    </div>
                )}
            </div>
        </div>
        </>
    );
}

const root = ReactDOM.createRoot(document.getElementById('cart-root'));
root.render(<App />);

async function init() {
    applyTimeBasedTheme();
    updateStickyHeaderHeight(); // Calculate sticky heights on init
    handleBackToTopVisibility();
    handleNavVisibility();
}
window.addEventListener('resize', debounce(updateStickyHeaderHeight, 100)); // Recalculate on resize

window.addEventListener('scroll', () => {
    handleBackToTopVisibility();
    handleNavVisibility();
}, { passive: true });

if (backToTopBtn) {
    backToTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}
init();
