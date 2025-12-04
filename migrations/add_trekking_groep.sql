-- Add trekking_groep_id to bons table to track related bonnen
ALTER TABLE bons ADD COLUMN IF NOT EXISTS trekking_groep_id INTEGER;

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS idx_bons_trekking_groep ON bons(trekking_groep_id);
