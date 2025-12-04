CREATE TABLE IF NOT EXISTS bon_logs (
    id SERIAL PRIMARY KEY,
    bon_id INTEGER NOT NULL REFERENCES bons(id) ON DELETE CASCADE,
    action VARCHAR(50) NOT NULL,
    user_name VARCHAR(255) DEFAULT 'unknown',
    details TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
