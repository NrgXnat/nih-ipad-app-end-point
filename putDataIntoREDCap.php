<?php
    
# This script is triggered if we receive new data from the NIH Toolbox.
# It will import scores from the NIH Toolbox into REDCAP.
#

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

// print an error message
function repError( $msg ) {
    echo (json_encode( array( "error" => 1, "message" => $msg ), JSON_PRETTY_PRINT ) );
    return;
}

// print an ok message
function repOk( $msg ) {
    echo (json_encode( array( "error" => 0, "message" => $msg ), JSON_PRETTY_PRINT ) );
    return;
}

// call redcap to check if a pguid has been assented and the baseline date
function getAssentInfo( $pguid, $token ) {
    global $verbose;

    // assent information is stored at baseline
    $baselineEventName = "baseline_year_1_arm_1";

    $args = array(
        'token' => $token,
        'content' => 'record',
        'format' => 'json',
        'type' => 'flat',
        'records' => array($pguid),
        'fields' => array('asnt_sign','asnt_timestamp','nih_tbx_summary_scores_daic_use_only_complete'),
        'events' => array($baselineEventName),
        'rawOrLabel' => 'raw',
        'rawOrLabelHeaders' => 'raw',
        'exportCheckboxLabel' => 'false',
        'exportSurveyFields' => 'false',
        'exportDataAccessGroups' => 'false',
        'returnFormat' => 'json'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://abcd-rc.ucsd.edu/redcap/api/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args, '', '&'));
    $output = curl_exec($ch);
    if ($verbose) echo("output:\n". json_encode($output, JSON_PRETTY_PRINT)."\n");
    $assented = json_decode($output, true)[0]['asnt_sign'];

    if (($assented == "") || ($assented == false)) {
        $assented = false;
    } else {
        $assented = true;
    }

    $assentDate = json_decode($output, true)[0]['asnt_timestamp'];
    
    $alreadyComplete = json_decode($output, true)[0]['nih_tbx_summary_scores_daic_use_only_complete'];
    
    curl_close($ch);
    
    if ($verbose) echo("assented: " . $assented . "\n");
    if ($verbose) echo("assentDate: " . $assentDate . "\n");
    if ($verbose) echo("alreadyComplete: " . $alreadyComplete . "\n");

    return ( array( "assented" => $assented, "assentDate" => $assentDate, "alreadyComplete" => $alreadyComplete ) );
}

// get the list of events from redcap
function getListOfEvents( $token ) {
    
    $args = array(
        'token' => $token,
        'content' => 'event',
        'format' => 'json',
        'returnFormat' => 'json'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://abcd-rc.ucsd.edu/redcap/api/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args, '', '&'));
    $output = curl_exec($ch);
    $events = json_decode($output, true);
    curl_close($ch);

    return $events;
}

// get the event name by calculating the number of days between the baseline date and assessment date
function getEventName( $assentDate, $assessmentDate, $events ) {
    global $verbose;
    
    // get the number of days between the baseline date and the test date
    $time1 = strtotime($assentDate);
    $time2 = strtotime($assessmentDate);
    $dateDiff = $time2 - $time1;
    $offset = floor($dateDiff / (60 * 60 * 24));
    if ($verbose) echo("offset: " . $offset . "\n");

    // find the event name
    $eventName = "";
    $currEvents = array();
    foreach($events as $event){
        if($event["event_name"] == ".Screener"){
            continue;
        }
        $lower_bound = $event["day_offset"] - $event["offset_min"];
        $upper_bound = $event["day_offset"] + $event["offset_max"];
        if($offset >= $lower_bound && $offset <= $upper_bound){
            $currEvents[] = $event["unique_event_name"];
        }
    }
    if(count($currEvents) == 1){
        $eventName = $currEvents[0];
    } else {
        echo("ERROR: More than one event fits this offset: " . $offset . " " . "\n");
        exit(1);
    }
    return $eventName;
}

// map the instrument name to the redcap variable
$mapInstToMeasure = array(
    "NIH Toolbox Picture Vocabulary Test Age 3+ v2.0" => "nihtbx_picvocab_",
    "NIH Toolbox Flanker Inhibitory Control and Attention Test Ages 8-11 v2.0" => "nihtbx_flanker_",
    "NIH Toolbox Flanker Inhibitory Control and Attention Test Ages 8-11 v2.1" => "nihtbx_flanker_",
    "NIH Toolbox List Sorting Working Memory Test Age 7+ v2.0" => "nihtbx_list_",
    "NIH Toolbox List Sorting Working Memory Test Age 7+ v2.1" => "nihtbx_list_",
    "NIH Toolbox Dimensional Change Card Sort Test Ages 8-11 v2.0" => "nihtbx_cardsort_",
    "NIH Toolbox Dimensional Change Card Sort Test Ages 8-11 v2.1" => "nihtbx_cardsort_",
    "NIH Toolbox Pattern Comparison Processing Speed Test Age 7+ v2.0" => "nihtbx_pattern_",
    "NIH Toolbox Pattern Comparison Processing Speed Test Age 7+ v2.1" => "nihtbx_pattern_",
    "NIH Toolbox Picture Sequence Memory Test Age 8+ Form A v2.0" => "nihtbx_picture_",
    "NIH Toolbox Picture Sequence Memory Test Age 8+ Form A v2.1" => "nihtbx_picture_",
    "NIH Toolbox Oral Reading Recognition Test Age 3+ v2.0" => "nihtbx_reading_",
    "Cognition Fluid Composite" => "nihtbx_fluidcomp_",
    "Cognition Fluid Composite v1.1" => "nihtbx_fluidcomp_",
    "Cognition Crystallized Composite" => "nihtbx_cryst_",
    "Cognition Crystallized Composite v1.1" => "nihtbx_cryst_",
    "Cognition Total Composite Score" => "nihtbx_totalcomp_",
    "Cognition Total Composite Score v1.1" => "nihtbx_totalcomp_",
    "Cognition Early Childhood Composite" => "nihtbx_earlycomp_",
    "Cognition Early Childhood Composite v1.1" => "nihtbx_earlycomp_"
);

// map the score name to the redcap variable
$scoresToCopy = array(
    "RawScore" => "rawscore",
    "Theta" => "theta",
    "ItmCnt" => "itmcnt",
    "DateFinished" => "date",
    "Language" => "language",
    "Computed Score" => "computedscore",
    "Uncorrected Standard Score" => "uncorrected",
    "Age-Corrected Standard Score" => "agecorrected",
    "Fully-Corrected T-score" => "fullycorrected",
);

$options = getopt("fvs:i:");

// -f force overwrite
$force = false;
if (isset($options['f'])) {
    $force = true;
}

// -v verbose
$verbose = false;
if (isset($options['v'])) {
    $verbose = true;
}

// -s <SITE>
if (!isset($options['s'])) {
    repError("Error: specify a site with -s <SITE>");
    exit(1);
}
$site = $options['s'];

// -i <filename>
if (!isset($options['i'])) {
    repError("Error: specify a file with -i <filename>\n");
    exit(1);
}
if (!is_readable($options['i'])) {
    repError("Error: file not found or not readable\n");
    exit(1);
}
$filename = $options['i'];

// get date from filename
preg_match('/(\d+)-(\d+)-(\d+)/', $filename, $matches);
$dateInFilename = $matches[0];

// read JSON file
$data = json_decode(file_get_contents($filename), true);

// keep track of successful entries
$doneData = array();

// keep track of failed entries
$failData = array();

// get REDCap token for siteID
$tokensfile = '/var/www/html/applications/ipad-app/tokens.json';
if (!is_readable($tokensfile)) {
    repError("Error: file not found or not readable: " . $tokensfile);
    exit(1);
}
$tokens = json_decode(file_get_contents($tokensfile), true);

$token = "";
if (isset($tokens[$site])) {
    $token = $tokens[$site];
} else {
    echo("ERROR: site: " . $site . " not found in tokens.json:\n");
    exit(1);
}

$fixpguidsfile = '/var/www/html/applications/ipad-app/d/' . $site . '/fixpguids.json';
if (!is_readable($fixpguidsfile)) {
    $msg = 'Error: file not found or not readable: ' . $fixpguidsfile;
    repError($msg);
    exit(1);
}
$fixpguids = json_decode(file_get_contents($fixpguidsfile), true);

$events = getListOfEvents( $token );
//echo("events:\n" . json_encode($events, JSON_PRETTY_PRINT)."\n");

// create an assosciative array of participants
$participants = array();
foreach ( $data as $entry ) {

    $wrong = $entry['PIN'];
    $pguid = "";
    if (isset($fixpguids[$wrong])) {
        $pguid = $fixpguids[$wrong];
        echo("FIXED: Found incorrect pGUID: " . $wrong . " changed to: " . $pguid . "\n");
    } else {
    
        // attempt to fix invalid pguid
        $pguid = strtoupper($wrong);
        if (startsWith($pguid, "INV")) {
            echo("FIXED: pguid: " . $pguid . "\n");
            $pguid = "NDAR_" . $pguid;
        } else if (strlen($pguid) == 8) {
            echo("FIXED: pguid: " . $pguid . "\n");
            $pguid = "NDAR_INV" . $pguid;
        }
        
    }
    if ($verbose) echo("pguid: " . $pguid . "\n");
    if ($pguid == "") continue;

    // get the assessment date for this pguid
    $assessmentDate = $entry['DateFinished'];
    if ($verbose) echo("assessmentDate: " . $assessmentDate . "\n");
    if ($assessmentDate != "") {
        $participants[$pguid] = $assessmentDate;
    } else {
        echo("WARNING: entry['DateFinished'] is empty.\n");
    }
    
}
if ($verbose) echo("participants:\n". json_encode($participants, JSON_PRETTY_PRINT)."\n");

// interate through the participants, checking if each one is assented
foreach ( $participants as $pguid => $assessmentDate ) {
    
    if ($verbose) echo("pguid: " . $pguid . " date: " . $assessmentDate . "\n");
    $result = getAssentInfo( $pguid, $token );

    // append the assessment date to this result
    $result['assessmentDate'] = $assessmentDate;
    if ($verbose) echo("result:\n". json_encode($result, JSON_PRETTY_PRINT)."\n");
    
    $participants[$pguid] = $result;
    
}
if ($verbose) echo("participants:\n" . json_encode($participants, JSON_PRETTY_PRINT) . "\n");

$count = 0;
foreach ( $data as $entry ) {

    if ($verbose) echo("entry: " . json_encode($entry, JSON_PRETTY_PRINT) . "\n");
    
    $wrong = $entry['PIN'];
    $pguid = "";
    if (isset($fixpguids[$wrong])) {
        $pguid = $fixpguids[$wrong];
        echo("FIXED: Found incorrect pGUID: " . $wrong . " changed to: " . $pguid . "\n");
    } else {
    
        // attempt to fix invalid pguid
        $pguid = strtoupper($wrong);
        if (startsWith($pguid, "INV")) {
            echo("FIXED: pguid: " . $pguid . "\n");
            $pguid = "NDAR_" . $pguid;
        } else if (strlen($pguid) == 8) {
            echo("FIXED: pguid: " . $pguid . "\n");
            $pguid = "NDAR_INV" . $pguid;
        }
        
    }
    if ($verbose) echo("pguid: " . $pguid . "\n");

    // pguid information
    $assentInfo = $participants[$pguid];
    if ($verbose) echo("assentInfo: " . json_encode($assentInfo, JSON_PRETTY_PRINT) . "\n");

    $isAssented = $assentInfo['assented'];
    if ($verbose) echo("isAssented: " . $isAssented . "\n");
    /* remove for now
    if (! $isAssented) {
        $message = "ERROR: pguid: " . $pguid . " does not exist or is not assented.";
        echo($message . "\n");
        $entry['importErrorMessage'] = $message;
        $failData[] = $entry;
        continue;
    }
    */
    
    $assentDate = $assentInfo['assentDate'];
    if ($verbose) echo("assentDate: " . $assentDate . "\n");
    /* remove for now
    if ($assentDate == "") {
        $message = "ERROR: pguid: " . $pguid . " has no baseline date.";
        echo($message . "\n");
        $entry['importErrorMessage'] = $message;
        $failData[] = $entry;
        continue;
    }
    */
    
    $assessmentDate = $assentInfo['assessmentDate'];
    if ($verbose) echo("assessmentDate: " . $assessmentDate . "\n");
    /* remove for now
    if ($assessmentDate == "") {
        $message = "ERROR: pguid: " . $pguid . " has no assessment date.";
        echo($message . "\n");
        $entry['importErrorMessage'] = $message;
        $failData[] = $entry;
        continue;
    }
    */

    $alreadyComplete = $assentInfo['alreadyComplete'];
    if ($verbose) echo("alreadyComplete: " . $alreadyComplete . "\n");
    /*
    // TODO: do not overwrite if already completed
    if ($alreadyComplete === "2") {
        // Do not overwrite if already complete
        $message = "The form was already completed. Do not overwrite data.";
        if ($verbose) echo($message . "\n");
        $entry['importErrorMessage'] = $message;
        $failData[] = $entry;
        continue;
    }
    */
        
    // TODO: get the event name
    //$eventName = getEventName( $assentDate, $assessmentDate, $events );
    $eventName = "baseline_year_1_arm_1";
    
    $instrument = $entry['Inst'];
    if ($verbose) echo("Instrument: " . $instrument . "\n");
    // Convert $instrument into nihtbx_picvocab_
    if (!isset($mapInstToMeasure[$instrument])) {
        echo("ERROR: mapInstToMeasure[".$instrument."] was not found");
        exit(1);
        continue;
    }

    // Search for pattern: v#.#
    $pattern = '/v[0-9]+\.[0-9]+$/';
    if (preg_match($pattern, $instrument, $matches)) {
        $version = $matches[0];
        echo("Found a valid version number: " . $version . "\n");
    } else {
        // The version number was not found
        echo("ERROR: No version number found in: " . $instrument . "\n");
        $version = 'NA';
    }
    
    $payload = array(
        "id_redcap" => $pguid,
        "redcap_event_name" => $eventName,
        "nih_tbx_summary_scores_daic_use_only_complete" => 2
    );

    // Add the instrument version to the redcap payload
    $redcapVariableVersion  = $mapInstToMeasure[$instrument] . 'v';
    $payload[$redcapVariableVersion] = $version;
    
    // add each score to the payload
    $missingData = false;
    foreach ($scoresToCopy as $key => $value) {

        if (isset($entry[$key])) {
            if ($key === "id") {
                continue;
            }
            $redcapVariableName  = $mapInstToMeasure[$instrument] . $value;
            $payload[$redcapVariableName] = $entry[$key];
            
            if ($entry[$key] === "") {
                // TODO: if the entry was empty "", then consider marking the form as not complete
                if (($key === "PIN") || ($key === "DeviceID")) {
                    // TODO: if there is missing, then mark as incomplete
                    //$missingData = true;
                }
            }
        }
    }
    if ($missingData) {
        $payload["nih_tbx_summary_scores_daic_use_only_complete"] = 0;
    } else {
        $payload["nih_tbx_summary_scores_daic_use_only_complete"] = 2; // complete but unverified
    }
    if ($verbose) echo("payload:\n" . json_encode($payload, JSON_PRETTY_PRINT)."\n");
    if ($verbose) echo("token: " . $token . "\n");

    $args = array(
        'token'             => $token,
        'content'           => 'record',
        'format'            => 'json',
        'type'              => 'flat',
        'overwriteBehavior' => 'normal',
        'data'              => '[' . json_encode($payload) . ']',
        'returnContent'     => 'count',
        'returnFormat'      => 'json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://abcd-rc.ucsd.edu/redcap/api/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args, '', '&'));
    $output = curl_exec($ch);
    curl_close($ch);
    // error checking
    $result = json_decode($output, true);
    if (array_key_exists('error', $result)) {
        echo("ERROR: ". $output . "\n");
        $entry['importErrorMessage'] = $output;
        $failData[] = $entry;
        continue;
    } else if (array_key_exists('count', $result)) {
        if ($result['count'] == 1) {
            if ($verbose) echo("count: ". $result['count'] . "\n");
        } else {
            echo("ERROR: ". $output . "\n");
            $entry['importErrorMessage'] = $output;
            $failData[] = $entry;
            continue;
        }
    } else {
        echo("ERROR: ". $output . "\n");
        $entry['importErrorMessage'] = $output;
        $failData[] = $entry;
        continue;
    }

    // save the successful entry
    $doneData[] = $entry;
    
    //if ($count > 1) break;
    $count = $count + 1;

    // debugging break loop early
    //break;
}

if ($verbose) echo("doneData:\n" . json_encode($doneData, JSON_PRETTY_PRINT) . "\n");
if ($verbose) echo("failData:\n" . json_encode($failData, JSON_PRETTY_PRINT) . "\n");

// save the successful entries to the 'done' directory
if (count($doneData) > 0) {
    $basename = basename($filename);
    $dirname = dirname($filename);
    $destination = $dirname . "/json-ok/" . $basename;
    if ($verbose) echo("destination: " . $destination . "\n");
    //copy($filename, $destination);
    file_put_contents($destination, json_encode($doneData, JSON_PRETTY_PRINT));
    if (filesize($destination) > 0) {
        if ($verbose) echo("Successfully saved file: " . $destination . "\n");
    } else {
        echo("ERROR: Failed to save file: " . $destination . "\n");
    }
}

// save the failed entries to the 'fail' directory
if (count($failData) > 0) {
    $basename = basename($filename);
    $dirname = dirname($filename);
    $destination = $dirname . "/json-fail/" . $basename;
    if ($verbose) echo("destination: " . $destination . "\n");
    //copy($filename, $destination);
    file_put_contents($destination, json_encode($failData, JSON_PRETTY_PRINT));
    if (filesize($destination) > 0) {
        if ($verbose) echo("Successfully saved file: " . $destination . "\n");
    } else {
        echo("ERROR: Failed to save file: " . $destination . "\n");
    }
}

if (true) {
    $basename = basename($filename);
    $dirname = dirname($filename);
    $destination = $dirname . "/json-processed/" . $basename;
    //if ($verbose)
    echo("Moving input file to destination: " . $destination . "\n");
    if ($force) rename($filename, $destination);
}

if ($verbose) echo("Successfully imported count: " . $count . " entries\n");
repOk("ok");

exit(0);

?>
