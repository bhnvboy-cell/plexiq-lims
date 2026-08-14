-- PlexiQ LIMS - Analysis Parameter Management & Instrument Mapping
-- Canonical parameter definitions, per-sample/per-batch assignments,
-- and instrument column-to-parameter mappings.
-- Idempotent: safe to re-run.

CREATE TABLE IF NOT EXISTS analysis_parameters (
    id                SERIAL PRIMARY KEY,
    parameter_code    VARCHAR(50) UNIQUE NOT NULL,
    parameter_name    VARCHAR(255) NOT NULL,
    unit              VARCHAR(50),
    category          VARCHAR(100) DEFAULT 'General',
    data_type         VARCHAR(20) NOT NULL DEFAULT 'numeric'
                      CHECK (data_type IN ('numeric','text','boolean')),
    decimal_places    INTEGER NOT NULL DEFAULT 2,
    spec_min          NUMERIC(18,6),
    spec_max          NUMERIC(18,6),
    spec_target       NUMERIC(18,6),
    specification_text VARCHAR(255),
    method            TEXT,
    is_active         BOOLEAN NOT NULL DEFAULT TRUE,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sample_analysis_parameters (
    id                SERIAL PRIMARY KEY,
    sample_id         INTEGER NOT NULL REFERENCES samples(id) ON DELETE CASCADE,
    parameter_id      INTEGER NOT NULL REFERENCES analysis_parameters(id),
    spec_min          NUMERIC(18,6),
    spec_max          NUMERIC(18,6),
    spec_target       NUMERIC(18,6),
    unit              VARCHAR(50),
    result_value      NUMERIC(18,6),
    result_text       TEXT,
    is_within_spec    BOOLEAN,
    status            VARCHAR(20) NOT NULL DEFAULT 'Pending'
                      CHECK (status IN ('Pending','In Progress','Completed','Reviewed','Approved','Rejected')),
    analyst_notes     TEXT,
    entered_by        INTEGER REFERENCES users(id),
    entered_at        TIMESTAMP,
    reviewed_by       INTEGER REFERENCES users(id),
    reviewed_at       TIMESTAMP,
    approved_by       INTEGER REFERENCES users(id),
    approved_at       TIMESTAMP,
    source            VARCHAR(20) NOT NULL DEFAULT 'manual'
                      CHECK (source IN ('manual','instrument')),
    instrument_result_id INTEGER,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (sample_id, parameter_id)
);

CREATE TABLE IF NOT EXISTS batch_analysis_parameters (
    id                SERIAL PRIMARY KEY,
    batch_number      VARCHAR(100) NOT NULL,
    product_id        INTEGER REFERENCES products(id),
    parameter_id      INTEGER NOT NULL REFERENCES analysis_parameters(id),
    spec_min          NUMERIC(18,6),
    spec_max          NUMERIC(18,6),
    spec_target       NUMERIC(18,6),
    unit              VARCHAR(50),
    is_active         BOOLEAN NOT NULL DEFAULT TRUE,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (batch_number, parameter_id)
);

CREATE TABLE IF NOT EXISTS instrument_parameter_mapping (
    id                SERIAL PRIMARY KEY,
    instrument_id     INTEGER NOT NULL REFERENCES instruments(id) ON DELETE CASCADE,
    source_column     VARCHAR(100) NOT NULL,
    parameter_id      INTEGER NOT NULL REFERENCES analysis_parameters(id),
    conversion_factor NUMERIC(18,6) NOT NULL DEFAULT 1,
    unit              VARCHAR(50),
    is_active         BOOLEAN NOT NULL DEFAULT TRUE,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (instrument_id, source_column)
);

-- Track source file per imported result for deduplication + audit.
ALTER TABLE instrument_results ADD COLUMN IF NOT EXISTS source_file VARCHAR(500);
ALTER TABLE instrument_results ADD COLUMN IF NOT EXISTS mapping_note TEXT;

-- Seed parameter definitions from the existing tests master data.
INSERT INTO analysis_parameters
    (parameter_code, parameter_name, unit, spec_min, spec_max, spec_target, specification_text, method)
SELECT
    t.test_code,
    t.test_name,
    u.unit_code,
    t.min_spec_limit,
    t.max_spec_limit,
    NULL,
    t.spec_limit_text,
    m.method_code
FROM tests t
LEFT JOIN units u  ON t.unit_id = u.id
LEFT JOIN methods m ON t.method_id = m.id
WHERE t.is_active = TRUE
ON CONFLICT (parameter_code) DO NOTHING;

CREATE INDEX IF NOT EXISTS idx_sap_sample    ON sample_analysis_parameters(sample_id);
CREATE INDEX IF NOT EXISTS idx_sap_parameter ON sample_analysis_parameters(parameter_id);
CREATE INDEX IF NOT EXISTS idx_sap_status    ON sample_analysis_parameters(status);
CREATE INDEX IF NOT EXISTS idx_bap_batch     ON batch_analysis_parameters(batch_number);
CREATE INDEX IF NOT EXISTS idx_imp_instrument ON instrument_parameter_mapping(instrument_id);
CREATE INDEX IF NOT EXISTS idx_inst_results_source_file ON instrument_results(source_file);
