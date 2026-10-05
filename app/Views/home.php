<?php require __DIR__ . '/partials/head.php'; ?>
<main id="main-content">
    <section class="hero wrap">
        <div class="hero-copy">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Discover your next useful thing</p>
            <h1>Good tools make<br><span>big ideas</span> happen.</h1>
            <p class="hero-description">Learn a new skill, find software, or get a project moving. Explore a growing collection of digital products and security devices in one place.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="/courses">Explore the store <span aria-hidden="true">→</span></a>
                <a class="text-link" href="#shop-sections">Browse categories</a>
            </div>
            <div class="hero-proof">
                <span class="proof-icon" aria-hidden="true">✳</span>
                <span>One store. Five ways to get ahead.</span>
            </div>
        </div>
        <div class="hero-art" aria-label="A selection of learning and technology essentials" role="img">
            <div class="art-orbit orbit-one"></div>
            <div class="art-orbit orbit-two"></div>
            <div class="art-card art-card-main">
                <span class="art-card-label">YOUR NEXT MOVE</span>
                <span class="art-card-title">Start<br>something<br><em>good.</em></span>
                <span class="art-card-spark" aria-hidden="true">✳</span>
            </div>
            <div class="art-chip chip-book"><span aria-hidden="true">▤</span> Learn</div>
            <div class="art-chip chip-code"><span aria-hidden="true">&lt;/&gt;</span> Build</div>
            <div class="art-chip chip-shield"><span aria-hidden="true">◇</span> Protect</div>
            <span class="art-star star-one" aria-hidden="true">✳</span>
            <span class="art-star star-two" aria-hidden="true">✦</span>
        </div>
    </section>

    <section class="section-band" id="shop-sections">
        <div class="wrap section-inner">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Find your category</p>
                    <h2>Explore the store</h2>
                </div>
                <p class="section-intro">Different goals, one simple place to start.</p>
            </div>
            <div class="category-grid">
                <a class="category-card category-course" href="/courses">
                    <span class="category-icon" aria-hidden="true">↗</span>
                    <span class="category-number">01 / LEARN</span>
                    <strong>Courses</strong>
                    <span>Build skills that move you forward.</span>
                    <span class="card-arrow" aria-hidden="true">→</span>
                </a>
                <a class="category-card category-web" href="/websites">
                    <span class="category-icon" aria-hidden="true">▧</span>
                    <span class="category-number">02 / LAUNCH</span>
                    <strong>Ready Websites</strong>
                    <span>Give your next idea a place online.</span>
                    <span class="card-arrow" aria-hidden="true">→</span>
                </a>
                <a class="category-card category-software" href="/software">
                    <span class="category-icon" aria-hidden="true">&lt;/&gt;</span>
                    <span class="category-number">03 / WORK</span>
                    <strong>Official Software</strong>
                    <span>Tools for the work you want to do.</span>
                    <span class="card-arrow" aria-hidden="true">→</span>
                </a>
                <a class="category-card category-books" href="/books">
                    <span class="category-icon" aria-hidden="true">▤</span>
                    <span class="category-number">04 / GROW</span>
                    <strong>Books</strong>
                    <span>Fresh perspectives, one page at a time.</span>
                    <span class="card-arrow" aria-hidden="true">→</span>
                </a>
                <a class="category-card category-devices" href="/devices">
                    <span class="category-icon" aria-hidden="true">◇</span>
                    <span class="category-number">05 / PROTECT</span>
                    <strong>Security Devices</strong>
                    <span>Explore products for a more secure setup.</span>
                    <span class="card-arrow" aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </section>

    <section class="wrap featured-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">A place to begin</p>
                <h2>Explore products</h2>
            </div>
            <a class="text-link" href="/products">View all products <span aria-hidden="true">→</span></a>
        </div>
        <form class="catalog-search home-search" data-catalog-search role="search">
            <label class="visually-hidden" for="home-search-input">Search all products</label>
            <input id="home-search-input" name="q" type="search" maxlength="120" placeholder="What are you looking for?" autocomplete="off">
            <button class="button button-primary" type="submit">Search <span aria-hidden="true">→</span></button>
        </form>
        <div class="product-grid" data-product-list data-api="/api/v1/products" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" data-limit="4">
            <p class="state-message">Loading products…</p>
        </div>
    </section>

    <section class="closing-banner wrap">
        <div>
            <p class="eyebrow">Your next step starts here</p>
            <h2>Make room for what’s next.</h2>
        </div>
        <a class="button button-light" href="/courses">Find your starting point <span aria-hidden="true">→</span></a>
        <span class="banner-decoration" aria-hidden="true">✳</span>
    </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
