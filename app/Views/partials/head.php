<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#f7f8f5">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Helpyard.store</title>
    <?php if (!empty($canonicalUrl)): ?>
        <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <meta property="og:type" content="<?= isset($productStructuredData) ? 'product' : 'website' ?>">
        <meta property="og:title" content="<?= htmlspecialchars($title . ' | Helpyard.store', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <meta property="og:description" content="<?= htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <?php if (!empty($productStructuredData['image'][0])): ?>
            <meta property="og:image" content="<?= htmlspecialchars($productStructuredData['image'][0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($productStructuredData)): ?>
        <script type="application/ld+json"><?= json_encode($productStructuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/storefront.css">
    <script src="/assets/js/storefront.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <?php require __DIR__ . '/header.php'; ?>
