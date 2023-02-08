<?php
include('config.php');
include('../include/PlantList.php');
include('../include/TaxonRecord.php');
include('../include/NameMatcher.php');

// we don't time out this script because it might get busy
set_time_limit(60*5); // for 5 minutes

$messages = array();

$file_dir = 'matching_cache/' . session_id() . "/";
if(!file_exists($file_dir)) mkdir($file_dir);
$input_file_path = $file_dir . "input.csv";
$output_file_path = $file_dir . "output.csv";

// are they posting some data?
if($_POST){
    if(isset($_FILES["input_file"])){
        // are they uploading a file? 
        // FIXME: DO SOME CHECKING ON SIZE AND FILE TYPE.
        move_uploaded_file($_FILES["input_file"]["tmp_name"], $input_file_path);
        $_SESSION['data_type'] = "CSV";
    }else{
        // they are posting data instead
        file_put_contents($input_file_path, $_POST['name_data']);
        $_SESSION['data_type'] = "cut and paste";
    }
}

// FIXME. does the datatype get forgotten but the session id carry on? Sometimes get an error that the index is out.

// they are deleting the data
if(isset($_GET['delete_data']) && $_GET['delete_data'] == 'true'){
    unlink($input_file_path);
    unlink($output_file_path);
    unset($_SESSION['data_type']);
}

// they are updating some parameters
if(isset($_GET['update_matching_params']) && $_GET['update_matching_params'] = 'true'){
    $_SESSION['matching_params'] = $_GET;
}else{
    // initiated the params if they haven't already been set
    if(!isset($_SESSION['matching_params'])){
        $_SESSION['matching_params'] = array(
            'name_col_index' => -1,
            'interactive' => false,
            'homonyms' => false,
            'ranks' => false,
            'ex' => false
        );
    }
}

// they have posted the results of a choice
// we do this almost like it is a separate page 
// so we can redirect and keep things simple
if(@$_GET['chosen_wfo']){

    // read in the whole file.
    $rows = array();
    $in = fopen($output_file_path, 'r');
    while($row = fgetcsv($in))$rows[] = $row;
    fclose($in);

    for($i = 1; $i < count($rows); $i++){

        $row = $rows[$i];

        // work through till we find the marker we left
        if($row[0] != 'CHOICE') continue;

        if($_GET['chosen_wfo'] == 'SKIP'){

            // they are skipping this one
            $row[0] = 'SKIPPED';
            $row[1] = '';
            $row[2] = '';

        }else{

            // they have sent a wfo id
            if($_GET['chosen_wfo'] == 'CUSTOM'){
                $chosen_name = new TaxonRecord($_GET['custom_wfo']);
            }else{
                $chosen_name = new TaxonRecord($_GET['chosen_wfo']);
            }

            $row[0] = $chosen_name->getWfoId();
            $row[1] = $chosen_name->getFullNameStringPlain();
            $row[2] = $chosen_name->getWfoPath();
            
        }

        $rows[$i] = $row;


    }

    // write the rows back to file
    $out = fopen($output_file_path, 'w');
    foreach($rows as $row) fputcsv($out, $row);
    fclose($out);

    // redirect to continued matching.
    header('Location: matching.php?matching_mode=' . $_GET['matching_mode'] . '&offset=' . $_GET['offset']);
    exit;
        

} 

require_once('header.php');
?>


<h1>Name Matching Tool</h1>
<p><a href="#instructions">Instructions are below</a></p>

