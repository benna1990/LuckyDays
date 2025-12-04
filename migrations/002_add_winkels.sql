-- Migratie 002: Winkels systeem
-- Datum: 2025-11-27

-- Winkels tabel aanmaken
CREATE TABLE IF NOT EXISTS winkels (
    id SERIAL PRIMARY KEY,
    naam VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Standaard winkels toevoegen
INSERT INTO winkels (naam) VALUES 
    ('Dapper'), 
    ('Banne'), 
    ('Plein'), 
    ('Jordy')
ON CONFLICT (naam) DO NOTHING;

-- Winkel_id toevoegen aan players
ALTER TABLE players ADD COLUMN IF NOT EXISTS winkel_id INT REFERENCES winkels(id);

-- Winkel_id toevoegen aan bons
ALTER TABLE bons ADD COLUMN IF NOT EXISTS winkel_id INT REFERENCES winkels(id);

-- Indexes voor performance
CREATE INDEX IF NOT EXISTS idx_players_winkel ON players(winkel_id);
CREATE INDEX IF NOT EXISTS idx_bons_winkel ON bons(winkel_id);

-- Default winkel toewijzen aan bestaande data (Dapper = 1)
UPDATE players SET winkel_id = 1 WHERE winkel_id IS NULL;
UPDATE bons SET winkel_id = 1 WHERE winkel_id IS NULL;

COMMENT ON TABLE winkels IS 'Verschillende winkellocaties';
COMMENT ON COLUMN players.winkel_id IS 'Speler behoort tot deze winkel';
COMMENT ON COLUMN bons.winkel_id IS 'Bon is voor deze winkel';



