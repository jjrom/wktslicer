<?php
/*
 * WKT slicer - cut a polygon into slices or parts to speed up database indexing
 *
 * Auteur   : Jerome Gasperi
 * Date     : 2013.07.05
 *
 */

/* ------------------------- MAIN ----------------------*/

// Remove PHP NOTICE
error_reporting(E_PARSE);

// This application can only be called from a shell (not from a webserver)
if (empty($_SERVER['SHELL'])) {
    exit;
}

// Help
$help = "\n## Usage php wktslicerToGeoJSON.php -i <wkt identifier> [-d <database name - default is 'wktslicer'>]\n\n";

// Default values
$dbname = "wktslicer";

$options = getopt("d:i:h");
foreach($options as $option => $value) {
    if ($option === "d") {
        $dbname = $value;
    }
    // Exemple : P3L1TBG1015134J
    if ($option === "i") {
        $identifier = $value;
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


// Connect to DB
$dbh = pg_connect("host=localhost dbname=".$dbname." user=postgres password=postgres") or die(pg_last_error());

$query = "SELECT identifier, ST_AsGeoJSON(footprint) as geojson FROM inputwkts WHERE identifier = '" . $identifier . "'";

/*
* Initialize GeoJSON empty FeatureCollection
*/
$geojson = array(
  'type' => 'FeatureCollection',
  'totalResults' => 0,
  'features' => array()
);

$results = pg_query($dbh, $query) or die(pg_last_error());
while ($result = pg_fetch_assoc($results)) {

    $feature = array(
          'type' => 'Feature',
          'geometry' => json_decode($result['geojson'], true),
          'properties' => array(
                'identifier' => $result['identifier'] 
           )
    );
        
    /* Add feature array to feature collection array */
    array_push($geojson['features'], $feature);

    /* Increment the number of element */
    $count++;
}

$geojson['totalResults'] = $count;
  
/* Return all products as a json format */
echo json_encode($geojson); 

// Properly exits script
pg_close($dbh);
exit(0);

?>
