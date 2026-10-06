<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/admin/fulfillment">Administration</a><span aria-hidden="true">/</span><span>Private files</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Administration</p>
        <h1>Private product files</h1>
        <p>Files are stored outside the public web directory. Uploading grants access to existing customers with eligible paid orders; revoking removes that access.</p>
        <div class="admin-actions">
            <a class="button button-secondary" href="/admin/orders">Order administration</a>
            <a class="button button-secondary" href="/admin/fulfillment">Physical fulfillment</a>
            <a class="button button-secondary" href="/admin/catalog">Manage catalog</a>
            <a class="button button-secondary" href="/admin/courses">Author course lessons</a>
        </div>
    </section>
    <?php if ($notice !== ''): ?><p class="cart-feedback" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="cart-feedback is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
    <section class="account-panel" aria-labelledby="upload-private-file-title">
        <h2 id="upload-private-file-title">Add a protected file</h2>
        <p>Allowed formats: PDF, ZIP, EPUB, and MP4. Maximum size: 50 MB. Uploading files replaces no existing files; revoke old versions after the replacement is ready.</p>
        <?php if ($products === []): ?>
            <p>No downloadable products are configured.</p>
        <?php else: ?>
            <form class="auth-form" method="post" action="/admin/files" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label for="private-file-product">Product</label>
                <select id="private-file-product" name="product_id" required>
                    <option value="">Choose a product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars($product['product_type'], ENT_QUOTES, 'UTF-8') ?>)</option>
                    <?php endforeach; ?>
                </select>
                <label for="private-file-asset">File</label>
                <input id="private-file-asset" name="asset" type="file" accept=".pdf,.zip,.epub,.mp4" required>
                <button class="button button-primary" type="submit">Upload privately</button>
            </form>
        <?php endif; ?>
    </section>
    <section class="account-orders" aria-labelledby="private-file-list-title">
        <h2 id="private-file-list-title">Product files</h2>
        <?php if ($products === []): ?>
            <p>No downloadable products are configured.</p>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <section class="order-item">
                    <div>
                        <h3><?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                        <?php if ($product['files'] === []): ?>
                            <p>No files uploaded.</p>
                        <?php else: ?>
                            <?php foreach ($product['files'] as $file): ?>
                                <article class="course-lesson">
                                    <p>
                                        <?= htmlspecialchars($file['download_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                        · <?= $file['is_active'] ? 'Active' : 'Revoked' ?>
                                    </p>
                                    <?php if ($file['is_active']): ?>
                                        <form method="post" action="/admin/files/<?= (int) $file['id'] ?>/revoke">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <button class="button button-secondary" type="submit">Revoke customer access</button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
