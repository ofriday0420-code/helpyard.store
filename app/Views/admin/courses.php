<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/admin/fulfillment">Administration</a><span aria-hidden="true">/</span><span>Courses</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Administration</p>
        <h1>Course authoring</h1>
        <p>Create course sections and text lessons, then publish lessons for customers with verified course access. Lesson text is escaped when displayed.</p>
        <div class="admin-actions">
            <a class="button button-secondary" href="/admin/catalog">Manage course products</a>
            <a class="button button-secondary" href="/admin/files">Manage protected files</a>
            <a class="button button-secondary" href="/admin/orders">Order administration</a>
            <a class="button button-secondary" href="/admin/fulfillment">Physical fulfillment</a>
        </div>
    </section>
    <?php if ($notice !== ''): ?><p class="cart-feedback" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="cart-feedback is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>

    <?php if ($productsWithoutCourses !== []): ?>
        <section class="account-panel" aria-labelledby="course-workspace-title">
            <h2 id="course-workspace-title">Set up a course workspace</h2>
            <p>Course products created after the learning tables were installed need a workspace before sections and lessons can be added.</p>
            <?php foreach ($productsWithoutCourses as $product): ?>
                <form class="admin-edit-form" method="post" action="/admin/courses">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                    <p><strong><?= htmlspecialchars($product['product_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> · <?= (int) $product['is_active'] === 1 ? 'active' : 'inactive' ?></p>
                    <button class="button button-primary" type="submit">Set up course</button>
                </form>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="account-orders" aria-labelledby="course-workspaces-title">
        <h2 id="course-workspaces-title">Course workspaces</h2>
        <?php if ($courses === []): ?>
            <p>No course products have been set up yet. Create or activate a course product in the catalog, then set up its workspace here.</p>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
                <section class="admin-product-card" id="course-<?= (int) $course['id'] ?>">
                    <h3><?= htmlspecialchars($course['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                    <p><a href="/product/<?= rawurlencode($course['slug']) ?>">View product</a> · <?= (int) $course['is_active'] === 1 ? 'active product' : 'inactive product' ?></p>
                    <form class="admin-edit-form" method="post" action="/admin/courses/<?= (int) $course['id'] ?>/sections">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <label>New section title <input name="title" maxlength="180" required></label>
                        <button class="button button-secondary" type="submit">Add section</button>
                    </form>

                    <?php if ($course['sections'] === []): ?>
                        <p>No sections yet. Add the course outline to begin authoring lessons.</p>
                    <?php else: ?>
                        <?php foreach ($course['sections'] as $section): ?>
                            <section class="admin-product-card" aria-labelledby="section-<?= (int) $section['id'] ?>-title">
                                <h4 id="section-<?= (int) $section['id'] ?>-title">Section <?= (int) $section['position'] ?>: <?= htmlspecialchars($section['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h4>
                                <?php foreach ($section['lessons'] as $lesson): ?>
                                    <form class="admin-edit-form" method="post" action="/admin/courses/lessons/<?= (int) $lesson['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <label>Lesson title <input name="title" maxlength="180" value="<?= htmlspecialchars($lesson['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required></label>
                                        <label>Lesson text <textarea name="content" rows="6" maxlength="250000" required><?= htmlspecialchars($lesson['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea></label>
                                        <label class="admin-check"><input type="checkbox" name="is_published" value="1" <?= (int) $lesson['is_published'] === 1 ? 'checked' : '' ?>> Published for enrolled customers</label>
                                        <p>Lesson <?= (int) $lesson['position'] ?> · <?= (int) $lesson['is_published'] === 1 ? 'published' : 'draft' ?></p>
                                        <button class="button button-secondary" type="submit">Save lesson</button>
                                    </form>
                                <?php endforeach; ?>
                                <form class="admin-edit-form" method="post" action="/admin/courses/sections/<?= (int) $section['id'] ?>/lessons">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <label>Lesson title <input name="title" maxlength="180" required></label>
                                    <label>Lesson text <textarea name="content" rows="6" maxlength="250000" required></textarea></label>
                                    <label class="admin-check"><input type="checkbox" name="is_published" value="1"> Publish for enrolled customers</label>
                                    <button class="button button-primary" type="submit">Add lesson</button>
                                </form>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
