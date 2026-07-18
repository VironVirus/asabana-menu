(() => {
    'use strict';

    const modal = document.querySelector('[data-item-modal]');
    const form = modal?.querySelector('form');
    const openButton = document.querySelector('[data-open-item-modal]');
    const closeButtons = [...document.querySelectorAll('[data-close-item-modal]')];
    const editButtons = [...document.querySelectorAll('[data-edit-item]')];
    const deleteForms = [...document.querySelectorAll('[data-delete-form]')];
    const search = document.querySelector('[data-admin-search]');
    const items = [...document.querySelectorAll('[data-admin-item]')];
    const noResults = document.querySelector('[data-admin-no-results]');
    let lastFocused = null;

    const fields = {
        id: form?.querySelector('[data-field-id]'),
        title: form?.querySelector('[data-field-title]'),
        price: form?.querySelector('[data-field-price]'),
        category: form?.querySelector('[data-field-category]'),
        description: form?.querySelector('[data-field-description]'),
        order: form?.querySelector('[data-field-order]'),
        available: form?.querySelector('[data-field-available]'),
        special: form?.querySelector('[data-field-special]'),
        image: form?.querySelector('[data-field-image]'),
    };
    const modalTitle = modal?.querySelector('[data-modal-title]');
    const previewWrap = modal?.querySelector('[data-image-preview-wrap]');
    const preview = modal?.querySelector('[data-image-preview]');

    const closeModal = () => {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('modal-open');
        if (lastFocused instanceof HTMLElement) lastFocused.focus();
    };

    const showModal = (button = null) => {
        if (!modal || !form) return;
        lastFocused = button || document.activeElement;
        form.reset();
        fields.id.value = '';
        fields.order.value = '0';
        fields.available.checked = true;
        previewWrap.hidden = true;
        preview.removeAttribute('src');

        if (button) {
            modalTitle.textContent = 'Edit menu item';
            fields.id.value = button.dataset.id || '';
            fields.title.value = button.dataset.title || '';
            fields.price.value = button.dataset.price || '';
            fields.category.value = button.dataset.category || 'swallow';
            fields.description.value = button.dataset.description || '';
            fields.order.value = button.dataset.order || '0';
            fields.available.checked = button.dataset.available === '1';
            fields.special.checked = button.dataset.special === '1';

            if (button.dataset.image) {
                preview.src = `../${button.dataset.image.split('/').map(encodeURIComponent).join('/')}`;
                previewWrap.hidden = false;
            }
        } else {
            modalTitle.textContent = 'Add menu item';
        }

        modal.hidden = false;
        document.body.classList.add('modal-open');
        fields.title.focus();
    };

    openButton?.addEventListener('click', () => showModal());
    editButtons.forEach(button => button.addEventListener('click', () => showModal(button)));
    closeButtons.forEach(button => button.addEventListener('click', closeModal));
    modal?.addEventListener('click', event => {
        if (event.target === modal) closeModal();
    });

    fields.image?.addEventListener('change', () => {
        const file = fields.image.files?.[0];
        if (!file) return;
        preview.src = URL.createObjectURL(file);
        previewWrap.hidden = false;
    });

    deleteForms.forEach(deleteForm => {
        deleteForm.addEventListener('submit', event => {
            if (!window.confirm('Delete this menu item and its uploaded image? This cannot be undone.')) {
                event.preventDefault();
            }
        });
    });

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLowerCase();
        let visible = 0;

        items.forEach(item => {
            const matches = query === '' || (item.dataset.search || '').includes(query);
            item.hidden = !matches;
            if (matches) visible += 1;
        });

        if (noResults) noResults.hidden = visible !== 0;
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
    });
})();

