#!/bin/bash
##################################################
#
# WKT slicer - cut a polygon into slices or parts to speed up database indexin
#
# Author	:	Jerome Gasperi
# Date		:	2013.07.05
#
##################################################

if [ $# -ne 1 ]
then
	echo ""
	echo " Usage : $0 [DATA_FOLDER]"
	echo ""
	echo "   [DATA_FOLDER] contains a list of XML SIPAD data files"
	echo ""
	exit
fi
# WKTSLICER_HOME is current directory
export WKTSLICER_HOME=`pwd`
export DATA_FOLDER=$1

# ---------------------------------------------------------------------------------- 
# 1. Database installation
# ----------------------------------------------------------------------------------
echo "Drop and create clean wktslicer database"
cd $WKTSLICER_HOME/scripts/installation
echo " -> Create empty splitpolygon database"
./wktslicerInstallDB.sh -F -d /usr/local/pgsql/share/contrib/postgis-1.5/
echo " Done !"
echo ""

# ---------------------------------------------------------------------------------- 
# 2. Ingest SIPAD XML data within inputwkts database
# ----------------------------------------------------------------------------------
echo "Ingest $DATA_FOLDER/*.xml files within splitpolygon database (i.e. inputwkts table)"
cd $WKTSLICER_HOME/scripts
php wktslicerIngestXML.php -f $DATA_FOLDER

# ---------------------------------------------------------------------------------- 
# 3. Split SIPAD big polygons into smaller polygon based on a 10x10 square degrees grid
# ----------------------------------------------------------------------------------
echo "Cut SIPAD big polygons within a -70/+70 degrees latitude box"
php wktslicerSplice.php -i ALL -t cut > /tmp/cut.csv

echo "Split SIPAD big polygons into 10 degrees latitude slices and store to database"
php wktslicerSlice.php -i ALL -a -t slice

# ---------------------------------------------------------------------------------- 
# 4. Performances test
# ----------------------------------------------------------------------------------

# Get intersecting polygons on small area (Toulouse, France) 
#     POLYGON((1.1013793945311965 43.52751299421623,1.7605590820312442 43.52751299421623,1.7605590820312442 43.68636569542979,1.1013793945311965 43.68636569542979,1.1013793945311965 43.52751299421623))

psql -U postgres -d wktslicer << EOF
-- Direct query on inputwkts
EXPLAIN ANALYSE SELECT count(identifier) FROM inputwkts WHERE ST_intersects(footprint, GeomFromText('POLYGON((1.1013793945311965 43.52751299421623,1.7605590820312442 43.52751299421623,1.7605590820312442 43.68636569542979,1.1013793945311965 43.68636569542979,1.1013793945311965 43.52751299421623))', 4326));
-- Indirect query using slicing
EXPLAIN ANALYSE SELECT count(distinct(i_identifier)) FROM outputwkts WHERE ST_intersects(footprint, GeomFromText('POLYGON((1.1013793945311965 43.52751299421623,1.7605590820312442 43.52751299421623,1.7605590820312442 43.68636569542979,1.1013793945311965 43.68636569542979,1.1013793945311965 43.52751299421623))', 4326));
EOF

# Get intersecting polygons on large area (Spain) 
#     POLYGON((-9 44, 4 42,-2 37,-10 36,-9 44))

psql -U postgres -d wktslicer << EOF
-- Direct query on inputwkts
EXPLAIN ANALYSE SELECT count(identifier) FROM inputwkts WHERE ST_intersects(footprint, GeomFromText('POLYGON((-9 44, 4 42,-2 37,-10 36,-9 44))', 4326));
-- Indirect query using slicing
EXPLAIN ANALYSE SELECT count(distinct(i_identifier)) FROM outputwkts WHERE ST_intersects(footprint, GeomFromText('POLYGON((-9 44, 4 42,-2 37,-10 36,-9 44))', 4326));
EOF

echo " Done !"
echo ""
