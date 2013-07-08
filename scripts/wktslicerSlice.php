<?php
/*
 * WKT slicer - cut a polygon into slices or parts to speed up database indexing
 *
 * Auteur   : Jerome Gasperi
 * Date     : 2013.04.04
 *
 */

/* ------------------------- MAIN ----------------------*/

// Remove PHP NOTICE
error_reporting(E_PARSE);

// This application can only be called from a shell (not from a webserver)
if (empty($_SERVER['SHELL'])) {
    exit;
}

# Default values
$gridsize = 10;
$latitude = 70;
$dbname = 'wktslicer';
$help = "\n## Usage php splitpolygonSplit.php -i <identifier - 'ALL' for all database>  -t <'cut', 'slice' or 'box'>[-d <database name - default 'wktslicer'> -o <output 'csv' or 'geojson'> -g <grid_size>]\n\n";

$options = getopt("d:g:i:o:t:h");
foreach($options as $option => $value) {
    if ($option === "t") {
        $action = $value;
    }
    if ($option === "g") {
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
$dbh = pg_connect("host=localhost dbname=".$dbname." user=postgres password=postgres") or die(pg_last_error());

// Loop on each input polygon
if ($identifier === "ALL") {
    $query = "SELECT identifier FROM inputwkts";
}
else {
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
}
else {
    $parser = "ST_AsText";
}

$identifiers = pg_query($dbh, $query) or die(pg_last_error());

while ($identifier = pg_fetch_assoc($identifiers)) {

    if ($action === "cut") {

        $lon = -180;
        $lon2 = 180;
        $lat = $latitude;
        $lat2 = $latitude * -1;

        $str = $lon . " " . $lat . "," . $lon . " " . $lat2 . "," . $lon2 . " " . $lat2 . "," . $lon2 . " " . $lat . "," . $lon . " " . $lat;
        $wkt = "POLYGON((" . $str . "))";

        // Intersect grid wkt with input wkt
        $query2 = "SELECT identifier, " . $parser . "(st_intersection(footprint, GeometryFromText('" . $wkt . "', 4326))) as geom from inputwkts WHERE identifier = '" . $identifier['identifier'] . "' AND st_isvalid(footprint) AND st_intersects(footprint, GeometryFromText('" . $wkt . "', 4326)) = 't'";
        
        $results = pg_query($dbh, $query2);

        while ($result = pg_fetch_assoc($results)) {

            if ($output === 'geojson') {
                $feature = array(
                      'type' => 'Feature',
                      'geometry' => json_decode($result['geom'], true),
                      'properties' => array(
                            'identifier' => $result['identifier'] 
                       )
                );
            }
            else {
                $feature = array($result['identifier'], $result['geom']);
            }

            /* Add feature array to feature collection array */
            array_push($out['features'], $feature);

            /* Increment the number of element */
            $count++;
        }

    }

    else if ($action === "slice") {

        // Product the grid
        // Latitude from -90 to 90
        $lon = -180;
        $lon2 = 180;
        for ($lat = -90; $lat <= 90; $lat = $lat + $gridsize) {
            
            $lat2 = $lat + $gridsize;

            $str = $lon . " " . $lat . "," . $lon . " " . $lat2 . "," . $lon2 . " " . $lat2 . "," . $lon2 . " " . $lat . "," . $lon . " " . $lat;
            $wkt = "POLYGON((" . $str . "))";

            // Intersect grid wkt with input wkt
            $query2 = "SELECT identifier, " . $parser . "(st_intersection(footprint, GeometryFromText('" . $wkt . "', 4326))) as geom from inputwkts WHERE identifier = '" . $identifier['identifier'] . "' AND st_isvalid(footprint) AND st_intersects(footprint, GeometryFromText('" . $wkt . "', 4326)) = 't'";
            
            $results = pg_query($dbh, $query2);

            while ($result = pg_fetch_assoc($results)) {

                if ($output === 'geojson') {
                    $feature = array(
                          'type' => 'Feature',
                          'geometry' => json_decode($result['geom'], true),
                          'properties' => array(
                                'identifier' => $result['identifier'] 
                           )
                    );
                }
                else {
                    $feature = array($result['identifier'], $result['geom']);
                }
                    
                /* Add feature array to feature collection array */
                array_push($out['features'], $feature);

                /* Increment the number of element */
                $count++;
            }

        }        
    }

    else if ($action === "grid") {

        // Product the grid
        // Longitude from -180 to 180
        for ($lon = -180; $lon <= 180; $lon = $lon + $gridsize) {
            // Latitude from -90 to 90
            for ($lat = -90; $lat <= 90; $lat = $lat + $gridsize) {
                
                $lat2 = $lat + $gridsize;
                $lon2 = $lon + $gridsize;

                $str = $lon . " " . $lat . "," . $lon . " " . $lat2 . "," . $lon2 . " " . $lat2 . "," . $lon2 . " " . $lat . "," . $lon . " " . $lat;
                $wkt = "POLYGON((" . $str . "))";

                // Intersect grid wkt with input wkt
                $query2 = "SELECT identifier, " . $parser . "(st_intersection(footprint, GeometryFromText('" . $wkt . "', 4326))) as geom from inputwkts WHERE identifier = '" . $identifier['identifier'] . "' AND st_isvalid(footprint) AND st_intersects(footprint, GeometryFromText('" . $wkt . "', 4326)) = 't'";
                
                $results = pg_query($dbh, $query2);

                while ($result = pg_fetch_assoc($results)) {

                    if ($output === 'geojson') {
                        $feature = array(
                              'type' => 'Feature',
                              'geometry' => json_decode($result['geom'], true),
                              'properties' => array(
                                    'identifier' => $result['identifier'] 
                               )
                        );
                    }
                    else {
                        $feature = array($result['identifier'], $result['geom']);
                    }
                        
                    /* Add feature array to feature collection array */
                    array_push($out['features'], $feature);

                    /* Increment the number of element */
                    $count++;
                }

            }        

        }
    }
}

// Close database
pg_close($dbh);

$out['totalResults'] = $count;
  
if ($output === 'geojson') {
    echo json_encode($out); 
}
else {
    foreach ($out['features'] as $feature) {
        echo join(";", $feature) . "\n";
    }
}
// Properly exits script
exit(0);

?>
