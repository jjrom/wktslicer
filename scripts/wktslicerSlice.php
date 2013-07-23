<?php

/*
 * WKT slicer - cut a polygon into slices or parts to speed up database indexing
 *
 * Auteur   : Jerome Gasperi
 * Date     : 2013.04.04
 *
 */

/*
 * Simplify a WKT to the given precision (i.e. number of digits)
 * 
 * @param {String} $wkt : must be a POLYGON
 * @param {Integer} $precision
 */

function simplify($wkt, $precision) {

    $pairs = explode(",", str_replace(")", "", str_replace("(", "", str_replace("POLYGON", "", $wkt))));
    $l = count($pairs);
    $arr = array();
    for ($i = 0; $i < $l; $i++) {
        $coordinates = explode(" ", trim($pairs[$i]));
        $lon = number_format(floatval($coordinates[0]), $precision);
        $lat = number_format(floatval($coordinates[1]), $precision);
        $arr[$i] = $lon . " " . $lat;
    }

    return "POLYGON((" . join(",", $arr) . "))";
}

/*
 * Returns an array of WKT POLYGONS from A MULTIPOLYGON
 * 
 * @param {String} $wkt : a WKT MULTIPOLYGON or POLYGON
 * @return {array} $arr : an array of POLYGON WKT
 */

function multipolygonToPolygons($wkt) {

    /*
     * If input is a POLYGON returns it within an array of one element
     */
    if (strrpos("MULTIPOLYGON", $wkt) === false) {
        return array($wkt);
    }

    /*
     * Split MULTIPOLYGON by detecting ")),(("
     */
    $parts = explode(")),((", str_replace(")))", "))", str_replace("(((", "((", str_replace("MULTIPOLYGON", "", $wkt))));
    $l = count($parts);
    $arr = array();
    for ($i = 0; $i < $l; $i++) {
        $arr[$i] = "POLYGON((" . str_replace("))", "", str_replace("((", "", $parts[$i])) . "))";
    }

    return $arr;
}

/* ------------------------- MAIN ---------------------- */

// Remove PHP NOTICE
error_reporting(E_PARSE);

// This application can only be called from a shell (not from a webserver)
if (empty($_SERVER['SHELL'])) {
    exit;
}

# Default values
$storeToDB = false;
$gridsize = 10;
$latitude = 70;
$gap = 0;
$precision = -1;
$dbname = 'wktslicer';
$help = "\n## Usage php splitpolygonSplit.php -i <identifier - 'ALL' for all database>  -t <'cut', 'slice' or 'box'>[-a -p <precision i.e. number of digits> -g <gap in degrees between latitude> -d <database name - default 'wktslicer'> -o <output 'csv' or 'geojson'> -s <grid_size>]\n\n Note : If -a is set, then result is stored within outputwkts database table (default not stored). This option is ignored if  -o 'geojson' is set\n -g only works on 'slice'. It is used to create MUTLIPOLYGONS from POLYGONS (topological constraints impose that POLYGONS from a MULTIPOLYGON do not touch)\n\n";

$options = getopt("ap:g:d:s:i:o:t:h");
foreach ($options as $option => $value) {
    if ($option === "a") {
        $storeToDB = true;
    }
    if ($option === "t") {
        $action = $value;
    }
    if ($option === "s") {
        $gridsize = intval($value);
    }
    if ($option === "i") {
        $identifier = $value;
    }
    if ($option === "o") {
        $output = $value;
    }
    if ($option === "d") {
        $dbname = $value;
    }
    if ($option === "g") {
        $gap = $value;
    }
    if ($option === "p") {
        $precision = $value;
    }
    if ($option === "h") {
        echo $help;
        exit;
    }
}

if (!$identifier) {
    echo $help;
    exit;
}
if (!$action) {
    echo $help;
    exit;
}

// Connect to DB
$dbh = pg_connect("host=localhost dbname=" . $dbname . " user=postgres password=postgres") or die(pg_last_error());

