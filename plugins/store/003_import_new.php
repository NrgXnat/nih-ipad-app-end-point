#!/usr/bin/env php

<?php

echo("Running plugin: 003_import.php\n");

// exit early
//exit(0);
    
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

if (!is_readable($filename)) {
    echo("Error: file not found or not readable\n");
    exit(1);
}

if (strpos($filename, "TestConnection.csv_") !== false) {
    exit(0);
}

$csvfile = dirname($filename) . "/" . shortenFilename( $filename ) . ".csv";
echo("csvfile: " . $csvfile . "\n");

$jsonfile = dirname($filename) . "/" . shortenFilename( $filename ) . ".json";
echo("jsonfile: " . $jsonfile . "\n");

// if not registration data and not assessment scores, then file as unused
if ((strpos($filename, "Assessment Scores.csv_") === false) && (strpos($filename, "Registration Data.csv_") === false)) {
    echo("Not registration data and assessment scores. File as unused.\n");
    moveToFolder($filename, "archive-unused");
    moveToFolder($csvfile, "json-unused");
    moveToFolder($jsonfile, "json-unused");
    exit(0);
}

// process assessment scores
if (strpos($filename, "Assessment Scores.csv_") !== false) {
    
    // import summary scores to redcap
    //$command = "php /var/www/html/applications/ipad-app/putDataIntoREDCap.php -f -v -s " . $site . " -i " . $jsonfile;
    $command = "php /var/www/html/applications/ipad-app/dataAndDemoToREDCap.php -t -v -m -s " . $site . " -f data -i " . $jsonfile;
    echo("command: " . $command . "\n");
    
    exec($command, $output, $return);
    
    // check the result
    echo("output: " . json_encode($output, JSON_PRETTY_PRINT) . "\n");
    echo("return: " . $return . "\n");
    
    if (!$return) {
        echo "Success\n";
        moveToFolder($filename, "archive-ok");
        moveToFolder($csvfile, "json-processed");
        exit(0);
    } else {
        echo "Fail\n";
        moveToFolder($filename, "archive-fail");
        exit(1);
    }
}

// process registration data
if (strpos($filename, "Registration Data.csv_") !== false) {
    
    // import demographics to redcap
    //$command = "php /var/www/html/applications/ipad-app/demoIntoREDCap.php -f -v -s " . $site . " -i " . $jsonfile;
    $command = "php /var/www/html/applications/ipad-app/dataAndDemoToREDCap.php -t -v -m -s " . $site . " -f demo -i " . $jsonfile;
    echo("command: " . $command . "\n");
    
    exec($command, $output, $return);
    
    // check the result
    echo("output: " . json_encode($output, JSON_PRETTY_PRINT) . "\n");
    echo("return: " . $return . "\n");
    
    if (!$return) {
        echo "Success\n";
        moveToFolder($filename, "archive-ok");
        moveToFolder($csvfile, "json-demo-processed");
        exit(0);
    } else {
        echo "Fail\n";
        moveToFolder($filename, "archive-fail");
        exit(1);
    }
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

function moveToFolder($filename, $folder) {
    $dirname = dirname($filename);
    $basename = basename($filename);
    $target = $dirname . "/" . $folder . "/" . $basename;
    echo("Move: " . $filename . " to: " . $target . "\n");
    rename($filename, $target);
}

?>
