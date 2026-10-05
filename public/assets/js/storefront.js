(() => {
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[character]);

    const formatPrice = (value) => {
        const price = Number(value);
        if (!Number.isFinite(price)) return 'Price unavailable';
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: 'BDT',
            maximumFractionDigits: 2,
        }).format(price);
    };

    const safeImageUrl = (value) => {
        if (typeof value !== 'string' || value.length > 2048) return '';
        try {
            const url = new URL(value, window.location.origin);
            return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
        } catch {
            return '';
        }
    };

    const productCard = (product, csrfToken) => {
        const name = escapeHtml(product.name || 'Product');
        const slug = encodeURIComponent(String(product.slug || ''));
        const image = Array.isArray(product.images) ? product.images[0] : null;
        const imageUrl = image ? safeImageUrl(image.image_url) : '';
        const imageMarkup = imageUrl
            ? `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(image.alt_text || product.name || '')}" loading="lazy">`
            : `<span class="product-image-mark" aria-hidden="true">${escapeHtml((product.name || 'H').slice(0, 1).toUpperCase())}</span>`;

        return `<article class="product-card">
            <a href="/product/${slug}" aria-label="View ${name}">
                <div class="product-image">${imageMarkup}</div>
            </a>
            <div class="product-info">
                <p class="product-category">${escapeHtml(product.category_name || product.product_type || 'Product')}</p>
                <h3 class="product-name"><a href="/product/${slug}">${name}</a></h3>
                <p class="product-short">${escapeHtml(product.short_description || '')}</p>
                <p class="product-price">${formatPrice(product.price)}</p>
                <p class="product-stock">${Number(product.has_variants) === 1 ? 'Choose an option to check availability' : Number(product.stock_quantity) > 0 ? 'Available' : 'Currently unavailable'}</p>
                ${Number(product.stock_quantity) > 0 && Number(product.has_variants) !== 1 ? `<form class="product-add-form" action="/cart/items" method="post">
                    <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken || '')}">
                    <input type="hidden" name="product_id" value="${Number(product.id)}">
                    <input type="hidden" name="quantity" value="1">
                    <button class="button button-primary" type="submit">Add to cart</button>
                </form>` : Number(product.has_variants) === 1 ? `<a class="button button-secondary" href="/product/${slug}">Choose options</a>` : ''}
            </div>
        </article>`;
    };

    const showMessage = (container, message, isError = false) => {
        container.innerHTML = `<p class="state-message${isError ? ' is-error' : ''}" role="${isError ? 'alert' : 'status'}">${escapeHtml(message)}</p>`;
    };

    const fetchJson = async (url) => {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const body = await response.json();
        if (!response.ok || body.success !== true) {
            throw new Error(body.error || 'Unable to load products right now.');
        }
        return body;
    };

    document.querySelectorAll('[data-product-list]').forEach((container) => {
        const toolbar = container.parentElement.querySelector('[data-result-count]');
        const pagination = container.parentElement.querySelector('[data-pagination]');
        const sort = container.parentElement.querySelector('[data-sort]');
        const searchForm = container.parentElement.querySelector('[data-catalog-search]');
        const searchInput = searchForm?.querySelector('input[name="q"]');
        const initialParams = new URLSearchParams(window.location.search);
        const pageSize = Number(container.dataset.limit || 9);
        let currentPage = 1;
        let currentProducts = [];
        let totalPages = 1;
        let searchTerm = initialParams.get('q') || '';
        const originalUrl = new URL(window.location.href);
        if (searchInput) searchInput.value = searchTerm;

        const renderProducts = () => {
            const products = [...currentProducts];
            if (sort?.value === 'price-low') products.sort((left, right) => Number(left.price) - Number(right.price));
            if (sort?.value === 'price-high') products.sort((left, right) => Number(right.price) - Number(left.price));

            if (products.length === 0) {
                showMessage(container, 'No products are listed here yet. Please check back soon.');
            } else {
                container.innerHTML = products.map((product) => productCard(product, container.dataset.csrf)).join('');
            }
            if (toolbar) toolbar.textContent = `${products.length} product${products.length === 1 ? '' : 's'} · ${currentPage} of ${totalPages}`;
        };

        const renderPagination = () => {
            if (!pagination) return;
            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            const previous = `<button class="page-button" type="button" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''} aria-label="Previous page">‹</button>`;
            const pages = Array.from({ length: totalPages }, (_, index) => {
                const page = index + 1;
                return `<button class="page-button" type="button" data-page="${page}" ${page === currentPage ? 'aria-current="page"' : ''}>${page}</button>`;
            }).join('');
            const next = `<button class="page-button" type="button" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''} aria-label="Next page">›</button>`;
            pagination.innerHTML = previous + pages + next;
        };

        const loadProducts = async () => {
            showMessage(container, 'Loading products…');
            const params = new URLSearchParams({
                page: String(currentPage),
                per_page: String(pageSize),
            });
            if (container.dataset.category) params.set('category', container.dataset.category);
            if (container.dataset.type) params.set('type', container.dataset.type);
            if (searchTerm) params.set('q', searchTerm);

            try {
                const data = await fetchJson(`${container.dataset.api}?${params}`);
                currentProducts = Array.isArray(data.products) ? data.products : [];
                totalPages = Math.max(1, Number(data.pagination?.last_page) || 1);
                renderProducts();
                renderPagination();
            } catch (error) {
                showMessage(container, error.message || 'Unable to load products right now.', true);
                if (toolbar) toolbar.textContent = 'Catalog unavailable';
                if (pagination) pagination.innerHTML = '';
            }
        };

        pagination?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-page]');
            if (!button || button.disabled) return;
            currentPage = Number(button.dataset.page);
            loadProducts();
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        sort?.addEventListener('change', renderProducts);
        searchForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            searchTerm = searchInput?.value.trim() || '';
            currentPage = 1;
            const url = new URL(originalUrl);
            if (searchTerm) url.searchParams.set('q', searchTerm);
            else url.searchParams.delete('q');
            window.history.replaceState({}, '', url);
            loadProducts();
        });
        loadProducts();
    });

    const detail = document.querySelector('[data-product-detail]');
    if (detail) {
        const renderDetail = (product) => {
            const images = Array.isArray(product.images) ? product.images : [];
            const firstImageUrl = images.length ? safeImageUrl(images[0].image_url) : '';
            const gallery = firstImageUrl
                ? `<img data-main-image src="${escapeHtml(firstImageUrl)}" alt="${escapeHtml(images[0].alt_text || product.name)}">`
                : `<span class="product-image-mark" aria-hidden="true">${escapeHtml((product.name || 'H').slice(0, 1).toUpperCase())}</span>`;
            const thumbnails = images.length > 1
                ? `<div class="detail-thumbnails">${images.map((image, index) => {
                    const url = safeImageUrl(image.image_url);
                    if (!url) return '';
                    return `<button class="detail-thumb" type="button" data-image="${escapeHtml(url)}" data-alt="${escapeHtml(image.alt_text || product.name)}" aria-pressed="${index === 0}">
                        <img src="${escapeHtml(url)}" alt="" loading="lazy">
                    </button>`;
                }).join('')}</div>`
                : '';
            const variants = Array.isArray(product.variants) && product.variants.length
                ? `<section aria-label="Product options"><h2 class="detail-category">Available options</h2><ul class="variant-list">${product.variants.map((variant) => {
                    const attributes = variant.attributes && typeof variant.attributes === 'object'
                        ? Object.entries(variant.attributes).map(([key, value]) => `${escapeHtml(key)}: ${escapeHtml(value)}`).join(' · ')
                        : '';
                    return `<li class="variant-option"><strong>${escapeHtml(variant.name)}</strong><span>${attributes}${variant.price_override ? ` · ${formatPrice(variant.price_override)}` : ''}</span></li>`;
                }).join('')}</ul></section>`
                : '';
            const hasAvailableStock = Array.isArray(product.variants) && product.variants.length
                ? product.variants.some((variant) => Number(variant.stock_quantity) > 0)
                : Number(product.stock_quantity) > 0;
            const variantSelector = Array.isArray(product.variants) && product.variants.length
                ? `<label for="product-variant">Choose an option</label>
                    <select id="product-variant" name="variant_id" form="add-product-form" required>
                        <option value="">Select an option</option>
                        ${product.variants.map((variant) => `<option value="${Number(variant.id)}" data-stock="${Number(variant.stock_quantity)}" data-price="${escapeHtml(variant.price_override ?? product.price)}" ${Number(variant.stock_quantity) < 1 ? 'disabled' : ''}>${escapeHtml(variant.name)} — ${formatPrice(variant.price_override ?? product.price)}${Number(variant.stock_quantity) < 1 ? ' (out of stock)' : ''}</option>`).join('')}
                    </select>`
                : '';

            detail.innerHTML = `<div class="detail-gallery">
                    <div class="detail-main-image">${gallery}</div>
                    ${thumbnails}
                </div>
                <div class="detail-copy">
                    <p class="detail-category">${escapeHtml(product.category_name || product.product_type || 'Product')}</p>
                    <h1 class="detail-title">${escapeHtml(product.name)}</h1>
                    <p class="detail-price" data-base-price="${escapeHtml(product.price)}">${formatPrice(product.price)}</p>
                    <p class="detail-description">${escapeHtml(product.description || product.short_description || 'Product details will be available soon.')}</p>
                    <ul class="detail-meta">
                        <li>Product type:<strong>${escapeHtml(product.product_type || 'General')}</strong></li>
                        <li>Availability:<strong>${hasAvailableStock ? 'In stock' : 'Currently unavailable'}</strong></li>
                    </ul>
                    ${variants}
                    ${hasAvailableStock ? `<form id="add-product-form" class="detail-add-form" action="/cart/items" method="post">
                        <input type="hidden" name="csrf_token" value="${escapeHtml(detail.dataset.csrf || '')}">
                        <input type="hidden" name="product_id" value="${Number(product.id)}">
                        ${variantSelector}
                        <label for="product-quantity">Quantity</label>
                        <input id="product-quantity" name="quantity" type="number" min="1" max="${Math.min(99, Number(product.stock_quantity))}" value="1" required>
                        <button class="button button-primary" type="submit">Add to cart</button>
                    </form>` : '<p class="detail-note">This product is currently unavailable.</p>'}
                    <p class="detail-note">Checkout and payment are the next step. Adding a product does not create an order or charge you.</p>
                </div>`;
        };

        detail.addEventListener('click', (event) => {
            const button = event.target.closest('[data-image]');
            if (!button) return;
            const mainImage = detail.querySelector('[data-main-image]');
            if (!mainImage) return;
            mainImage.src = button.dataset.image;
            mainImage.alt = button.dataset.alt || '';
            detail.querySelectorAll('[data-image]').forEach((thumbnail) => thumbnail.setAttribute('aria-pressed', String(thumbnail === button)));
        });

        detail.addEventListener('change', (event) => {
            if (!event.target.matches('#product-variant')) return;
            const quantity = detail.querySelector('#product-quantity');
            const price = detail.querySelector('.detail-price');
            const selected = event.target.selectedOptions[0];
            const stock = Number(selected.dataset.stock || 0);
            quantity.max = String(Math.min(99, stock || 99));
            quantity.value = '1';
            if (price) price.textContent = formatPrice(selected.value ? selected.dataset.price : price.dataset.basePrice);
        });

        fetchJson(detail.dataset.api)
            .then((data) => renderDetail(data.product))
            .catch((error) => showMessage(detail, error.message || 'Unable to load this product right now.', true));
    }

    const menuToggle = document.querySelector('.menu-toggle');
    const navigation = document.querySelector('#primary-nav');
    menuToggle?.addEventListener('click', () => {
        const expanded = menuToggle.getAttribute('aria-expanded') === 'true';
        menuToggle.setAttribute('aria-expanded', String(!expanded));
        menuToggle.setAttribute('aria-label', expanded ? 'Open navigation' : 'Close navigation');
        navigation?.classList.toggle('is-open', !expanded);
    });
})();
