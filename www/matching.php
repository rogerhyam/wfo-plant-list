<?php
include('config.php');

$messages = array();

$file_dir = 'matching_cache/' . session_id() . "/";
if(!file_exists($file_dir)) mkdir($file_dir);
$input_file_path = $file_dir . "input.csv";

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
}

if(isset($_GET['update_download_params']) && $_GET['update_download_params'] = 'true'){
    $_SESSION['download_params'] = $_GET;
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
    </style>
</head>
<body>
<?php

?>

<h1>WFO Plant List: Name Matching Tool</h1>

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

<table>

<tr>
    <th style="text-align: right">Names Column:</th>
    <td>
        <select>
            <option>~ Pick Column ~</option>

        </select>

    </td>
    <td>The data supplied is in a CSV file. You must specify which column contains the names.</td>
</tr>

<tr>
    <th style="text-align: right">Interactive mode:</th>
    <td style="text-align: center"><input type="checkbox" name="interactive" /></td>
    <td>If no unambiguous match is found but some candidate names are found then stop and manually pick from the list of candidates. If this isn't selected then rows without unambiguous matches will be skipped.</td>
</tr>

<tr>
    <th style="text-align: right">Check homonyms:</th>
    <td style="text-align: center"><input type="checkbox" name="homonyms" /></td>
    <td>If a single, exact match of name and author string is found but there are other names with the same letters but a different author string stop/skip.</td>
</tr>

<tr>
    <th style="text-align: right">Check ranks:</th>
    <td style="text-align: center"><input type="checkbox" name="ranks" /></td>
    <td>If a precise match of name and author string is found and it is possible to extract the rank from the name but the rank doesn't match then stop/skip.</td>
</tr>

<tr>
    <th style="text-align: right">Fangle with "ex" authors:</th>
    <td style="text-align: center"><input type="checkbox" name="ex_authors" /></td>
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
    <td colspan="3" style="text-align: right"><input type="submit" value="Run Matching" name="submit"></td>
</tr>
</table>
  
</form>
</p>

<h2>4. Output Columns</h2>
<p>Select the columns you'd like included when you download the results.</p>
<p>
<form action="matching.php" method="GET">

<table>

<tr>
    <th style="text-align: right">wfo_id:</th>
    <td><input type="checkbox" name="wfo_id"  checked disabled="true" /></td>
    <td>This column is always included. It will contain either the matched WFO ID, 'SKIPPED' or be blank.</td>
</tr>
<tr>
    <th style="text-align: right">full_name:</th>
    <td><input type="checkbox" name="full_name"  <?php echo isset($_SESSION['download_params']['full_name']) ? 'checked': ''; ?>  /></td>
    <td>The full name including the authors.</td>
</tr>
<tr>
    <th style="text-align: right">full_name_html:</th>
    <td><input type="checkbox" name="full_name_html"  <?php echo isset($_SESSION['download_params']['full_name_html']) ? 'checked': ''; ?> /></td>
    <td>The full name including the authors with HTML mark up tags for italics etc.</td>
</tr>
<tr>
    <th style="text-align: right">wfo_role:</th>
    <td><input type="checkbox" name="wfo_role"  <?php echo isset($_SESSION['download_params']['wfo_role']) ? 'checked': ''; ?> /></td>
    <td>The role this name plays in the current classification: accepted, synonym, unplaced or deprecated.</td>
</tr>
<tr>
    <th style="text-align: right">accepted_wfo_id:</th>
    <td><input type="checkbox" name="accepted_wfo_id"  <?php echo isset($_SESSION['download_params']['accepted_wfo_id']) ? 'checked': ''; ?>  /></td>
    <td>If the name is a synonym this is the WFO ID of the accepted name for the taxon.</td>
</tr>
<tr>
    <th style="text-align: right">accepted_full_name:</th>
    <td><input type="checkbox" name="accepted_full_name"  <?php echo isset($_SESSION['download_params']['accepted_full_name']) ? 'checked': ''; ?>  /></td>
    <td>The full name for the accepted name.</td>
</tr>
<tr>
    <th style="text-align: right">accepted_full_name_html:</th>
    <td><input type="checkbox" name="accepted_full_name_html"  <?php echo isset($_SESSION['download_params']['accepted_full_name_html']) ? 'checked': ''; ?>  /></td>
    <td>The full name for the accepted name with HTML mark up.</td>
</tr>
<tr>
    <th style="text-align: right">parent_wfo_id:</th>
    <td><input type="checkbox" name="parent_wfo_id"  <?php echo isset($_SESSION['download_params']['parent_wfo_id']) ? 'checked': ''; ?>  /></td>
    <td>The WFO ID of the parent taxon if this is an accepted name.</td>
</tr>
<tr>
    <th style="text-align: right">basionym_wfo_id:</th>
    <td><input type="checkbox" name="basionym_wfo_id"  <?php echo isset($_SESSION['download_params']['basionym_wfo_id']) ? 'checked': ''; ?>  /></td>
    <td>The WFO ID of the basionym of this name if known.</td>
</tr>
<tr>
    <th style="text-align: right">wfo_path:</th>
    <td><input type="checkbox" name="wfo_path"  <?php echo isset($_SESSION['download_params']['wfo_path']) ? 'checked': ''; ?>  /></td>
    <td>A list of names showing the placement of this name in the current classification.</td>
</tr>
<tr>
    <th style="text-align: right">stable_uri:</th>
    <td><input type="checkbox" name="stable_uri"  <?php echo isset($_SESSION['download_params']['stable_uri']) ? 'checked': ''; ?>  /></td>
    <td>A stable URI for the name that will always link to its most recent placement.</td>
</tr>

<tr>
    <td colspan="3" style="text-align: right"><input type="submit" value="Generate Download File" name="submit"></td>
</tr>
</table>
  
</form>
</p>


<h2>5. Download</h2>
<p><a href="#">Download Results</a></p>


</body>
</html>


