-- ==================
-- Store input WKTs
-- ==================
CREATE TABLE inputwkts (
	identifier		VARCHAR(250) PRIMARY KEY
);

-- ============================================================
-- GEOMETRY COLUMNS
-- ============================================================
-- =====
select AddGeometryColumn(
	'inputwkts',
	'footprint',
	'4326',
	'POLYGON',
	2
);
