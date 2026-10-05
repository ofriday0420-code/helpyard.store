<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/account">My account</a><span aria-hidden="true">/</span><span>My courses</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Customer account</p>
        <h1>My courses</h1>
        <p>Courses are available here after payment has been verified.</p>
    </section>
    <?php if ($courses === []): ?>
        <p>No purchased courses are currently available for your account.</p>
        <a class="button button-secondary" href="/courses">Browse courses</a>
    <?php else: ?>
        <div class="order-items" aria-label="Enrolled courses">
            <?php foreach ($courses as $course): ?>
                <article class="order-item">
                    <div>
                        <h2><?= htmlspecialchars($course['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
                        <p><?= (int) $course['completed_count'] ?> of <?= (int) $course['lesson_count'] ?> lessons complete</p>
                    </div>
                    <a class="button button-primary" href="/account/courses/<?= (int) $course['id'] ?>">Open course</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
