-- Centrale audit log voor alle belangrijke acties
-- Database: PostgreSQL
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGSERIAL PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    user_id INT NULL,
    username TEXT NULL,
    session_id TEXT NULL,
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id TEXT NULL,
    details JSONB
);

CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log (user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_log_entity ON audit_log (entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log (action);
