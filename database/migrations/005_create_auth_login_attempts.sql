CREATE TABLE IF NOT EXISTS auth_login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier_hash CHAR(64) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auth_attempts_identifier_time (identifier_hash, attempted_at),
    INDEX idx_auth_attempts_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