// Loop on each input polygon
if ($identifier === "ALL") {
    $query = "SELECT identifier FROM inputwkts";
} else {
    $query = "SELECT identifier FROM inputwkts WHERE identifier = '" . $identifier . "'";
}

/*
 * Initialize GeoJSON empty FeatureCollection
 */
$out = array(
    'type' => 'FeatureCollection',
    'totalResults' => 0,
    'features' => array()
);

if ($output === 'geojson') {
    $parser = "ST_AsGeoJSON";
} else {
    $parser = "ST_AsText";
}

$identifiers = pg_query($dbh, $query) or die(pg_last_error());
$count = 0;
$inserted = 0;

while ($identifier = pg_fetch_assoc($identifiers)) {

    if ($action === "cut") {

        $lon = -360;
        $lon2 = 360;
        $lat = $latitude;
        $lat2 = $latitude * -1;

        $str = $lon . " " . $lat . "," . $lon . " " . $lat2 . "," . $lon2 . " " . $lat2 . "," . $lon2 . " " . $lat . "," . $lon . " " . $lat;
        $wkt = "POLYGON((" . $str . "))";

        // Intersect grid wkt with input wkt
        if ($output === 'geojson' && $precision !== -1) {
            $query2 = "SELECT identifier, " . $parser . "(ST_Reverse(ST_ForceRHR(st_intersection(footprint, GeometryFromText('" . $wkt . "', 4326)))), " . $precision . ") as geom from inputwkts WHERE identifier = '" . $identifier['identifier'] . "' AND st_isvalid(footprint) AND st_intersects(footprint, GeometryFromText('" . $wkt . "', 4326)) = 't'";
        } else {
            $query2 = "SELECT identifier, " . $parser . "(ST_Reverse(ST_ForceRHR(st_intersection(footprint, GeometryFromText('" . $wkt . "', 4326))))) as geom from inputwkts WHERE identifier = '" . $identifier['identifier'] . "' AND st_isvalid(footprint) AND st_intersects(footprint, GeometryFromText('" . $wkt . "', 4326)) = 't'";
        }

        $results = pg_query($dbh, $query2);

        while ($result = pg_fetch_assoc($results)) {

            if ($output === 'geojson') {

                /* Add feature array to feature collection array */
                array_push($out['features'], array(
                    'type' => 'Feature',
                    'geometry' => json_decode($result['geom'], true),
                    'properties' => array(
                        'identifier' => $result['identifier']
                    )
                ));
            } else {
                $arr = multipolygonToPolygons($result['geom']);
                for ($i = 0; $i < count($arr); $i++) {
                    if ($storeToDB) {
                        pg_query($dbh, "INSERT INTO outputwkts (i_identifier, footprint) VALUES ('" . $result['identifier'] . "','SRID=4326;" . $arr[$i] . "');");
                        $inserted++;
                    } else {
                        echo $result['identifier'] . ";" . $arr[$i] . "\n";
                    }
                }
            }

            /* Increment the number of element */
            $count++;
        }
    } else if ($action === "slice") {

        // Product the grid
        // Latitude from -90 to 90
        $lon = -360;
        $lon2 = 360;
        for ($lat = -90; $lat <= 90; $lat = $lat + $gridsize) {

            $lat1 = $lat + $gap;
            $lat2 = $lat1 + $gridsize - $gap;

            $str = $lon . " " . $lat1 . "," . $lon . " " . $lat2 . "," . $lon2 . " " . $lat2 . "," . $lon2 . " " . $lat1 . "," . $lon . " " . $lat1;
            $wkt = "POLYGON((" . $str . "))";

            // Intersect grid wkt with input wkt
            if ($output === 'geojson' && $precision !== -1) {
                $query2 = "SELECT identifier, " . $parser . "(ST_Reverse(ST_ForceRHR(st_intersection(footprint, GeometryFromText('" . $wkt . "', 4326)))), " . $precision . ") as geom from inputwkts WHERE identifier = '" . $identifier['identifier'] . "' AND st_isvalid(footprint) AND st_intersects(footprint, GeometryFromText('" . $wkt . "', 4326)) = 't'";
            } else {
                $query2 = "SELECT identifier, " . $parser . "(ST_Reverse(ST_ForceRHR(st_intersection(footprint, GeometryFromText('" . $wkt . "', 4326))))) as geom from inputwkts WHERE identifier = '" . $identifier['identifier'] . "' AND st_isvalid(footprint) AND st_intersects(footprint, GeometryFromText('" . $wkt . "', 4326)) = 't'";
            }
            $results = pg_query($dbh, $query2);

            while ($result = pg_fetch_assoc($results)) {

                /* Add feature array to feature collection array */
                if ($output === 'geojson') {
                    array_push($out['features'], array(
                        'type' => 'Feature',
                        'geometry' => json_decode($result['geom'], true),
                        'properties' => array(
                            'identifier' => $result['identifier']
                        )
                    ));
                } else {
                    $arr = multipolygonToPolygons($result['geom']);
                    for ($i = 0; $i < count($arr); $i++) {
                        $wkt = $arr[$i];
                        if ($precision !== -1) {
                            $wkt = simplify($wkt, $precision);
                        }
                        if ($storeToDB) {
                            pg_query($dbh, "INSERT INTO outputwkts (i_identifier, footprint) VALUES ('" . $result['identifier'] . "','SRID=4326;" . $wkt . "');");
                            $inserted++;
                        } else {
                            echo $result['identifier'] . ";" . $wkt . "\n";
                        }
                    }
                }

                /* Increment the number of element */
                $count++;
            }
        }
    } else if ($action === "grid") {

        // Product the grid
        // Longitude from -360 to 360
        for ($lon = -360; $lon <= 360; $lon = $lon + $gridsize) {
            // Latitude from -90 to 90
            for ($lat = -90; $lat <= 90; $lat = $lat + $gridsize) {

                $lat2 = $lat + $gridsize;
                $lon2 = $lon + $gridsize;

                $str = $lon . " " . $lat . "," . $lon . " " . $lat2 . "," . $lon2 . " " . $lat2 . "," . $lon2 . " " . $lat . "," . $lon . " " . $lat;
                $wkt = "POLYGON((" . $str . "))";

                // Intersect grid wkt with input wkt
                $query2 = "SELECT identifier, " . $parser . "((ST_Reverse(ST_ForceRHR(st_intersection(footprint, GeometryFromText('" . $wkt . "', 4326))))) as geom from inputwkts WHERE identifier = '" . $identifier['identifier'] . "' AND st_isvalid(footprint) AND st_intersects(footprint, GeometryFromText('" . $wkt . "', 4326)) = 't'";

                $results = pg_query($dbh, $query2);

                while ($result = pg_fetch_assoc($results)) {

                    if ($output === 'geojson') {
                        array_push($out['features'], array(
                            'type' => 'Feature',
                            'geometry' => json_decode($result['geom'], true),
                            'properties' => array(
                                'identifier' => $result['identifier']
                            )
                        ));
                    } else {
                        $arr = multipolygonToPolygons($result['geom']);
                        for ($i = 0; $i < count($arr); $i++) {
                            if ($storeToDB) {
                                pg_query($dbh, "INSERT INTO outputwkts (i_identifier, footprint) VALUES ('" . $result['identifier'] . "','SRID=4326;" . $arr[$i] . "');");
                                $inserted++;
                            } else {
                                echo $result['identifier'] . ";" . $arr[$i] . "\n";
                            }
                        }
                    }

                    /* Increment the number of element */
                    $count++;
                }
            }
        }
    }
}

$out['totalResults'] = $count;

if ($output === 'geojson') {
    echo json_encode($out);
}
else {
    "Insert $inserted products in $dbname database\n";
}
// Properly exits script
pg_close($dbh);
exit(0);
?>