<?php
if(@$_GET['matching_mode']){
    echo '<div>';
    
    // does the output file exist?
    if(!file_exists($output_file_path)){

        // FIXME: if the input file already has the wfo_ fields don't create them

        // no output file so create it
        $in = fopen($input_file_path, 'r');
        $out = fopen($output_file_path, 'w');
        $is_re_upload = false;

        if($_SESSION['data_type'] == 'CSV'){
            
            $header = fgetcsv($in);

            // add the first three rows if they are not already there
            if(preg_match('/wfo_id$/', $header[0])){ // use odd regex to avoid odd excel chars at start of file
                $is_re_upload = true;
            }else{
                array_unshift($header, 'wfo_id', 'wfo_full_name', 'wfo_check');
            }

        }else{
            $header = array('wfo_id', 'wfo_full_name', 'wfo_check', 'input_name_string');
        }
        
        fputcsv($out, $header);

        if($_SESSION['data_type'] == 'CSV'){
            while($line = fgetcsv($in)){

                if(!$is_re_upload){
                    // they haven't uploaded a file with the three cols 
                    // already in it so shift the cols across
                    array_unshift($line, '', '', '');
                }
                
                fputcsv($out, $line);
            }
        }else{
            while($name = fgets($in)){
                $line = array('','','',trim($name));
                fputcsv($out, $line);
            }
        }
        fclose($in);
        fclose($out);
    }

    // we have an output file with additional columns to work with.
    // load the whole thing into memory
    $rows = array();
    $in = fopen($output_file_path, 'r');
    while($row = fgetcsv($in))$rows[] = $row;
    fclose($in);

    // which column is the name in?
    $name_index = $_SESSION['data_type'] == 'CSV' ? $_SESSION['matching_params']['name_col_index'] + 3 : 3;

    $offset = @$_GET['offset'] ? @$_GET['offset'] : 1;
    $page_end = $offset + 100;
    $stopped_for_choice = false;
    
    for($i = $offset; $i < count($rows); $i++){

        $row = $rows[$i];

        if(
            !$row[0] // no wfo id yet
            ||
            ($_GET['matching_mode'] == 'skipped' && $row[0] == 'SKIPPED')
        ){

            $config = new class{}; // matching configuration object
            $config->method = "full";
            $config->includeDeprecated = true;
            $config->limit = 10;
            $config->checkHomonyms = @$_GET['homonyms'] == 'true' ? true : false;
            $config->checkRank = @$_GET['ranks'] == 'true' ? true : false;
            $matcher = new NameMatcher($config);

            $response = $matcher->match($row[$name_index]);

            if($response->match && !$response->candidates){
                // we have an exact match with no ambiguity
                $row[0] = $response->match->getWfoId();
                $row[1] = $response->match->getFullNameStringPlain();
                $row[2] = $response->match->getWfoPath();
            }elseif($response->match && $response->candidates){
                // we have an exact match AND some ambiguity
                // this will be homonyms or ranks based
                if(@$_SESSION['matching_params']['interactive']){
                    $row[0] = 'CHOICE';
                    render_choices($response);
                }
            }elseif(!$response->match && $response->candidates){
                // no exact match but some candidates to look at
                if(@$_SESSION['matching_params']['interactive']){
                    // render some choice boxes
                    $row[0] = 'CHOICE';
                    render_choices($response);
                }else{
                    // not interactive and no precise match found
                    $row[0] = '';
                    $row[1] = '';
                    $row[2] = count($response->candidates) . ' candidates found.';
                }
            }else{
                // nothing at all! Not a squib?
                $row[0] = '';
                $row[1] = '';
                $row[2] = 'No candidates found';
            }

            // $new_row = match_row($row, $name_index, $matcher);
            $rows[$i] = $row;

            // what we do depends on the value returned
            if($row[0] == 'CHOICE'){
                $stopped_for_choice = true;
                break; // stop! We have rendered a choice box
            } 

        }
    
        $offset = $i;

        // paging 
        if($i > $page_end) break;

    } // working through rows

    // write out some stats on how we are doing
    $total_rows = count($rows) -1;
    $matched = 0;
    $skipped = 0;
    foreach($rows as $row){
        if($row[0] == 'SKIPPED') $skipped++;
        if(preg_match('/^wfo-[0-9]{10}$/', $row[0])) $matched++;
    }


    if($offset == $total_rows){
        
        if($matched == $total_rows){
           echo '<h2 style="color: green;">Matching complete.</h2>'; 
           echo "<p>You can download the results now.</p>";       
        }else{
           echo '<h2>End of table reached.</h2>';
           echo "<p>You haven't matched all the names. You could try and run matching again with different parameters.</p>"; 
           echo "<p>You can download the results anytime so you don't lose your work.</p>";
        }
        echo "<p><a href=\"$output_file_path\">Download Results</a></p>";

    }else{
        echo '<h2>Matching in progress.</h2>';
        // progress bar
        $percent = round( ($offset/$total_rows)*100 );
        echo "<div style=\"background-color: white; height: 7px; width: 100%; padding:0px; border: solid black 1px;\">";
        echo "<div style=\"background-color: blue; height: 7px; width: $percent%; padding:0px; border: none;\"></div>";
        echo "</div>";
        echo '<p style="color: red; text-align: right;"><a href="matching.php"  >Stop</a></p>';
    }
    
    echo "<p>[ Total rows: " 
        . number_format($total_rows, 0) 
        . " | Offset: "
        . number_format($offset, 0) 
        . " | Matched: " 
        .  number_format($matched,0) 
        . " | Skipped: " 
        . number_format($skipped, 0) 
        . " ]</p>";




    if($offset + 1 < count($rows) && !$stopped_for_choice){
        // we need to call again
        $uri = "matching.php?matching_mode=" . @$_GET['matching_mode'] . "&offset=$offset";
        echo "<script>window.location = \"$uri\"</script>";
        //echo "<a href=\"$uri\">Next Page $offset - " . count($rows) ." - $stopped_for_choice</a>";
    }

    // rows are now updated - write them to the file.
    // ready for the next call
    $out = fopen($output_file_path, 'w');
    foreach($rows as $row) fputcsv($out, $row);
    fclose($out);

    echo '</div>';

} // we are in matching mode
?>

