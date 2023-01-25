<?php
include('config.php');
include('../include/NameMatcher.php');

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

// they are deleting the data
if(isset($_GET['delete_data']) && $_GET['delete_data'] == 'true'){
    unlink($input_file_path);
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

?>
<!DOCTYPE html>
<html>
<head>
    <title>WFO Plant List: Name Matching</title>
    <style>
        body{
            font-family: sans-serif;
            padding: 2em;
        }
        table, td, th{
            text-align:left;
            border: 1px solid black;
            border-collapse: collapse;
            padding: 0.5em;
        }
        table{
            width: 60em;
        }
        th{
            white-space: nowrap;
        }
        div{
            width: 58em;
            border: solid 1px gray; 
            padding: 1em;
        }
    </style>
</head>
<body>

<h1>WFO Plant List: Name Matching Tool</h1>

<?php
if(@$_GET['matching_mode']){
    echo '<div>';
    echo '<h2>Matching in progress.</h2>';
    
    // does the output file exist?
    if(!file_exists($output_file_path)){
        // no output file so create it
        $in = fopen($input_file_path, 'r');
        $out = fopen($output_file_path, 'w');

        if($_SESSION['data_type'] == 'CSV'){
            $header = fgetcsv($in);
        }else{
            $header = array('input_name_string');
        }
        array_unshift($header, 'wfo_id', 'wfo_full_name', 'wfo_check');
        fputcsv($out, $header);

        if($_SESSION['data_type'] == 'CSV'){
            while($line = fgetcsv($in)){
                array_unshift($line, '', '', '');
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

    // work through the rows (skipping the header)
    $written_choice = false; // whether we have written the results of a choice box yet
    
    // which column is the name in?
    $name_index = $_SESSION['data_type'] == 'CSV' ? $_SESSION['matching_params']['name_col_index'] + 3 : 3;

    // some stats to display 
    $total_rows = count($rows) -1; 
    $matched = 0;
    $skipped = 0; 
    
    for($i = 1; $i < count($rows); $i++){

        $row = $rows[$i];

        if(preg_match('/^wfo-[0-9]{10}$/', $row[0])) $matched++;
        if($row[0] == 'SKIPPED') $skipped++;

        // we might be being called with results of a choice box
        // in which case we skip through till we get to the marker in the row
        // and fill in the values there before continuing
        if(@$_GET['chosen_wfo'] && !$written_choice){
            if($row[0] == 'CHOICE'){
                $row[0] = $_GET['chosen_wfo'];
                $row[1] = $_GET['chosen_name'];
                $row[2] = $_GET['chosen_check'];
                $rows[$i] = $row;
                $written_choice = true;
            }
            continue; // haven't reached the choice row yet
        } 

        if(
            !$row[0] // no wfo id yet
            ||
            ($_GET['matching_mode'] == 'skipped' && $row[0] == 'SKIPPED')
            ||
            $_GET['matching_mode'] == 'all'
        ){

            $config = new class{}; // matching configuration object
            $matcher = new NameMatcher($config);

            $response = $matcher->match($row[$name_index]);

            if($response->match && !$response->candidates){
                // we have an exact match with no ambiguity
                $row[0] = $reponse->match->getWfoId();
                $row[1] = $reponse->match->getFullNameStringPlain();
                $row[2] = $reponse->match->getWfoPath();
            }elseif($response->match && $response->candidates){
                // we have an exact match AND some ambiguity
                // this will be homonyms or ranks based
                if(@$_SESSION['matching_params']['interactive']){
                    render_choices($response);
                    break;
                }
            }elseif(!$response->match && $response->candidates){
                // no exact match but some candidates to look at
                if(@$_SESSION['matching_params']['interactive']){
                    // render some choice boxes
                    render_choices($response);
                    break;
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
            if(preg_match('/^wfo-[0-9]{10}$/', $new_row[0])) $matched++;
            if($new_row[0] == 'SKIPPED') $skipped++;
            if($new_row[0] == 'CHOICE') break; // stop! We have rendered a choice box

        }
    
    }

    // write out some stats on how we are doing
    echo "<p>[Total rows: $total_rows | Matched: $matched | Skipped: $skipped ]</p>";

    // rows are now updated - write them to the file.
    // ready for the next call
    $out = fopen($output_file_path, 'w');
    foreach($rows as $row) fputcsv($out, $row);
    fclose($out);

    echo '</div>';
}
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

<p>You need to upload some data to work with. This can either be via cut and paste or file upload.</p>
<h3>1.1 Cut'n'Paste Data</h3>
<p>Each name should be on a new line. Try cutting and pasting a column from a spreadsheet if you like.</p>
<p>
<form action="matching.php" method="POST">
  <textarea cols="60" rows="10" name="name_data"></textarea>
  <br/>
  <input type="submit" value="Submit Data" name="submit">
</form>
</p>

<h3>1.2 Upload a CSV File</h3>
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
    <th style="text-align: right">Fangle with "ex" authors:</th>
    <td style="text-align: center"><input type="checkbox" name="ex" value="true" <?php echo @$_SESSION['matching_params']['ex'] ? 'checked' : '' ?>/></td>
    <td>WFO Plant List does not use ex in author strings. Try and do something sensible by comparing the strings before and after the ex in the supplied authors.</td>
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
    <th style="text-align: right">Start again:</th>
    <td><input type="radio" name="matching_mode" value="all" /></td>
    <td>Rematch everything, even if it already has a WFO ID associated with it.</td>
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


</body>
</html>

<?php

function render_choices($response){
    echo "Choices";
    print_r($response);
}

?>

