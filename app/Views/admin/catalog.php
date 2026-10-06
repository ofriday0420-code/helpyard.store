<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/admin/fulfillment">Administration</a><span aria-hidden="true">/</span><span>Catalog</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Administration</p>
        <h1>Catalog management</h1>
        <div class="admin-actions">
            <a class="button button-secondary" href="/admin/orders">Order administration</a>
            <a class="button button-secondary" href="/admin/fulfillment">Physical fulfillment</a>
            <a class="button button-secondary" href="/admin/files">Manage private product files</a>
            <a class="button button-secondary" href="/admin/courses">Author course lessons</a>
        </div>
        <p>Create and maintain categories, product listings, prices, and inventory. Product types cannot be changed after creation so paid-order records remain consistent.</p>
    </section>
    <?php if ($notice !== ''): ?><p class="cart-feedback" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="cart-feedback is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>

    <section class="account-panel" aria-labelledby="category-create-title">
        <h2 id="category-create-title">Create a category</h2>
        <form class="admin-edit-form" method="post" action="/admin/catalog/categories">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>Category name <input name="name" maxlength="120" required></label>
            <label>URL slug <input name="slug" maxlength="140" placeholder="generated-from-name" pattern="[a-z0-9]+(-[a-z0-9]+)*"></label>
            <button class="button button-primary" type="submit">Add category</button>
        </form>
    </section>

    <section class="account-orders" aria-labelledby="category-list-title">
        <h2 id="category-list-title">Categories</h2>
        <?php if ($categories === []): ?>
            <p>No categories are configured.</p>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <form class="admin-edit-form admin-entity-form" method="post" action="/admin/catalog/categories/<?= (int) $category['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label>Name <input name="name" maxlength="120" required value="<?= htmlspecialchars($category['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label>
                    <label>Slug <input name="slug" maxlength="140" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= htmlspecialchars($category['slug'], ENT_QUOTES, 'UTF-8') ?>"></label>
                    <label class="admin-check"><input name="is_active" type="checkbox" value="1" <?= (int) $category['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
                    <span><?= (int) $category['active_product_count'] ?> active product(s)</span>
                    <button class="button button-secondary" type="submit">Save category</button>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="account-panel" aria-labelledby="product-create-title">
        <h2 id="product-create-title">Create a product</h2>
        <form class="admin-edit-form" method="post" action="/admin/catalog/products">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>Product name <input name="name" maxlength="180" required></label>
            <label>URL slug <input name="slug" maxlength="180" placeholder="generated-from-name" pattern="[a-z0-9]+(-[a-z0-9]+)*"></label>
            <label>Type
                <select name="product_type" required>
                    <?php foreach ($productTypes as $productType): ?>
                        <option value="<?= htmlspecialchars($productType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($productType), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Category
                <select name="category_id">
                    <option value="">Uncategorized</option>
                    <?php foreach ($activeCategories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars($category['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Price (BDT) <input name="price" type="number" min="0" max="99999999.99" step="0.01" required value="0.00"></label>
            <label>Compare price (BDT) <input name="compare_price" type="number" min="0.01" max="99999999.99" step="0.01"></label>
            <label>Stock quantity <input name="stock_quantity" type="number" min="0" max="2147483647" step="1" required value="0"></label>
            <label>Short description <textarea name="short_description" maxlength="1000" rows="2"></textarea></label>
            <label>Description <textarea name="description" maxlength="20000" rows="5"></textarea></label>
            <label class="admin-check"><input name="is_active" type="checkbox" value="1" checked> Active in storefront</label>
            <button class="button button-primary" type="submit">Create product</button>
        </form>
    </section>

    <section class="account-orders" aria-labelledby="product-list-title">
        <h2 id="product-list-title">Products <small>(latest 200)</small></h2>
        <?php if ($products === []): ?>
            <p>No products are configured.</p>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <article class="admin-product-card">
                    <h3><?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars($product['product_type'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) $product['is_active'] === 1 ? 'Active' : 'Hidden' ?> · <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <form class="admin-edit-form" method="post" action="/admin/catalog/products/<?= (int) $product['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="product_type" value="<?= htmlspecialchars($product['product_type'], ENT_QUOTES, 'UTF-8') ?>">
                        <label>Name <input name="name" maxlength="180" required value="<?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label>
                        <label>Slug <input name="slug" maxlength="180" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= htmlspecialchars($product['slug'], ENT_QUOTES, 'UTF-8') ?>"></label>
                        <label>Category
                            <select name="category_id">
                                <option value="">Uncategorized</option>
                                <?php foreach ($activeCategories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= (int) $product['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                                <?php if ($product['category_id'] !== null && (int) $product['category_is_active'] !== 1): ?>
                                    <option value="<?= (int) $product['category_id'] ?>" selected><?= htmlspecialchars($product['category_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (inactive)</option>
                                <?php endif; ?>
                            </select>
                        </label>
                        <label>Price (BDT) <input name="price" type="number" min="0" max="99999999.99" step="0.01" required value="<?= htmlspecialchars((string) $product['price'], ENT_QUOTES, 'UTF-8') ?>"></label>
                        <label>Compare price (BDT) <input name="compare_price" type="number" min="0.01" max="99999999.99" step="0.01" value="<?= htmlspecialchars((string) ($product['compare_price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                        <?php if ((int) $product['active_variant_count'] === 0): ?>
                            <label>Stock quantity <input name="stock_quantity" type="number" min="0" max="2147483647" step="1" required value="<?= (int) $product['stock_quantity'] ?>"></label>
                        <?php else: ?>
                            <input type="hidden" name="stock_quantity" value="<?= (int) $product['stock_quantity'] ?>">
                            <p>Stock is tracked by the product options below.</p>
                        <?php endif; ?>
                        <label>Short description <textarea name="short_description" maxlength="1000" rows="2"><?= htmlspecialchars((string) $product['short_description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea></label>
                        <label>Description <textarea name="description" maxlength="20000" rows="4"><?= htmlspecialchars((string) $product['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea></label>
                        <label class="admin-check"><input name="is_active" type="checkbox" value="1" <?= (int) $product['is_active'] === 1 ? 'checked' : '' ?>> Active in storefront</label>
                        <button class="button button-secondary" type="submit">Save product</button>
                    </form>
                    <p><a class="button button-secondary" href="/admin/catalog/products/<?= (int) $product['id'] ?>/images">Manage product images</a></p>
                    <?php if ($product['variants'] !== []): ?>
                        <h4>Product option inventory</h4>
                        <?php foreach ($product['variants'] as $variant): ?>
                            <form class="admin-edit-form admin-entity-form" method="post" action="/admin/catalog/variants/<?= (int) $variant['id'] ?>/stock">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <span><?= htmlspecialchars($variant['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $variant['sku'] !== null ? ' · ' . htmlspecialchars($variant['sku'], ENT_QUOTES, 'UTF-8') : '' ?><?= (int) $variant['is_active'] === 1 ? '' : ' · Disabled' ?></span>
                                <label>Stock <input name="stock_quantity" type="number" min="0" max="2147483647" step="1" required value="<?= (int) $variant['stock_quantity'] ?>"></label>
                                <button class="button button-secondary" type="submit">Update stock</button>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
