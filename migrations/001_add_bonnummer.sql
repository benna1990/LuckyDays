-- Migratie 001: Bonnummer toevoegen aan bons tabel
-- Datum: 2025-11-27

ALTER TABLE bons ADD COLUMN IF NOT EXISTS bonnummer VARCHAR(50) NULL;

CREATE INDEX IF NOT EXISTS idx_bons_bonnummer ON bons(bonnummer);

COMMENT ON COLUMN bons.bonnummer IS 'Optioneel bonnummer, NULL of "0" betekent geen bonnummer';



