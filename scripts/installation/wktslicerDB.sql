-- ==================
-- Store input WKTs
-- ==================
CREATE TABLE inputwkts (
	identifier		VARCHAR(250) PRIMARY KEY
);

-- ============================================================
-- GEOMETRY COLUMNS
-- ============================================================
SELECT AddGeometryColumn('inputwkts','footprint','4326','POLYGON',2);
CREATE INDEX inputwkts_geometry_idx ON inputwkts USING GIST(footprint);

-- ==================
-- Store output WKTs
-- ==================
CREATE TABLE outputwkts (
        identifier          SERIAL PRIMARY KEY,
	i_identifier        VARCHAR(250)
);

-- ============================================================
-- GEOMETRY COLUMNS
-- ============================================================
-- =====
SELECT AddGeometryColumn('outputwkts','footprint','4326','POLYGON',2);
CREATE INDEX outputwkts_geometry_idx ON outputwkts USING GIST(footprint);