<h2>1. Data Upload</h2>

<?php

// do we have data to play with
if(file_exists($input_file_path)){
    $input_file_size = number_format(filesize($input_file_path), 0);
?>

<p><strong>Input data size:</strong> <?php echo $input_file_size ?> bytes.</p>
<p><strong>Input data type:</strong> <?php echo $_SESSION['data_type'] ?>.</p>

<p><a href="matching.php?delete_data=true" style="color: red;">Clear all data and start again.</a></p>

<?php }else{ // no input file present ?>

<p>You need to submit some data to work with. This can either be via cut and paste or file upload.</p>
<h3><span style="color: green">EITHER</span> Cut'n'Paste Data</h3>
<p>Each name should be on a new line. Try cutting and pasting a column from a spreadsheet if you like.</p>
<p>
<form action="matching.php" method="POST">
  <textarea cols="60" rows="10" name="name_data"></textarea>
  <br/>
  <input type="submit" value="Submit Data" name="submit">
</form>
</p>

<h3><span style="color: green">OR</span> Upload a CSV File</h3>
<p>The first row of the CSV file will be taken as the column headers for the file.</p>
<p>
<form action="matching.php" method="POST" enctype="multipart/form-data">
  Select file to upload:
  <input type="file" name="input_file" id="input_file">
  <input type="submit" value="Upload CSV File" name="submit">
</form>
</p>

<?php } // end no input file present?>

<h2>2. Matching Parameters</h2>
<p>Set the parameters you'd like to use during the matching phase.</p>

<p>
<form action="matching.php" method="GET">
    <input type="hidden" name="update_matching_params" value="true" />
<table>
<?php 

    if(isset($_SESSION['data_type']) && $_SESSION['data_type'] == 'CSV') { 
    
        // get the first line of the file
        $in_file = fopen($input_file_path, 'r');
        $header = fgetcsv($in_file);
        fclose($in_file);
?>
<tr>
    <th style="text-align: right">Names Column:</th>
    <td>
        <select name="name_col_index">
            <option value="-1">~ Pick Column ~</option>
<?php
    for($i = 0; $i < count($header); $i++){
        $selected = $i == @$_SESSION['matching_params']['name_col_index'] ? 'selected' : '';
        echo "<option value=\"$i\" $selected >{$header[$i]}</option>";
    }
?>
        </select>
    </td>
    <td>The data supplied is in a CSV file. You must specify which column contains the names.</td>
</tr>

<?php
} // end data type check

?>


<tr>
    <th style="text-align: right">Interactive mode:</th>
    <td style="text-align: center"><input type="checkbox" name="interactive" value="true" <?php echo @$_SESSION['matching_params']['interactive'] ? 'checked' : '' ?>/></td>
    <td>If no unambiguous match is found but some candidate names are found then stop and manually pick from the list of candidates. If this isn't selected then rows without unambiguous matches will be skipped.</td>
</tr>

<tr>
    <th style="text-align: right">Check homonyms:</th>
    <td style="text-align: center"><input type="checkbox" name="homonyms" value="true" <?php echo @$_SESSION['matching_params']['homonyms'] ? 'checked' : '' ?>/></td>
    <td>If a single, exact match of name and author string is found but there are other names with the same letters but a different author string stop/skip.</td>
