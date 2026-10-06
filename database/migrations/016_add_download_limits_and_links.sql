ALTER TABLE download_entitlements
    ADD COLUMN download_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER granted_at,
    ADD COLUMN max_downloads INT UNSIGNED NOT NULL DEFAULT 5 AFTER download_count;

CREATE TABLE download_link_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entitlement_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX uq_download_link_tokens_hash (token_hash),
    INDEX idx_download_link_tokens_entitlement_expiry (entitlement_id, expires_at),
    INDEX idx_download_link_tokens_expiry (expires_at),
    CONSTRAINT fk_download_link_tokens_entitlement
        FOREIGN KEY (entitlement_id) REFERENCES download_entitlements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
