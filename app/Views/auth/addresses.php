<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/account">My account</a><span aria-hidden="true">/</span><span>Addresses</span>
    </nav>
    <section class="account-panel">
        <div>
            <p class="eyebrow">Customer account</p>
            <h1>Your addresses</h1>
            <p class="auth-intro">Manage addresses saved to your account. Only you can view or remove them.</p>
        </div>
        <?php if ($notice !== ''): ?>
            <p class="auth-success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="auth-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="saved-addresses">
            <?php if ($addresses === []): ?>
                <p class="state-message">No saved addresses yet.</p>
            <?php else: ?>
                <?php foreach ($addresses as $address): ?>
                    <article class="saved-address">
                        <div>
                            <strong><?= htmlspecialchars($address['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                            <?php if ((int) $address['is_default'] === 1): ?><span class="default-badge">Default</span><?php endif; ?>
                            <p><?= htmlspecialchars($address['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                            <p><?= htmlspecialchars($address['address_line_1'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $address['address_line_2'] !== null && $address['address_line_2'] !== '' ? ', ' . htmlspecialchars($address['address_line_2'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?></p>
                            <p><?= htmlspecialchars($address['city'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $address['postal_code'] !== null && $address['postal_code'] !== '' ? ' ' . htmlspecialchars($address['postal_code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?>, <?= htmlspecialchars($address['country'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        </div>
                        <form method="post" action="/account/addresses/<?= (int) $address['id'] ?>/delete">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="text-button" type="submit">Remove</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <section class="address-form-section">
            <h2>Add an address</h2>
            <form class="auth-form address-form" method="post" action="/account/addresses">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label for="address-name">Full name</label><input id="address-name" name="full_name" maxlength="120" autocomplete="name" required>
                <label for="address-phone">Phone</label><input id="address-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" required>
                <label for="address-line-1">Address line 1</label><input id="address-line-1" name="address_line_1" maxlength="255" autocomplete="address-line1" required>
                <label for="address-line-2">Address line 2 <span class="optional-label">(optional)</span></label><input id="address-line-2" name="address_line_2" maxlength="255" autocomplete="address-line2">
                <div class="address-fields">
                    <div><label for="address-city">City</label><input id="address-city" name="city" maxlength="80" autocomplete="address-level2" required></div>
                    <div><label for="address-postal">Postal code <span class="optional-label">(optional)</span></label><input id="address-postal" name="postal_code" maxlength="20" autocomplete="postal-code"></div>
                </div>
                <label for="address-country">Country</label><input id="address-country" name="country" maxlength="80" autocomplete="country-name" required>
                <label class="checkbox-row"><input type="checkbox" name="is_default" value="1"> Set as default address</label>
                <button class="button button-primary auth-submit" type="submit">Save address <span aria-hidden="true">→</span></button>
            </form>
        </section>
        <a class="text-link" href="/account">← Back to account</a>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
