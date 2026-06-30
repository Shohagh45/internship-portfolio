-- Per-tenant FAQ store. Embeddings are pre-computed and cached as JSON together
-- with their vector norm, so request-time scoring is a cheap dot product.
CREATE TABLE IF NOT EXISTS faqs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id  BIGINT UNSIGNED NOT NULL,
    question   TEXT NOT NULL,
    answer     TEXT NOT NULL,
    tags       VARCHAR(255) DEFAULT NULL,
    lang       CHAR(2) DEFAULT NULL,        -- 'nl' | 'en' | NULL (any)
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    embedding  JSON NOT NULL,               -- pre-computed question embedding
    norm       DOUBLE NOT NULL,             -- ||embedding||, stored to avoid recomputing
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_active (tenant_id, is_active),
    INDEX idx_tenant_lang (tenant_id, lang)
);

-- Questions the bot could not confidently answer — a backlog for improving the
-- FAQ set.
CREATE TABLE IF NOT EXISTS unanswered_queries (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id  BIGINT UNSIGNED NOT NULL,
    query      TEXT NOT NULL,
    score      DOUBLE DEFAULT NULL,         -- best similarity score at miss time
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_created (tenant_id, created_at)
);
