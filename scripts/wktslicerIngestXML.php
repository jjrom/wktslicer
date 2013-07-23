<?php

/*
 * WKT slicer - cut a polygon into slices or parts to speed up database indexing
 *
 * Ingest XML data from SIPAD XML files into wktslicer database
 *
 * Auteur   : Jerome Gasperi
 * Date     : 2013.04.04
 *
 */

/* --------------------- FUNCTIONS ---------------------- */

function getXmlList($directory) {

    // create an array to hold directory list
    $results = array();

    // Only allow XML files
    $allowed_types = array('xml');

    // create a handler for the directory
    $handler = opendir($directory);

    // open directory and walk through the filenames
    while ($file = readdir($handler)) {

        // if file isn't this directory or its parent, add it to the results
        if (in_array(strtolower(substr($file, -3)), $allowed_types)) {
            $results[] = $file;
        }
    }

    // tidy up: close the handler
    closedir($handler);

    // done!
    return $results;
}

/* ------------------------- MAIN ---------------------- */

// Remove PHP NOTICE
error_reporting(E_PARSE);

// This application can only be called from a shell (not from a webserver)
if (empty($_SERVER['SHELL'])) {
    exit;
}

// Help
$help = "\n## Usage php wktslicerIngestXML.php -f <Folder containing XML files> [-d <database name - default is 'wktslicer'>]\n\n";
$help .= "  XML files must follow SIPAD structure - see example here https://raw.github.com/jjrom/wktslicer/master/data/SIPAD_example_file.xml \n\n";

// Default values
$dbname = "wktslicer";

$options = getopt("d:f:h");
foreach ($options as $option => $value) {
    if ($option === "d") {
        $dbname = $value;
    }
    if ($option === "f") {
        $datafolder = $value;
    }
    if ($option === "h") {
        echo $help;
        exit;
    }
}

if (!$datafolder) {
    echo $help;
    exit;
}

// Connect to DB
$dbh = pg_connect("host=localhost dbname=" . $dbname . " user=postgres password=postgres") or die(pg_last_error());

// Get all xml files
$xmlFiles = getXmlList($datafolder);

// Number of inserts
$total = 0;

// Loop over each xml files
foreach ($xmlFiles as $xml) {
    
    echo "Processing file $xml \n";
    $count = 0;
    
    // Create a new dom instance
    $dom = new DomDocument();

    // Load the xml file
    $dom->load($datafolder . '/' . $xml);

    // Retrieves each object from XML file
    $folders = $dom->getElementsByTagName("DATA_OBJECT_DESCRIPTION_POLDER");

    foreach ($folders as $folder) {

        $identifier = $folder->getElementsByTagName("DATA_OBJECT_IDENTIFIER")->item(0)->nodeValue;

        // Initialize empty array for new coordinates
        $newPairs = array();
        
        // No Geo Information - skip
        if ($folder->getElementsByTagName("GEO_POLYGON")->length === 0) {
            continue;
        }
        
        // Explode WKT into coordinates array
        $wkt = trim(str_replace("POLYGON", "", str_replace("))", "", str_replace("((", "", $folder->getElementsByTagName("GEO_POLYGON")->item(0)->nodeValue))));
        $pairs = explode(",", $wkt);
        $l = count($pairs);
        $coordinates = explode(" ", trim($pairs[0]));
        $lonPrev = floatval($coordinates[0]);
        $latPrev = floatval($coordinates[1]);
        array_push($newPairs, $lonPrev . " " . $latPrev);

        // If Delta(lon(i) - lon(i - 1)) is greater than 180 degrees then add 360 to lon
        for ($i = 1; $i < $l; $i++) {
            $coordinates = explode(" ", trim($pairs[$i]));
            $lon = floatval($coordinates[0]);
            $lat = floatval($coordinates[1]);

            if ($lon - $lonPrev >= 180) {
                $lon = $lon - 360;
            } else if ($lon - $lonPrev <= -180) {
                $lon = $lon + 360;
            }

            $lonPrev = $lon;
            $latPrev = $lat;

            array_push($newPairs, $lon . " " . $lat);
        }


        $footprint = "SRID=4326;POLYGON((" . join(",", $newPairs) . "))";

        // Prepare INSERT query
        $query = "DELETE FROM inputwkts WHERE identifier='" . $identifier . "'";
        pg_query($dbh, $query) or die(pg_last_error());
        $query = "INSERT INTO inputwkts (identifier, footprint) VALUES ('" . $identifier . "','" . $footprint . "');";
        pg_query($dbh, $query) or die(pg_last_error());
        $count++;
    }
    echo "  --> $count products inserted\n";
    $total += $count;
}

echo "Done ! $total products inserted\n";
        
// Properly exits script
pg_close($dbh);
exit(0);
?>