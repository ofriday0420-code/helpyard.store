<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/account">My account</a><span aria-hidden="true">/</span><a href="/account/courses">My courses</a><span aria-hidden="true">/</span><span>Course</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Enrolled course</p>
        <h1><?= htmlspecialchars($course['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        <p>Course content is private to customers with a verified paid enrollment.</p>
    </section>
    <?php if ($notice !== ''): ?><p class="cart-feedback" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($course['sections'] === []): ?>
        <p>Course lessons are being prepared. Please check back later.</p>
    <?php else: ?>
        <div class="order-items" aria-label="Course curriculum">
            <?php foreach ($course['sections'] as $section): ?>
                <section class="order-item">
                    <div>
                        <h2><?= htmlspecialchars($section['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
                        <?php foreach ($section['lessons'] as $lesson): ?>
                            <article class="course-lesson">
                                <h3><?= htmlspecialchars($lesson['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                                <div><?= nl2br(htmlspecialchars($lesson['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></div>
                                <?php if ($lesson['completed_at'] !== null): ?>
                                    <p class="checkout-note">Completed <?= htmlspecialchars($lesson['completed_at'], ENT_QUOTES, 'UTF-8') ?> UTC</p>
                                <?php else: ?>
                                    <form method="post" action="/account/courses/<?= (int) $course['id'] ?>/lessons/<?= (int) $lesson['id'] ?>/complete">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="button button-secondary" type="submit">Mark lesson complete</button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
