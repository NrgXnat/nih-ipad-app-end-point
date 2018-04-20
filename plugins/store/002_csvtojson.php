#!/usr/bin/env php

<?php

echo("Running plugin: 002_csvtojson.php\n");

$options = getopt("s:i:");
// -s <SITE>
if (!isset($options['s'])) {
    echo("Error: specify a site with -s <SITE>\n");
    exit(1);
}
$site = $options['s'];
echo("site: " . $site . "\n");

// -i <filename>
if (!isset($options['i'])) {
    echo("Error: specify a file with -i <filename>\n");
    exit(1);
}
$filename = $options['i'];
echo("filename: " . $filename . "\n");

// exit early
//exit(0);

if (strpos($filename, "TestConnection.csv_") !== false) {
    exit(0);
}

function shortenFilename( $filename ) {
    // extract the date and time from the filename
    preg_match('/(\d{4})-(\d{2})-(\d{2}) (\d{2}).(\d{2}).(\d{2})/', $filename, $matches);
    //echo(json_encode($matches, JSON_PRETTY_PRINT)."\n");
    $dateInFilename = $matches[1] . "-".$matches[2] . "-".$matches[3];
    $timeInFilename = $matches[4] . ".".$matches[5] . ".".$matches[6];
    $type = "";
    if (strpos($filename, "Assessment Scores.csv_") !== false) {
        $type = "Scores";
    } else if (strpos($filename, "Assessment Data.csv_") !== false) {
        $type = "Data";
    } else if (strpos($filename, "Registration Data.csv_") !== false) {
        $type = "Registration";
    } else {
        return false;
    }
    $newFilename = $dateInFilename . "-" . $timeInFilename . "-" . $type;
    return $newFilename;
}

$csvfile = dirname($filename) . "/" . shortenFilename( $filename ) . ".csv";
echo("csvfile: " . $csvfile . "\n");

$jsonfile = dirname($filename) . "/" . shortenFilename( $filename ) . ".json";
echo("jsonfile: " . $jsonfile . "\n");

if (!is_readable($csvfile)) {
    echo("Error: file not found or not readable\n");
    exit(1);
}

// convert CSV to JSON
$command = "php /var/www/html/applications/ipad-app/csvToJSON.php -f -s " . $site . " -i " . $csvfile . " > " . $jsonfile;
exec($command, $output, $return);

// check the result
echo("output: " . json_encode($output, JSON_PRETTY_PRINT) . "\n");
echo("return: " . $return . "\n");

if (!$return) {
    echo "Success\n";
    exit(0);
} else {
    echo "Fail\n";
    exit(1);
}

?>