</tr>

<tr>
    <th style="text-align: right">Check ranks:</th>
    <td style="text-align: center"><input type="checkbox" name="ranks" value="true" <?php echo @$_SESSION['matching_params']['ranks'] ? 'checked' : '' ?>/></td>
    <td>If a precise match of name and author string is found and it is possible to extract the rank from the name but the rank doesn't match then stop/skip.</td>
</tr>
<tr>
    <td colspan="3" style="text-align: right"><input type="submit" value="Set Parameters" name="submit"></td>
</tr>
</table>

</form>
</p>

<h2>3. Matching Run</h2>
<p>Actually run the matching process.</p>
<p>
<form action="matching.php" method="GET">
    <input type="hidden" name="offset" value="<?php echo @$_GET['offset'] ?>" />
<table>

<tr>
    <th style="text-align: right">Only unexamined:</th>
    <td><input type="radio" name="matching_mode" value="unmatched" checked="true" /></td>
    <td>Only try and match rows that haven't been matched or skipped before.</td>
</tr>
<tr>
    <th style="text-align: right">Skipped and unmatched:</th>
    <td><input type="radio" name="matching_mode" value="skipped" /></td>
    <td>Try and match rows that haven't been attempted and those that were previously skipped.</td>
</tr>
<tr>
    <td colspan="3" style="text-align: right">

<?php
    // check if we have enough information to allow matching to start
    $disabled = "";
    $message = "";
    if(!file_exists($input_file_path)){
        $disabled = "disabled";
        $message = "You must upload some data first: ";
    } 
    if(@$_SESSION['data_type'] == 'CSV' && @$_SESSION['matching_params']['name_col_index'] < 0){
        $disabled = "disabled";
        $message = "You must specify the name column above: ";
    } 
    
?>
    <span style="color: red;"><?php echo $message ?></span>
    <input type="submit" value="Run Matching" name="submit" <?php echo $disabled ?> />
    </td>
</tr>
</table>
  
</form>
</p>

<h2>4. Download</h2>
<p><a href="<?php echo $output_file_path ?>">Download Results</a></p>

<div>
<a name="instructions"><h2>Instructions</h2></a>
<p>
    This tool is for attaching WFO name IDs to your data based on the name string you have.
    You submit your data,
    run the matching process
    and download a CSV file with three additional columns in:
