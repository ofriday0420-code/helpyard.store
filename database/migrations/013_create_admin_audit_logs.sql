CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(80) NOT NULL,
    subject_type VARCHAR(80) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    details JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_audit_actor_created (actor_user_id, created_at),
    INDEX idx_admin_audit_subject (subject_type, subject_id, id),
    CONSTRAINT fk_admin_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
