#!/bin/bash
#
# WKT slicer - cut a polygon into slices or parts to speed up database indexing
#
# Author : Jerome Gasperi @ CNES
# Date   : 2013.07.08
# Version: 1.0
#

# Paths are mandatory from command line
SUPERUSER=postgres
DROPFIRST=NO
DB=wktslicer
usage="## CharterNG database installation\n\n  Usage $0 -d <PostGIS directory> [-s <database SUPERUSER> -F]\n\n  -d : absolute path to the directory containing postgis.sql\n  -s : dabase SUPERUSER (default "postgres")\n  -F : WARNING - suppress existing wktslicer database\n"
while getopts "d:s:hF" options; do
    case $options in
        d ) ROOTDIR=`echo $OPTARG`;;
        s ) SUPERUSER=`echo $OPTARG`;;
        F ) DROPFIRST=YES;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$ROOTDIR" = "" ]
then
    echo -e $usage
    exit 1
fi
if [ "$DROPFIRST" = "YES" ]
then
    dropdb -U $SUPERUSER $DB
fi

# Example : $ROOTDIR = /usr/local/pgsql/share/contrib/postgis-1.5/
postgis=`echo $ROOTDIR/postgis.sql`
projections=`echo $ROOTDIR/spatial_ref_sys.sql`

# Make db POSTGIS compliant
createdb $DB -U $SUPERUSER
createlang -U $SUPERUSER plpgsql $DB
psql -d $DB -U $SUPERUSER -f $postgis
psql -d $DB -U $SUPERUSER -f $projections

# Install schema
psql -d $DB -U $SUPERUSER -f wktslicerDB.sql

# VACCUM
vacuumdb --full --analyze -U $SUPERUSER $DB