</p>
<ol>
    <li><strong>wfo_id</strong> The unique 10 digit WFO ID for the name.</li>
    <li><strong>wfo_full_name</strong> The full version of the name as it occurs in the WFO Plant list as plain text.</li>
    <li><strong>wfo_check</strong>
        If the name is placed in the current classification then the full path to the name.
        If the name hasn't been placed in the classification then either 
        UNPLACED (An expert has not expressed an opinion on the taxonomy yet.)
        of
        DEPRECATED (Can't be placed in the classification - do not use.)

    </li>
</ol>

<h3>Name strings</h3>
<p>
     The names you submit must be complete and include the authors.
     They should have one, two or three "name words".
     You will get unreliable results if you include varieties of subspecies (four name words).
     Ranks (either in full or using common abbreviations) are OK to include.
     Hybrid symbols will be stripped out at the start of the process.
</p>

<h3>Submitting data</h3>

<p>
    The easiest way to get started is to cut and paste a 
    column of names into the text box in the form and click "Submit Data".
    If you have the authors in a second column then it is OK to copy the two columns into the text box.
    The matching process will merge them.
</p>

<p>
    Once you have tried it out with a few names cut and paste into the text box you could
    try uploading a CSV file. All the columns in the CSV file will be returned to you in 
    the results so this technique can be used to bind WFO IDs to your local IDs 
    and other data. If you have the name and authors in separate columns you 
    must combine them into a single column before upload.
</p>

<h3>Setting parameters</h3>

<p>
    The matching process can be parametised. 
    The default values are usually OK to start with but if you have uploaded a CSV file
    you need to specify the column that contains the name strings at a minimum.
</p>


<p> 
    Recommendation: Do not turn on interactive mode the first time you run the matching
    process. This will give an idea of how dirty the data is and how much work is needed 
    to get to 100% matching using interactive mode.
</p>

<h3>Doing a matching run</h3>

<p>
    Once you have submitted data and set the parameters you can do a matching run.
    If you have submitted a large file then the page may refresh multiple times 
    so be patient.
</p>

<p>
    You can do multiple matching runs on the same data, perhaps one with interactive mode off
    followed by a run with it turned on.
</p>

<h3>Downloading data</h3>

<p>
    You can download the results of the matching at any time after you first run the matching process.
    <strong>To avoid data loss download your data frequently. </strong> Data is only stored as long
    as your session lasts. If you walk away and come back later it may be gone!
    You can upload the file you have downloaded if you want to continue an earlier session.
</p>

<h3>Big datasets</h3>

<p>
    No limit is set on the number of names that can be matched in one go, beyond the filesize upload limit.
    The process works well with CSV files with tens of thousands of rows.
    The process will probably fail with more than one hundred thousand rows.
</p>

<p>
    If you have a large number of names to match it is highly recommend you break your work into 
    batches of logical batches of a few tens of thousands of names each. This is worth doing for the human
    factor alone. A large dataset may contain more ambiguous names than a human is able to disambiguate in one session.
</p>

<p>
    If you frequently need to rematch many thousands of names please consider installing a local
    copy of this matching service (see <a href="index.php#scale">Scalability and Performance</a>). This is a shared resource and if the server is stressed 
    it will slow down access to other users.
</p>

<?php

require_once('footer.php');

function render_choices($response){
    
    echo "<h3>Interactive Mode: Choice</h3>";
    echo "<p>Pick one of the candidate names to match or skip this name for now.</p>";

    echo '<form method="GET" action="matching.php" >';
    echo '<input type="hidden" name="offset" value="' . @$_GET['offset'] .'" />';
    echo '<input type="hidden" name="matching_mode" value="'. @$_GET['matching_mode'] .'" />' ;

    echo '<table style="width: 100%">';
    echo "<tr>";
    echo '<th style="text-align: right" >Name String:</th>';
    echo "<td>{$response->inputString}</td>";
    echo '<td style="text-align: right" >Skip <input type="radio" name="chosen_wfo" value="SKIP" checked /></td>';
    echo "</tr>";
    //echo "<pre>"; print_r($response->narrative); echo "</pre>"; 

    for($i = 0; $i < count($response->candidates); $i++){
        $candidate = $response->candidates[$i];
        if(!$candidate) continue;
        echo "<tr>";
        echo '<th style="text-align: right; vertical-align: top;" >Candidate ' . ($i +1) . ':</th>';
        echo "<td>";
        echo "<a href=\"https://list.worldfloraonline.org/{$candidate->getWfoId()}\" target=\"wfo\">{$candidate->getFullNameStringHtml()}</a>";
        echo " [{$candidate->getNomenclaturalStatus()}]";
        echo "<br/>";
        echo $candidate->getCitationMicro();
        
        // put in some nomenclatural references
        foreach($candidate->getNomenclaturalReferences() as $ref){
            // we suppress plantlist links!
            if(preg_match('/www.theplantlist.org/', $ref->uri)) continue;
            $flag = ucfirst($ref->kind);
            echo "<br/>$flag: <a href=\"{$ref->uri}\" target=\"{$ref->kind}\">{$ref->label}</a>";
        }

        echo "</td>";
        echo '<td style="text-align: right" >'. $candidate->getWfoId() .'&nbsp;<input type="radio" name="chosen_wfo" value="'. $candidate->getWfoId() . '" /></td>';
        echo "</tr>";
    }

    // paste in wfo
    echo "<tr>";
    echo "<td colspan=\"2\" style=\"text-align: right; vertical-align: top;\">Paste in a WFO ID you found by searching some other way</td>";
    echo '<td style="text-align: right" >
        <input type="text" name="custom_wfo"  /> 
        <input type="radio" name="chosen_wfo" value="CUSTOM" />
    </td>';
    echo "</tr>";



    // submit form
    echo "<tr>";
    echo '<td colspan="3" style="text-align: right" >
        <input type="submit" value="Submit"  /> 
    </td>';
    echo "</tr>";
    echo "</table>";
    echo "</form>";

    // print out the matching narrative to explain what we have
    echo "<h3>Matching Narrative</h3>";
    echo "<ol>";
    foreach($response->narrative as $story){
        echo "<li>$story</li>";
    }
    echo "</ol>";


    
}

?>

