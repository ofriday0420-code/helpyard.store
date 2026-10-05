<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/account">My account</a><span aria-hidden="true">/</span><span>Downloads</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Customer account</p>
        <h1>My downloads</h1>
        <p>Private files from verified, paid orders are available here.</p>
    </section>
    <?php if ($downloads === []): ?>
        <p>No downloadable files are currently available for your account.</p>
    <?php else: ?>
        <div class="order-items" aria-label="Available downloads">
            <?php foreach ($downloads as $download): ?>
                <article class="order-item">
                    <div>
                        <h2><?= htmlspecialchars($download['product_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
                        <p><?= htmlspecialchars($download['download_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <p>Order <?= htmlspecialchars($download['order_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    </div>
                    <a class="button button-primary" href="/account/downloads/<?= (int) $download['entitlement_id'] ?>">Download securely</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
