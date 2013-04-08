<?php
// Get the form substrate
include_once("baseforms.php");
// Connect to database
include_once("neaot.com/database_w.php");

$debugging = true;

$form = new Form("neaotcalendarform");

$idfield = new NumberFormField("id", "ID");
$idfield->setReadonly(true);
$form->addField($idfield);
$form->addField(new DateFormField("start", "Date"));
//$form->addField(new DateFormField("end", "End Date"));
//$form->addField(new DaytimeFormField("start_time", "Start Time"));
//$form->addField(new DaytimeFormField("end_time", "End Time"));
//$form->addField(new FormField("subject","Title"));
//$form->addField(new PhoneFormField("phone", "Phone"));
//$form->addField(new EmailFormField("email", "Email"));
$form->addField(new TextAreaFormField("entry", "Details"));

if(array_key_exists('id', $_GET)) {

	$id = clean($_GET['id']);
	
	$query = "SELECT id, DATE_FORMAT(start,'%c/%e/%y') as start, DATE_FORMAT(end,'%c/%e/%y') as end, TIME_FORMAT(start_time, '%l:%i') as start_time, TIME_FORMAT(end_time, '%l:%i %p') as end_time, subject, entry FROM neaot_calendar WHERE id = '{$id}'";         
		  // Make the query, report any errors
		  if(! $result = mysql_query($query, $db)) {
			echo "query error";
			// PTW's error reporter from database.php
			print_error_and_exit();
		  }
	$source = mysql_fetch_array($result);
	$form->parseValues($source);
}



///
// Handle validation, insertion into database, and acknowledgement
//
if ($_POST['process'] == 1) {
  // If you come here from a submit, try parsing out the values
  if ($form->parseValues()) {
    // We got a valid form!
	
	$query = "REPLACE  neaot_calendar SET " . $form->SQLform();         
      // Make the query, report any errors
      if(! $result = mysql_query($query, $db)) {
        echo "query error";
        // PTW's error reporter from database.php
        print_error_and_exit();
      }
	  


    // Go back to listing page
    header("location: head.php");
  }


  // Otherwise, we fall through and re-display the form, with any
  // errors highlighted
}

	

	
// Now output the real document
?>
<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>NEAOT : News from Head :: Edit/Delete Entry</title>
<link href="/neaot0606.css" rel="stylesheet" type="text/css" media="all" />

<style type="text/css">
/* <![CDATA[ */

form table { width: 700px; border: none; margin: 5px 0; background-color: white; border-collapse: collapse;}
			
form td, form th { border: none; margin: 0; padding: 5px; text-align: left;}

form tbody, form th { border: none; }

.required {color: #903}
.errortext {color: #903}
.hint {color:#666666}

/* Column widths */
.label { width: 110px; background-color:#ffffff}
.field { width: 300px;background-color:#ffffff }
.field input {width: 250px; }
.annotation { width: 15px; background-color: #ffffff }

/* inputtext for the comments textarea */
.field textarea { width: 525px; height: 20em;}
tr.entry td.label {vertical-align:top}
tr.interest td.label {vertical-align:top}

.buttons input {width: auto; }
.buttons {text-align: center}

			/* ]]> */
</style>


</head>

<body>

<div class="calendarcontainer">

<p class="title">News from Head: Edit Entry</p>

<p><strong>Markdown Syntax</strong><br>	
Bold example:  **this text is bold**<br>	
Link example: [link text](http://www.neaot.com)<br>
More about <a href="http://daringfireball.net/projects/markdown/basics" title="Markdown Basics" target="_blank">Markdown</a> </p>	

<?php
// Here comes the form
echo $form->HTMLForm();
?>

</div>
</body>
</html>
