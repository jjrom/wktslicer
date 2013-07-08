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
# 3. Split SIPAD big polygons into smaller polygon based on a 4x4 square degrees grid
# ----------------------------------------------------------------------------------
echo "Split SIPAD big polygons into 5 degrees latitude slices"
php wktslicerSplice.php $DATA_FOLDER

echo "Cut SIPAD big polygons within a -70/+70 degrees latitude box"
php wktslicerSplice.php $DATA_FOLDER


echo " Done !"
echo ""
