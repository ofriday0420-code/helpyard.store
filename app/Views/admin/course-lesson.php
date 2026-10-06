<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/admin/courses">Course authoring</a><span aria-hidden="true">/</span><span>Edit lesson</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Course authoring</p>
        <h1>Edit lesson</h1>
        <p><?= htmlspecialchars($lesson['course_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= htmlspecialchars($lesson['section_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · lesson <?= (int) $lesson['position'] ?></p>
    </section>
    <section class="account-panel" aria-labelledby="lesson-editor-title">
        <h2 id="lesson-editor-title">Lesson content</h2>
        <p>Text is displayed as plain text to customers. Publishing makes it visible to customers with verified course access.</p>
        <form class="auth-form" method="post" action="/admin/courses/lessons/<?= (int) $lesson['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="lesson-title">Title</label>
            <input id="lesson-title" name="title" maxlength="180" value="<?= htmlspecialchars($lesson['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
            <label for="lesson-content">Lesson text</label>
            <textarea id="lesson-content" name="content" rows="18" maxlength="250000" required><?= htmlspecialchars($lesson['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
            <label class="admin-check"><input type="checkbox" name="is_published" value="1" <?= (int) $lesson['is_published'] === 1 ? 'checked' : '' ?>> Published for enrolled customers</label>
            <button class="button button-primary" type="submit">Save lesson</button>
            <a class="button button-secondary" href="/admin/courses#course-<?= (int) $lesson['course_id'] ?>">Cancel</a>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
