<?php require __DIR__ . '/partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><span><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Explore Helpyard.store</p>
        <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <form class="catalog-search" data-catalog-search role="search">
        <label class="visually-hidden" for="catalog-search-input">Search products</label>
        <input id="catalog-search-input" name="q" type="search" maxlength="120" placeholder="Search this collection…" autocomplete="off">
        <button class="button button-primary" type="submit">Search <span aria-hidden="true">→</span></button>
    </form>
    <section class="catalog-toolbar" aria-label="Catalog controls">
        <p data-result-count>Browse products selected for you</p>
        <label class="sort-control">
            <span>Sort</span>
            <select data-sort aria-label="Sort products">
                <option value="newest">Newest</option>
                <option value="price-low">Price: low to high</option>
                <option value="price-high">Price: high to low</option>
            </select>
        </label>
    </section>
    <div class="product-grid" data-product-list data-api="/api/v1/products" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" data-category="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>" data-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
        <p class="state-message">Loading products…</p>
    </div>
    <nav class="pagination" data-pagination aria-label="Product pages"></nav>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
