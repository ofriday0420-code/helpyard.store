<?php require __DIR__ . '/partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/products">Products</a><span aria-hidden="true">/</span><span>Details</span>
    </nav>
    <section class="product-detail" data-product-detail data-api="/api/v1/products/<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <p class="state-message">Loading product details…</p>
    </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
