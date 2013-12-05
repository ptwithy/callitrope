<?php

/**
 * Copyright (c) 2012, 2013 callitrope
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 * Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

///
// General form classes
//

include_once("subroutines.php");

function PHPtoSQL($val) {
  // Don't quote numbers
  if (is_numeric($val)) {
    if (intval($val) == $val) {
      return intval($val);
    } else {
      return floatval($val);
    }
  } else {
    return "'" . addslashes($val) . "'";
  }
}

// See if our browser has HTML5 features like `placeholder`
// Webkit = Safari, Chrome; Presto = Opera
$html5 = preg_match("/webkit|presto/i", $_SERVER['HTTP_USER_AGENT']);
// Some stuff only looks good on mobile
$mobile = preg_match("/ipad|iphone|android/i",  $_SERVER['HTTP_USER_AGENT']);

// Have I told you how much PHP sucks?  Apparently you cannot
// define anonymous inner functions?  WTF?
function is_field ($f) { return $f instanceof FormField; };
function order ($a, $b) { return $a->priority - $b->priority; };

function fieldInternal($name, $description=null, $type=null, $optional=false, $options=NULL) {
  $namemap = array("email", "number", "choice", "state", "zip", "postal", "country", "phone", "cell", "birth", "date", "daytime", "time", "file", "image", "picture");
  if ($type == null) {
    foreach ($namemap as $n) {
      if (stristr($name, $n)) {
        $type = $n;
        break;
      }
    }
  }

  switch ($type) {
    case "email":
      return new EmailFormField($name, $description, $optional, $options);
    case "number":
      return new SimpleNumberFormField($name, $description, $optional, $options);
    case "state":
      return new StateFormField($name, $description, $optional, $options);
    case "zip":
      return new ZipFormField($name, $description, $optional, $options);
    case "postal":
      return new PostalCodeFormField($name, $description, $optional, $options);
    case "country":
      return new CountryFormField($name, $description, $optional, $options);
    case "phone":
    case "cell":
      return new PhoneFormField($name, $description, $optional, $options);
    case "date":
      return new DateFormField($name, $description, $optional, $options);
    case "birth":
    case "birthdate":
      return new BirthdateFormField($name, $description, $optional, $options);
    case "daytime":
      return new DaytimeFormField($name, $description, $optional, $options);
    case "text":
    case "area":
    case "textarea":
      return new TextAreaFormField($name, $description, $optional, $options);
    case "button":
    case "radio":
    case "radiobutton":
    case "choice":
    case "single":
      return new SimpleRadioFormField($name, $description, $optional, $options);
    case "check":
    case "checkbox":
    case "multiple":
      return new SimpleCheckboxFormField($name, $description, $optional, $options);
    case "menu":
      return new SimpleMenuFormField($name, $description, $optional, $options);

    case "file":
      return new FileFormField($name, $description, $optional, $options);
    case "image":
    case "picture":
      return new ImageFormField($name, $description, $optional, $options);

    default:
      return new FormField($name, $description, $optional, $options);
  }
}

///
// Create a field
//
// $name - name of the field
// $type - type of the field, can be inferred from some obvious names
// $description - defaults to name with underscores removed and capitalized
function field($name, $description=null, $type=null, $options=null) {
  return fieldInternal($name, $description, $type, false, $options);
}
///
// Create an optional field
//
function optField($name, $description=null, $type=null, $options=null) {
  return fieldInternal($name, $description, $type, true, $options);
}

  
///
// The basic form object
//
// This holds all the information about the form, knows how to
// generate the HTML for the form, how to parse the values from an SQL
// query array or the POST array, and how to format the SQL to store
// the values
class Form {
  // The name of the form
  var $name;
  // The destination for submit
  var $action;
  // The method for submit
  var $method;
  // Whether to use divs
  var $usedivs;
  // Error array from parsing
  var $errorMessages;
  // Whether the form is complete or not
  var $complete = false;
  // The auto-generated process token (1 is the back-compatible value, overridden by initialize)
  var $process = 1;
  
  // Array of fields to auto-add
  var $columns;
  // Array of choices
  var $enums;
  
  ///
  // Compute the choices for an enum field
  function choicesForField($field) {
    return array_key_exists($field, $this->enums) ? $this->enums[$field] : null;
  }
  
  // Define choices, possibly overriding defaults from DatabaseForm
  function addChoice($field, $choice) {
    $this->enums[$field] = $choice;
  }
  
  function addChoices($choices) {
    foreach ($choices as $enum => $choice) {
      $this->addChoice($enum, $choice);
    }
  }
  
  // Ordering is important
  var $sqlTypeMap = array (
    'text' => 'area'
    ,'int' => 'number'
    ,'decimal' => 'number'
    ,'datetime' => 'datetime'
    ,'date' => 'date'
    ,'time' => 'time'
    ,'blob' => 'image'
  );
    
      
  function autoAddFields($include=null, $omit= null) {
    global $debugging;
    $columns = $this->columns;
    if ($include !== null) {
      // filter columns to add
      $columns = array_intersect_key($columns, array_flip($include));
    }
    if ($omit !== null) {
      $columns = array_diff_key($columns, array_flip($omit));
    }
    if ($debugging > 1) {
      echo "<pre>\$columns => " . print_r($columns) . "<pre>";
    }
    foreach ($columns as $field => $desc) {
      if ($debugging > 2) {
        echo "<pre>{$field} => " . print_r($desc) . "<pre>";
      }
      $type = null;
      $e = $this->choicesForField($field);
      if (is_array($e)) {
        if ($debugging > 2) {
          echo "<pre>{$field} choices => " . print_r($e) . "<pre>";
        }
        if (array_key_exists(0, $e)) {
          $type = "checkbox";
        } elseif (in_array("", $e)) {
          $type = "menu";
        } else {
          $type = "radio";
        }
      }
      if ($type === null) {
        $sqltype = $desc->Type;
        foreach ($this->sqlTypeMap as $s => $t) {
          if (stristr($s, $sqltype)) {
            $type = $t;
            break;
          }
        }
      }
      $f = field($field, null, $type);
      if (array_key_exists($f->id, $this->fields)) {
        // Don't add fields that have already been added
      } else if (($f->id == $this->idname) ||
                 ($f->id == $this->createdname) ||
                 ($f->id == $this->modifiedname)) {
        // Don't add any of the special database fields
      } else {
        // Ok, add it
        $this->addField($f);
      }
      if ($debugging > 1) {
        echo "<pre>{$field} => " . $f . "<pre>";
      }
    }
  }
  
  // This holds all the fields in the form.  Each time you create
  // a field, it will be added to this array.
  var $fields;

  // Sets the order of the fields in the form
  function setFieldOrder($ordering) {
    $newfields = array();
    $oldfields = $this->fields;
    foreach ($ordering as $field) {
      $newfields[$field] = $oldfields[$field];
    }
    foreach ($oldfields as $field => $value) {
      if (!array_key_exists($field, $newfields)) {
        $newfields[$field] = $value;
      }
    }
    $this->fields = $newfields;
  }

  // These allow a form to have multiple sections.  Each section is
  // represented by a table, which allows different styling between
  // tables.  By default there will be a single section that has the
  // same name as the form.
  var $last = 0;
  var $sectionName;
  var $sections;

  // Define a new form.  Parameters are
  // @param $name:String The name of the form
  // @param $action:String The destination page of submit
  // @param $method:String (optional, default "post") Whether to post or
  // get
  // @param $usedivs:boolean (optional, default `false`) Whether to use
  // divs instead of table elements for layout
  function Form ($name, $action="", $method="post", $usedivs=false) {
    $this->name = $name;
    $this->action = empty($action) ? $_SERVER['PHP_SELF'] : $action;
    $this->method = $method;
    $this->usedivs = $usedivs;
    $this->errorMessages = array();
    $this->enums = array();
    $this->fields = array();
    $this->sections = array();
    $this->startSection($name);
  }
  
  // Initialize the form
  //
  // Must be called before any headers are output
  // Currently only used by DatabaseForm
  function initialize() {
    // We need some session variables to track state
    ptw_session_start();
    // Retrieve the process token
    if (isset($_SESSION['process'])) {
      $this->process = $_SESSION['process'];
    } else {
      // First time:  clear the back-compatible value
      $this->process = NULL;
    }
    $fields = array_filter($this->fields, 'is_field');
    foreach ($fields as $field) {
      $field->initialize();
    }  
  }

  // Output anything the form might need in the <head> tag
  function head() {
    $fields = array_filter($this->fields, 'is_field');
    foreach ($fields as $field) {
      $field->head();
    }  
  }
  
  
  function isValidPost() {
    return array_key_exists('process', $_POST) && ($_POST['process'] == $this->process);
  }
  
  // Give fields a chance to clean up any temporary storage, e.g., files/images
  function finalize() {
    $fields = array_filter($this->fields, 'is_field');
    foreach ($fields as $field) {
      $field->finalize();
    }  
  }

  // Takes a string that will be inserted into the table as a footer
  function finishSection($footer='') {
    if (! empty($footer)) {
      $this->fields[] = $footer;
    }
    if ($this->last < count($this->fields)) {
      $this->sections[$this->sectionName] = array_slice($this->fields, $this->last);
      $this->last = count($this->fields);
    }
  }

  // Takes a string that will be inserted into the table as a header
  function startSection($name, $header='') {
    $this->finishSection();
    $this->sectionName = $name;
    if (! empty($header)) {
      $this->fields[] = $header;
    }
  }

  function addField($formField) {
    if ($formField instanceof FormField) {
      $this->fields[$formField->id] = $formField;
      $formField->setForm($this);
    } else {
      $this->fields[] = $formField;
    }
  }
  
  function addFields($fields) {
    foreach ($fields as $f) {
      $this->addfield($f);
    }
  }

  function field($fieldName, $instance=NULL) {
    $name = $fieldName . ($instance == NULL ? '' : $instance);
    if (array_key_exists($name, $this->fields)) {
      return $this->fields[$name];
    }
  }

  function fieldHasValue($fieldName, $instance=NULL) {
    $field = $this->field($fieldName, $instance);
    return $field && $field->hasvalue();
  }

  function fieldValue($fieldName, $instance=NULL) {
    $field = $this->field($fieldName, $instance);
    if ($field && $field->hasvalue()) {
      return $field->choice();
    }
  }

  function fieldHTMLValue($fieldName, $instance=NULL) {
    $field = $this->field($fieldName, $instance);
    if ($field && $field->hasvalue()) {
      return $field->HTMLValue();
    }
  }

  function fieldSQLValue($fieldName, $instance=NULL) {
    $field = $this->field($fieldName, $instance);
    if ($field) {
      // [2012-08-12 ptw] we know SQLValue returns the right thing if there
      // is no value
      return $field->SQLValue();
    }
  }

  function fieldTextValue($fieldName, $instance=NULL) {
    $field = $this->fields[$fieldName];
    if ($field && $field->hasvalue()) {
      return $field->TextValue();
    }
  }

  // Returns a string representing the HTML version of the form
  // enctype="multipart/form-data" required to allow file uploads (for FileFormField)
  function HTMLForm($process=null, $buttons=null) {
    global $debugging;
    // We allow passing in a token, but discourage it
    if ($process == null) {
      if ($this->process == 1) {
        // initialize was never called, we are back-compatible mode
        $process = $this->process;
      } else {
        // We generate a new process token every time -- this prevents re-submitting
        // or forging submissions
        $process = $_SESSION['process'] = generatePassword();
      }
    } else {
      if ($debugging) { trigger_error("Supplying \$process={$process} is deprecated"); }
      $_SESSION['process'] = $process;
    }
    
  
    if (! $buttons) {
      $buttons =
<<<QUOTE
      <input type="submit" name="submitButton" value="Submit Form">&nbsp;&nbsp;&nbsp;<input type="reset" value="Reset Form">
QUOTE;
    }
    $html = "";
    if (count($this->errorMessages) > 0) {
      $html .=
<<< QUOTE

  <div class='errortext'>
QUOTE;
      foreach ($this->errorMessages as $msg) {
        $html .=
<<<QUOTE

    <p class="errortext">{$msg}</p>
QUOTE;
      }
      $html .=
<<<QUOTE

  </div>
QUOTE;
    }
    $html .=
<<<QUOTE

  <form class="{$this->name}" name="{$this->name}" method="{$this->method}" action="{$this->action}" enctype="multipart/form-data">
QUOTE;
    $html .= $this->HTMLFormTable();
    $html .=
<<<QUOTE

    <input type="hidden" name="process" value="{$process}">
    <div class="buttons">
      {$buttons}
    </div>
  </form>
QUOTE;
    return $html . "\n";
  }

  // Returns a string representing the HTML version of the form table
  // Override this to build multiple tables with different layouts
  function HTMLFormTable() {
    // Ensure finished
    $this->finishSection();
    $element = $this->usedivs ? "div" : "table";
    $html = "";
    foreach ($this->sections as $name => $fields) {
      $html .=
<<<QUOTE

    <{$element} class="{$name}">
QUOTE;
    if (! $this->usedivs) {
      $html .=
<<<QUOTE

      <col class="label"><col class="field"><col class="annotation">
QUOTE;
      }
      foreach ($fields as $field) {
        if ($field instanceof FormField) {
          $html .= $field->HTMLTableRow($this->usedivs);
        } else {
          $html .= $field;
        }
      }
      $html .=
<<<QUOTE

    </{$element}>
QUOTE;
    }
    return $html;
  }

  // Returns true if any of the fields of the form (or section) have
  // been filled in.
  //
  // @param $section:string Restrict to the specified section, otherwise
  // all sections
  function hasValue($section=null) {
    $value = false;
    $fields = $section ? $this->sections[$section] : $this->fields;
    foreach ($fields as $field) {
      if ($field instanceof FormField) {
        $value |= $field->hasvalue();
      }
    }
    return $value;
  }

  // Returns a string representing the SQL version of the form
  //
  // @param $section:string Restrict to the specified section, otherwise
  // all sections
  function SQLForm($section=null, $fields=null) {
    $sql = "";
    if (! $fields) {
      $fields = $section ? $this->sections[$section] : $this->fields;
    }
    foreach ($fields as $field) {
      if ($field instanceof FormField) {
        $form = $field->SQLForm();
        if ($form !== null) {
          $sql .= ($sql ? ", " : "") . $form;
        }
      }
    }
    return $sql;
  }
  
  // Returns a string to create the SQL table that will hold the form
  //
  // @param $section:string Restrict to the specified section, otherwise
  // all sections
  function SQLTable($section=null, $fields=null) {
    $sql = "";
    if (! $fields) {
      $fields = $section ? $this->sections[$section] : $this->fields;
    }
    foreach ($fields as $field) {
      if ($field instanceof FormField) {
        $sql .= ($sql ? ",\n" : "") . $field->SQLTableColumn();
      }
    }
    return <<<QUOTE
      CREATE TABLE `{$this->name}` (
        {$sql}
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
QUOTE;
  }
  

  // Returns a string representing the SQL values of the form
  //
  // @param $section:string Restrict to the specified section, otherwise
  // all sections
  function SQLValues($section=null, $fields=null) {
    $sql = "";
    if (! $fields) {
      $fields = $section ? $this->sections[$section] : $this->fields;
    }
    foreach ($fields as $field) {
      if ($field instanceof FormField) {
        $value = $field->SQLValue();
        if ($value !== null) {
          if ($sql != "") { $sql .= ", "; }
          $sql .= $value;
        }
      }
    }
    return $sql;
  }

  // Returns a string representing the SQL fields of the form
  // (normalized to return values in a SELECT statement the way we expect --
  // in particular, enums are converted to ints)
  //
  // @param $section:string Restrict to the specified section, otherwise
  // all sections
  function SQLFields($section=null, $fields=null) {
    $sql = "";
    if (! $fields) {
      $fields = $section ? $this->sections[$section] : $this->fields;
    }
    foreach ($fields as $field) {
      if ($field instanceof FormField) {
        $sql .= ($sql ? ", " : "") . $field->SQLField();
      }
    }
    return $sql;
  }

  // Returns a string representing the text version of the form
  //
  // @param $brief:boolean if true, use the field name rather than
  // description as the label
  // @param $section:string Restrict to the specified section, otherwise
  // all sections
  function TextForm($brief=false, $section=null) {
    $text = "";
    $fields = $section ? $this->sections[$section] : $this->fields;
    foreach ($fields as $field) {
      if ($field instanceof FormField &&
          ((! $brief) || $field->hasvalue())) {
        // Neil wants double-spacing between fields
        if ($text != "") { $text .= "\n\n"; }
        $text .= $field->TextForm($brief);
      }
    }
    return $text;
  }

  // Parses the form values from an array
  // Returns true if the values validate
  // @param source:Array (optional) array of values, indexed by field
  // name, to parse the value from.  Default is $_POST
  // @param validate:Boolean (optional) whether or not to validate
  // the values.  Default is true
  function parseValues($source=NULL, $validate=true) {
    global $debugging;
    if ($source == NULL) { $source = $this->method == "post" ? $_POST : $_GET; }
    if ($debugging > 2) {
      echo "<pre>\$source: ";
      print_r($source);
      echo "</pre>";
    }
    $ok = true;
    $errors = array();
    // See "PHP sucks" above
    $fields = array_filter($this->fields, 'is_field');
    // Here, PHP double sucks.  Why would this function not just return
    // the f-ing sorted array?!?!?
    usort($fields, 'order');
    $current = $fields[0]->priority;
    foreach ($fields as $field) {
      // Stop if there are errors and you hit a new priority level
      if ((! $ok) && ($field->priority != $current)) { break; }
      $current = $field->priority;
      // This allows you to have a dynamic form -- we won't check
      // fields that didn't get posted.
      if ($field->isPresent($source)) {
        if ($field->parseValue($source)) {
          // All good
        } else {
          // Bzzt!
          $ok = false;
          array_push($errors, $field->errorMessage());
        }
      }
    }
    if ($validate) {
      $this->errorMessages = $errors;
    }
    $this->complete = $ok;
    return $ok;
  }

  function addErrorMessage($message) {
        array_push($this->errorMessages, $message);
  }
}


///
// Multi-section form.
//
// Deprecated:  All forms now support multiple sections
//
class MultisectionForm extends Form {
  function MultisectionForm($name, $action="", $method="post", $usedivs=false) {
    parent::Form($name, $action, $method, $usedivs);
  }
}


///
// Database-backed form.
//
// Can auto-create fields based on database columns.
class DatabaseForm extends Form {
  var $database;
  var $table;
  // If defined, makes the form editable
  var $idname;
  var $editable = false;
  var $recordID = NULL;
  // If defined will be updated with created and modified timestamps
  var $createdname;
  var $modifiedname;
  
  function DatabaseForm($database, $table, $options=null) {
    // default options
    $defaultoptions = array('name' => null, 'action' => "", 'method' => "post", 'usedivs' => false, 'idname' => NULL, 'createdname' => NULL, 'modifiedname' => NULL);
    $options = $options ? array_merge($defaultoptions, $options) : $defaultoptions; 
    parent::Form($options['name'], $options['action'], $options['method'], $options['usedivs']);
    $this->database = $database;
    $this->table = $table;
    $this->idname = $options['idname'];
    $this->createdname = $options['createdname'];
    $this->modifiedname = $options['modifiedname'];
    $this->editable = $this->idname != NULL;
    $this->columns = columns_of_table($this->database, $this->table);
    $lookups = lookups_from_table_enums($this->database, $this->table);
    // Invert the maps to match form choices
    $enums = array();
    foreach ($lookups as $field => $lookup) {
      $choices = array();
      foreach($lookup as $description => $index) {
        // NULL is 'no choice'
        if ($index !== NULL) {
          $choices[$index] = $description;
        }
      }
      $enums[$field] = $choices;
    }
    $this->enums = $enums;
  }
  
  // Handles all parsing, etc.
  function initialize() {
    global $debugging;
    
    // Will initialize the session, retrieve the process token
    parent::initialize();  
    
    if ($debugging > 2) {
      echo "<pre>self: ";
      print_r($_SERVER['PHP_SELF']);
      echo "\n\$_GET: ";
      print_r($_GET);
      echo "\$_POST: ";
      print_r($_POST);
      echo "\$_FILES: ";
      print_r($_FILES);
      echo "</pre>";
    }

    /**
     * If this is an editable form, look for a passed in id
     * either in a get var (say from a menu of forms to edit)
     * or in a post var (because we came back to edit after submitting)
     *
     * Get trumps post, so you can switch records with a query arg
     */
    $idname = $this->idname;
    $editable = $this->editable;
    $this->recordID = NULL;
    if ($editable) {
      // Editable form
      if (isset($_GET[$idname])) {
        $this->recordID = clean($_GET[$idname]);
      } else if (isset($_POST[$idname])) {
        $this->recordID = clean($_POST[$idname]);
      }
    }
  }

  // Processes the buttons
  // If non-editable form, returns false on validation errors, returns record ID on successful insert
  // For editable form, automatically refreshes the form on insert or update
  // Pass in optional onWhatever to specify a target page on success other than the default
  function handleButtons($options=null) {
    $defaultoptions = array('onSubmit' =>  NULL, 'onCancel' => NULL, 'onDelete' => NULL, 'onUpdate' => NULL);
    $options = $options ? array_merge($defaultoptions, $options) : $defaultoptions;
    $idname = $this->idname;
    $editable = $this->editable;
    ///
    // Handle validation, insertion into database, and acknowledgement
    //
    if ($this->isValidPost()) {
      $submit = array_key_exists('submitButton', $_POST) && $_POST['submitButton'] == 'Submit Form';
      $delete = $editable && array_key_exists('deleteButton', $_POST) && $_POST['deleteButton'] == 'Delete Entry';
      $update = $editable && array_key_exists('updateButton', $_POST) && $_POST['updateButton'] == 'Update Entry';
      // Only validate if the user pushed a button, not for refresh
      $valid = $this->parseValues(null, $submit || $delete || $update);
      // If you came here from a cancel button don't need to validate, but do need to parse to clean up
      if ($editable && array_key_exists('cancelButton', $_POST) && $_POST['cancelButton'] == 'Revert Entry') {
        $this->finalize();
        $id = $this->recordID;
        if ($options['onCancel']) {
          header($options['onCancel']);
        } else {
          // Now fetch the record you just inserted, so you can edit it
          header("Location: {$this->action}?{$idname}=" . urlencode($id));
        }
        exit;
      }
      if ($valid) {
        // We got a valid form!
        if ($submit) {
          // Create a new entry
          $id = $this->SQLInsert();
          if ($id != NULL) {
            if ($options['onSubmit']) {
              header($options['onSubmit']);
              exit;
            } else if ($editable) {
              // Now fetch the record you just inserted, so you can edit it
              header("Location: {$this->action}?{$idname}=" . urlencode($id));
              exit;
            }
          }
          return $id;
        } else if ($delete) {
          // Delete the entry
          $deleted = $this->SQLDelete(); 
          if ($deleted != NULL) {
            if ($options['onDelete']) {
              header($options['onDelete']);
              exit;
            }
            // Go back to an empty form
            $parts = explode('?', $this->action, 1);
            $url = $parts[0]; //  http_build_url($this->action, "", HTTP_URL_STRIP_QUERY | HTTP_URL_STRIP_FRAGMENT);
            header("Location: {$url}");
            exit;
          }        
        } else if ($update) {
          // Update the entry
          $id = $this->SQLUpdate();
          if ($id != NULL) {
            if ($options['onUpdate']) {
              header($options['onUpdate']);
              exit;
            }
            // Now fetch the record you just updated, so you can edit it
            header("Location: {$this->action}?{$idname}=" . urlencode($id));
            exit;
          }        
        }
      }
      // Otherwise, we fall through and re-display the form, with any
      // errors highlighted
    } else if ($editable && isset($_GET[$idname]) && $this->recordID == $_GET[$idname]) {
      // If you came here from a GET, load the form from the database
      $this->SQLLoad($this->recordID);
    }
    return false;
  }
  
  // Special behavior for database-backed forms
  function HTMLForm($process=null, $buttons=null) {
    if ($this->editable && $this->recordID) {
      // If we have an id, we want update/revert/delete
      $buttons = <<<QUOTE
      <input type="submit" name="updateButton" value="Update Entry">&nbsp;&nbsp;
      <input type="submit" name="cancelButton" value="Revert Entry">&nbsp;&nbsp;
      <input class="submit" onclick="return confirm('Are you sure you want to delete this entry?')"  type="submit"  name="deleteButton" value="Delete Entry">
      <input type="hidden" id="{$this->idname}" name="{$this->idname}" value="{$this->recordID}">
QUOTE;
    }
    return parent::HTMLForm($process, $buttons);
  }

  // Make the query, report any errors
  function SQLExecuteQuery($sql, $database) {
    global $debugging;
    if ($debugging > 2) {
      echo "<p style='font-size: smaller'>";
      echo "[Query: <span style='font-style: italic'>" . $sql . "</span>]";
      echo "</p>";
    }
    if (! $result = mysql_query($sql, $database)) {
      if ($debugging) {
        echo "<p style='font-size: smaller'>";
        echo "[Query: <span style='font-style: italic'>" . $sql . "</span>]";
        echo "<br>";
        echo "[Error: <span style='font-style: italic'>" . mysql_error() . "</span>]";
        echo "</p>";
      }
    }
    return $result;
  }
    
  // Insert the values into the table
  function SQLInsert($additional = "", $options=NULL) {
    // default options
    $defaultoptions = array('section' => null, 'fields' => null, 'table' => $this->table, 'database' => $this->database);
    $options = $options ? array_merge($defaultoptions, $options) : $defaultoptions;
    $database = $options['database'];
    $table = $options['table'];
    $sql = "INSERT INTO " . $table . " SET " . $this->SQLForm($options['section'], $options['fields']);
    // Update created and modified
    if ($this->createdname) {
      $additional .= ($additional ? ", " : "") . "`{$this->createdname}` = NOW()";
    }
    if ($this->modifiedname) {
      $additional .= ($additional ? ", " : "") . "`{$this->modifiedname}` = NOW()";
    }
    if ($additional) {
      $sql .= ", ";
      $sql .= $additional;
    }
    $success = $this->SQLExecuteQuery($sql, $database) ? mysql_insert_id($database) : NULL;
    if ($success) {
      $this->finalize();
    }
    return $success;
  }

  // Update an entry in the table
  function SQLUpdate($additional = "", $options=NULL) {
    // default options
    $defaultoptions = array('section' => null, 'fields' => null, 'table' => $this->table, 'database' => $this->database, 'idname' => $this->idname);
    $options = $options ? array_merge($defaultoptions, $options) : $defaultoptions;
    $database = $options['database'];
    $table = $options['table'];
    $idname = $options['idname'];
    $id = $this->recordID ? $this->recordID : $this->fieldValue($idname);
    $sql = "UPDATE " . $table . " SET " . $this->SQLForm($options['section'], $options['fields']);
    // Only modified will be updated here
    if ($this->modifiedname) {
      $additional .= ($additional ? ", " : "") . "`{$this->modifiedname}` = NOW()";
    }
    if ($additional) {
      $sql .= ", ";
      $sql .= $additional;
    }
    $sql .= " WHERE {$idname} = " . PHPtoSQL($id);
    $success = $this->SQLExecuteQuery($sql, $database) ? $id : NULL;
    if ($success) {
      $this->finalize();
    }
    return $success;
  }

  // Delete an entry from the table
  function SQLDelete($options=NULL) {
    // default options
    $defaultoptions = array('table' => $this->table, 'database' => $this->database, 'idname' => $this->idname);
    $options = $options ? array_merge($defaultoptions, $options) : $defaultoptions;
    $database = $options['database'];
    $table = $options['table'];
    $idname = $options['idname'];
    $id = $this->recordID ? $this->recordID : $this->fieldValue($idname);
    $sql = "DELETE FROM " . $table . " WHERE {$idname} = " . PHPtoSQL($id);
    $success =  $this->SQLExecuteQuery($sql, $database) ? $id : NULL;
    if ($success) {
      $this->finalize();
    }
    return $success;
  }
  
  // Fetch values from the table
  function SQLSelect($id, $additional = null, $options=NULL) {
    // default options
    $defaultoptions = array('section' => null, 'fields' => null, 'table' => $this->table, 'database' => $this->database, 'idname' => $this->idname);
    $options = $options ? array_merge($defaultoptions, $options) : $defaultoptions;
    $database = $options['database'];
    $table = $options['table'];
    $idname = $options['idname'];
    $sql = "SELECT " . $this->SQLFields($options['section'], $options['fields']) . " FROM " . $table . (($id != null) ? (" WHERE {$idname} = " . PHPtoSQL($id)) : '');
    if ($additional) {
      $sql .= ", ";
      $sql .= $additional;
    }
    return $this->SQLExecuteQuery($sql, $database);
  }
  
  // Load the form from the table
  // Just a wrapper for SQLSelect without additions
  function SQLLoad($id, $options=NULL) {
    $result = $this->SQLSelect($id, null, $options);
    $source = mysql_fetch_array($result);
    return $source ? $this->parseValues($source) : NULL;
  }
}  

///
// Basic Form Field object
//
// You make one of these for each field in your form and add it to the
// form.
//
class FormField {
  // The form this belongs to
  var $form;
  // The name of the field
  var $name;
  // An English description of the field
  var $description;
  // The type of the <input> element
  var $type;
  // A title for the <input>
  var $title;
  // A placeholder for the <input>
  var $placeholder;
  // An annotation that will appear to the right of the form element
  var $annotation;
  // Non-editable field
  var $readonly = false;
  // Autosubmit field
  var $autosubmit = false;
  // Is the field required?
  var $required;
  // The value of the field (parsed from $_POST)
  var $value;
  // The default value (for resetting);
  var $default;
  // The maximum length of the value
  var $maxlength;
  // We could have a flag that remembers when the value is invalid
  // so we could outline the HTML in red or something
  var $valid;
  // For repeating fields, what instance is this?
  var $instance;
  // Unique id
  var $id;
  // Input name
  var $input;
  // Parse priority
  var $priority = 0;
  // For debugging
  var $options;

  // Create a form field.  Arguments are:
  // @param name:String The name of the field
  // @param description:String The English description of the field
  // @param optional:Boolean (optional) True if the field is not
  // required
  // @param options:Array (optional) Additional options
  function FormField ($name, $description, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'type' => 'text'
      ,'title' => NULL
      ,'placeholder' => NULL
      ,'instance' => NULL
      ,'priority' => 0
      ,'readonly' => false
      ,'autosubmit' => false
      ,'maxlength' => NULL
      ,'default' => NULL
    );
    $this->options = $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;

    $this->name = $name;
    $this->description = ($description !== NULL) ? $description : ucwords(str_replace("_", " ", $name));
    $this->type = $options['type'];
    $this->required = (! $optional);
    $this->annotation = $options['annotation'];
    $this->title = $options['title'];
    $this->placeholder = $options['placeholder'];
    $this->readonly = $options['readonly'];
    $this->autosubmit = $options['autosubmit'];
    $this->maxlength = $options['maxlength'];
    $this->default =$options['default'];
    $this->valid = true;
    $this->priority = $options['priority'];
    $this->setInstance($options['instance']);
  }
  
  function setInstance($instance=NULL) {
    $this->instance = $instance;
    $instance = is_null($this->instance) ? '' : $this->instance;
    $this->id = "{$this->name}{$instance}";
    $multiple = is_null($this->instance) ? '' : $this->instance; // Was '[]', but POST does not preserve order?
    $this->input = "{$this->name}{$multiple}";
  }
  
  // Make field properties match the DB
  function setForm($form) {
    $this->form = $form;
    if ($form->columns && array_key_exists($this->id, $form->columns)) {
      $descriptor = $form->columns[$this->id];
      // Non-nullable fields are required
      $this->required = ($descriptor->Null != 'YES');
      // Pick up any default
      if ($descriptor->Default) { $this->default = $descriptor->Default; }
      // Pick up test lengths
      if ($this->maxlength == NULL) {
        if (preg_match("/VARCHAR\\((\\d*)\\)/", $descriptor->Type, $regs)) {
          $this->maxlength = 0 + $regs[1];
        }
      }
    }
  }

  function setAnnotation($annotation) {
    $this->annotation = $annotation;
  }

  // Tests if a value is valid for this field
  function isvalid($value) {
    return (! $this->required) || (isset($value) && ($value !== ''));
  }

  function hasvalue() {
    return $this->valid && isset($this->value);
  }

  // Effectively the presentation, where the value is the representation
  function choice() {
    return $this->value;
  }

  // Creates an error message telling how to correct your errors.
  function errorMessage() {
    if ($this->required) {
      return $this->description . " is a required field. Please enter it below.";
    } else {
      return $this->description . ': "' . $this->HTMLValue() . '" is not a valid entry.';
    }
  }

  // Creates a canonical version of the value for this field
  function canonical($value) {
    $value = trim($value);
    return $value;
  }

  function setValue($v) {
    // If the value is valid, store it
    if ($this->isvalid($v)) {
      // Allow erasing of non-required values
      if ((! $this->required) && empty($v)) {
        $this->value = NULL;
      } else {
        $this->value = $this->canonical($v);
      }
      $this->valid = true;
    }
    // Otherwise, store the bogus value for error reporting
    // but mark it as not valid.
    else {
      $this->value = $v;
      $this->valid = false;
    }
  }

  // Set a default (initial) value (if no value is set)
  function initialValue($v) {
    if (! isset($this->value)) {
      $this->setValue($v);
    }
  }

  // This allows you to have a dynamic form -- we won't check
  // fields that didn't get posted.
  function isPresent($source) {
    return (array_key_exists($this->input, $source));
  }  

  // Gets a value for this field from the posted form data.  Verifies
  // that it is valid, and stores the canonical value.  Returns a
  // boolean indicating whether or not a valid value was given.
  // @param source:Array array of values, indexed by field
  // name, to parse the value from
  function parseValue($source) {
    $v = $source[$this->input];
    // If the posted value is valid, store it
    $this->setValue($v);
    return $this->valid;
  }

  function head() {
  }
  
  function initialize() {
  }
  
  function finalize() {
  }

  // Returns the value in a format that is safe to insert into HTML.
  // Can return an invalid value (for error reporting).  If you must
  // have a valid value, test $this->valid first.
  function HTMLValue() {
    return htmlspecialchars($this->choice(), ENT_QUOTES);
  }

  // Returns the value in a format that is safe to insert into SQL.
  // If the field does not have a valid value, returns DEFAULT.
  function SQLValue() {
    if ($this->hasvalue()) {
      // NOT choice -- SQL Stores representation
      $val = $this->value;
      return PHPtoSQL($val);
    } else {
      return "DEFAULT";
    }
  }

  // Returns the value in plain text format.  Invalid or empty fields
  // are return as the empty string.  Quotes in the string are escaped.
  function TextValue() {
    if ($this->hasvalue()) {
      return $this->choice();
    } else {
      return "";
    }
  }

  function setReadonly($on) {
    $this->readonly = $on;
  }

  function setAutosubmit($on) {
    $this->autosubmit = $on;
  }

  function additionalInputAttributes () {
    global $html5;
    $attrs = "";
    if ($this->title) {
      $attrs .= " title='{$this->title}'";
    }
    if ($this->placeholder) {
      $attrs .= " placeholder='{$this->placeholder}'";
    }
    if ($this->maxlength) {
      $attrs .=  " maxlength='{$this->maxlength}'";
    }
    if ($this->readonly) {
      $attrs .= " disabled='disabled'";
    }
    if ($this->autosubmit) {
      $attrs .= " onchange='document.forms[0].submit()'";
    }
    return $attrs;
  }

  // Create the HTML form element for inputting this field
  function HTMLFormElement() {
    global $html5;
    // If there is a value, display that, otherwise display the default
    // Higlight incorrect values, lowlight defaults
    $class= "";
    $onfocus = "";
    $additional = $this->additionalInputAttributes();
    if (isset($this->value)) {
      $val = $this->HTMLValue();
      if (! $this->valid) {
        $class = ' class="invalid"';
        $onfocus =
<<<QUOTE
          onfocus="this.className = '';"
QUOTE;
      }
    } else if (isset($this->default)) {
      $val = $this->default;
      $val = htmlspecialchars($val, ENT_QUOTES);
      $class = ' class="hint"';
      $onfocus =
<<<QUOTE
        onfocus="this.className = ''; this.value = '';"
QUOTE;
    } else {
      $val = '';
    }
    return
<<<QUOTE

          <input{$class}{$onfocus} name="{$this->input}" id="{$this->id}" type="{$this->type}"{$additional} value="{$val}">
QUOTE;
  }

  function HTMLTableColumn() {
    $element = $this->HTMLFormElement();
    if ($this->readonly) {
      // We still need to submit the value
      $element .=
<<<QUOTE

      <input type="hidden" name="{$this->input}" id="{$this->id}" value="{$this->value}">
QUOTE;
    }
    return $element;
  }
  
  // Creates an HTML table row containing an input element
  // for entering this field in a form.
  function HTMLTableRow($usedivs=false) {
    $req = $this->required ? "<span class='required'>*</span>" : "";
    $rowclass = $this->name;
    if ($this->required) {
      $rowclass .= ' requiredfield';
    }
    $tr = $usedivs ? "div" : "tr";
    $td = $usedivs ? "div" : "td";
    $form =
<<<QUOTE

      <{$tr} class="{$rowclass}">
        <{$td} class="label">
QUOTE;
    if (isset($this->description)) {
      $form .=
<<<QUOTE
          <label for="{$this->id}">{$this->description}</label>{$req}
QUOTE;
    }
    $form .=
<<<QUOTE
        </{$td}>
        <{$td} class="field">
QUOTE;
    $form .= $this->HTMLTableColumn();
    $form .=
<<<QUOTE

        </{$td}>
QUOTE;
    if (isset($this->annotation)) {
      $form .=
<<<QUOTE

        <{$td} class="annotation">{$this->annotation}</{$td}>
QUOTE;
    } else {
      $form .=
<<<QUOTE

        <{$td} class="annotation"></{$td}>
QUOTE;
    }
    $form .=
<<<QUOTE

      </{$tr}>
QUOTE;
    return $form;
  }

  // Creates an SQL assignment expression for entering this field
  // into a database.
  function SQLForm() {
    return "`{$this->id}` = " . $this->SQLValue();
  }
  
  function __toString() {
    return get_class($this) . ": (" . var_export($this->options, true) . ") " . $this->SQLForm();
  }

  // SQL column specification
  function SQLTableColumn() {
    $name = "`{$this->id}`";
    $type = $this->SQLType();
    $nullable = "NOT NULL";
    $default = "";
    if ($this->required) {
    } else if ($this->default != null) {
      $default = "DEFAULT " . PHPtoSQL($this->default);
    } else {
      $nullable = "NULL";
      $default = "DEFAULT NULL";
    }
    return "{$name} {$type} {$nullable} {$default}";
  }

  // Create an SQL expression that will fetch the field's canonical value
  function SQLField() {
    return "`{$this->id}`";
  }

  function SQLType() {
    return "VARCHAR(" . ($this->maxlength ? $this->maxlength : 63) . ")";
  }
  
  // Heuristicates colon after label in TextForm
  function addLabelColon($label) {
    // Add a trailing colon if no punctuation already
    if (preg_match("/.*[\w\d)\]]$/", $label)) { $label .= ':'; }
    return $label;
  }

  // Creates a text description of this field, say, for an email
  function TextForm($brief=false) {
    $instance = is_null($this->instance) ? '' : " {$this->instance}";

    $label = $brief ? "{$this->id}" : "{$this->description}{$instance}";
    $label = $this->addLabelColon($label);
    return $label . " " . $this->TextValue();
  }
}

///
// A FormField that is an email address
//
class EmailFormField extends FormField {

  function EmailFormField ($name, $description, $optional=false, $options=NULL) {
    global $html5;
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'type' => $html5 ? "email" : "text"
      ,'title' => 'email address'
      ,'placeholder' => 'you@isp.com'
      // http://stackoverflow.com/questions/386294/what-is-the-maximum-length-of-a-valid-email-address
      ,'maxlength' => 254
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::FormField($name, $description, $optional, $options);
  }

  function isvalid($value) {
    if ((! $this->required) && empty($value)) {
      return true;
    } else {
      return parent::isvalid($value) && is_email_valid($value);
    }
  }

  function additionalInputAttributes () {
    global $html5;
    $attrs = parent::additionalInputAttributes();
    if ($html5) {
      $attrs .= " autocorrect='off' autocapitalize='off'";
    }
    return $attrs;
  }
}


///
// A FormField that is a number in a certain range
//
class SimpleNumberFormField extends FormField {
  var $min;
  var $max;
  var $step;

  // Create a NumberFormField
  //
  // @param name:String The name of the field
  // @param description:String The English description of the field
  // @min:Number (optional) The minimum value, default null means no
  // minimum
  // @max:Number (optional) The maximum value, default null means no
  // maximum
  // @step:Number (optional) How much to increment/decrement by, default
  // null means no step
  // @param optional:Boolean (optional) True if the field is not
  // required
  // @param annotation:String (optional) Additional description that
  // will appear to the right of the form
  function SimpleNumberFormField ($name, $description, $optional=false, $options=NULL) {
    global $mobile;
    $title = "number";
    if (isset($options['min']) && isset($options['max'])) {
      $title .= " between {$options['min']} and {$options['max']}";
    }
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'type' => ($mobile ? "number" : "text")
      ,'title' => $title
      ,'min' => NULL
      ,'step' => NULL
      ,'max' => NULL
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;

    parent::FormField($name, $description, $optional, $options);
    $this->min = $options['min'];
    $this->max = $options['max'];
    $this->step = $options['step'];
  }

  function isvalid ($value) {
    if ((! $this->required) && empty($value)) {
      return true;
    } else {
      return parent::isvalid($value) &&
        is_numeric($value) &&
        ($this->min ? ($value >= $this->min) : true) &&
        ($this->max ? ($value <= $this->max) : true);
    }
  }

  function errorMessage () {
    if (empty($this->value) && $this->required) {
      return $this->description . " is a required field. Please enter it below.";
    }
    return $this->description . ': "' . $this->HTMLValue() . '" is not a valid '
      . $this->title
      . '. Please enter a valid ' . $this->title
      . ($this->placeholder ? (' (' . $this->placeholder . ')') : '')    . '.';
  }

  function additionalInputAttributes () {
    $attrs = parent::additionalInputAttributes();
    if ($this->min != null) {
      $attrs .= " min='{$this->min}'";
    }
    if ($this->max != null) {
      $attrs .= " max='{$this->max}'";
    }
    if ($this->step != null) {
      $attrs .= " step='{$this->step}'";
    }
    return $attrs;
  }
  
  function SQLType() {
    $max = 0;
    if ($this->max != null) {
      $max = $this->max;
    }
    if (($this->min != null) && ($this->min < 0) && ((- $this->min) > $max)) {
      $max = (- $this->min);
    }
    // Simplistic -- improve if you really need to be a storage miser
    $type = "INT";
    if ($max && ($max < 128)) {
      $type = "TINYINT";
    } else if ($max > 2147483647) {
      $type = "DECIMAL";
    }
    $digits = $max ? ceil(log10($max)) : null;
    return $type . ($digits ? ("(" . $digits . ")") : "");
  } 
}

///
// Back-compatibility
class NumberFormField extends SimpleNumberFormField {
  function NumberFormField ($name, $description, $min=null, $max=null, $step=null, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      // We'll let you enter anything, but heuristicate it to a 2-letter code
      ,'max' => $max
      ,'step' => $step
      ,'min' => $min
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleNumberFormField($name, $description, $optional, $options);
  }
}


///
// Abstract FormField that matches a pattern
//
abstract class PatternFormField extends FormField {
  // The pattern that you have to match 
  var $pattern;

  function PatternFormField ($name, $description, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'pattern' => NULL
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::FormField($name, $description, $optional, $options);
    $this->pattern = $options['pattern'];
  }

  function isvalid ($value) {
    if ((! $this->required) && empty($value)) {
      return true;
    } else {
      return parent::isvalid($value) &&
        preg_match($this->pattern, $value);
    }
  }

  function errorMessage () {
    if (empty($this->value) && $this->required) {
      return $this->description . " is a required field. Please enter it below.";
    }
    return $this->description . ': "' . $this->HTMLValue() . '" is not a valid '
      . $this->title
      . '. Please enter a valid ' . $this->title
      . ($this->placeholder ? (' (' . $this->placeholder . ')') : '')    . '.';
  }

  function additionalInputAttributes () {
    global $html5;
    // The input pattern does not include the slashes
    $p = substr($this->pattern, 1, -1);
    $attrs = parent::additionalInputAttributes();
    if ($html5) {
      $attrs .= " autocorrect='off' autocapitalize='off'";
    }
// This works poorly in Safari 5 (won't submit, but no feedback
//    $attrs .= " pattern='{$p}'";
    return $attrs;
  }
}


///
// A FormField that is a 2-letter State abbreviation
//
class StateFormField extends PatternFormField {

  function StateFormField ($name, $description, $optional=false, $options) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'pattern' => "/^([A-Za-z]{2,2})$/"
      ,'maxlength' => 2
      ,'title' => "state designation"
      ,'placeholder' => "ST"
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::PatternFormField($name, $description, $optional, $options);
  }

  function canonical ($value) {
    $value = parent::canonical($value);
    $matches = array();
    if (preg_match($this->pattern, $value, $matches)) {
      return strtoupper($matches[1]);
    }
    return NULL;
  }
}


///
// A FormField that is a 2-letter Country abbreviation
class CountryFormField extends StateFormField {
  function CountryFormField ($name, $description, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      // We'll let you enter anything, but heuristicate it to a 2-letter code
      ,'maxlength' => NULL
      ,'title' => "country designation"
      ,'placeholder' => "CC"
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::StateFormField($name, $description, $optional, $options);
  }

  // For this particular field, we heuristicate before we validate
  function isvalid ($value) {
    if ((! $this->required) && empty($value)) {
      return true;
    } else {
      return parent::isvalid($this->heuristicate($value));
    }
  }
  
  function canonical($value) {
    return parent::canonical($this->heuristicate($value));
  }
  
  function heuristicate($value) {
    if (empty($value)) { return $value; }
    global $ISO_3166_1_countries;
    include "ISO-3166-1.php";

    $upper = strtoupper($value);
    // May already be correct
    if (array_key_exists($upper, $ISO_3166_1_countries)) {
        return $upper;
    }
    // Quick fixes
    switch ($upper) {
      case 'USA':
        return 'US';
    }
    // The hard way
    $matches = array();
    foreach ($ISO_3166_1_countries as $code => $name) {
      if (stristr($name, $value)) { $matches[$name] = $code; }
    }
    if (count($matches) == 1) {
      return array_shift($matches);
    } else if (count($matches) > 1) {
      foreach($matches as $name => $code) {
        if (strcasecmp($name, $value) == 0) { return $code; }
      }
    }
    return $upper;
  }
}

///
// A FormField that is a ZIP code
//
class ZIPFormField extends PatternFormField {

  function ZIPFormField ($name, $description, $optional=false, $options=NULL) {
    global $mobile;
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      // [2012-12-12 ptw] Mobile Webkit inserts commas if you use 'number'
      ,'type' => $mobile ? "tel" : "text"
      ,'maxlength' => 10
      ,'pattern' => "/^([0-9]{5,5})-?([0-9]{4,4})?$/"
      ,'title' => "ZIP code"
      ,'placeholder' => "01234-5678"
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::PatternFormField($name, $description, $optional, $options);
  }

  function canonical ($value) {
    $value = parent::canonical($value);
    $matches = array();
    if (preg_match($this->pattern, $value, $matches)) {
      return $matches[1];
    }
    return NULL;
  }

  // ZIP-codes want to be a string, not a number
  function SQLValue() {
    if ($this->hasvalue()) {
      return "'" . addslashes($this->value) . "'";
    } else {
      return "DEFAULT";
    }
  }
  
  // ZIP-codes want to be a string, not a number
//   function SQLField() {
//     $field = parent::SQLField();
//     return <<<QUOTE
// CONCAT("'", {$field}, "'") AS {$field}
// QUOTE;
//   }
}

///
// A FormField that is a Postal Code
//
class PostalCodeFormField extends FormField {

  function PostalCodeFormField ($name, $description, $optional=false, $options=NULL) {
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      // [2012-12-12 ptw] Mobile Webkit inserts commas if you use 'number'
      ,'type' => "text"
      ,'title' => "Postal code"
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    // Can't really do any validation on postal codes, they are too random!
    // We can't know if the postal code is all numeric or not
    // Hence simple text field, rather than pattern
    parent::FormField($name, $description, $optional, $options);
  }
}

///
// A FormField that is a phone number
//
class PhoneFormField extends PatternFormField {

  function PhoneFormField ($name, $description, $optional=false, $options=NULL) {
    global $html5;
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      // [2012-12-12 ptw] Mobile Webkit inserts commas if you use 'number'
      ,'type' => $html5 ? "tel" : "text"
      ,'maxlength' => 16
      ,'pattern' => "/^\(?([0-9]{3,3})\)?[-. ]?([0-9]{3,3})[-. ]?([0-9]{4,4})$/"
      ,'title' => "phone number"
      ,'placeholder' => "555-555-1234"
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::PatternFormField($name, $description, $optional, $options);
  }

  function isvalid ($value) {
    if ((! $this->required) && empty($value)) {
      return true;
    } else {
      return parent::isvalid($value);
    }
  }

  function canonical ($value) {
    $value = parent::canonical($value);
    $matches = array();
    if (preg_match($this->pattern, $value, $matches)) {
      return $matches[1] . "-" . $matches[2] . "-" . $matches[3];
    }
    return NULL;
  }
}

///
// A FormField that is a date
//
// Allows a 2-digit year if non-ISO mode, defaults to the current century
//
// Heuristicates input/ouput format, stores in ISO format
//
class DateFormField extends PatternFormField {
  var $ISO;
  var $ISOPattern = "/^([0-9]{4,4})-([0-1]?[0-9])-([0-3]?[0-9])$/";
  var $LocalPattern = "/^([01]?[0-9])[-\/ ]([0-3]?[0-9])[-\/ ]((?:[0-9]{2,2})?[0-9]{2,2})$/";
  var $year;
  var $month;
  var $day;

  function DateFormField ($name, $description, $optional=false, $options=NULL) {
    global $mobile;
    $this->ISO = $mobile;
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'type' => $this->ISO ? "date" : "text"
      ,'maxlength' => 16
      // We want ISO format always, heuristicate Local if necessary
      ,'pattern' => $this->ISOPattern
      ,'title' => "date"
      ,'placeholder' => $this->ISO ? date("Y-m-d") : date("m/d/y")
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::PatternFormField($name, $description, $optional, $options);
  }

  function isvalid ($value) {
    if ((! $this->required) && empty($value)) {
      return true;
    } else {
      return preg_match($this->ISOPattern, $value) || preg_match($this->LocalPattern, $value);
    }
  }

  function ISOValue () {
    return ("" . $this->year . "-" . number_pad($this->month, 2) . "-" . number_pad($this->day, 2));
  }
  
  function choice () {
    if ((! empty($this->value)) && $this->isvalid($this->value)) {
      if ($this->ISO) {
        return $this->ISOValue();
      } else {
        return ("" . $this->month . "/" . $this->day . "/" . $this->year);
      }
    } else {
      return $this->value;
    }
  }
  
  function canonical ($value) {
    $value = parent::canonical($value);
    if (preg_match($this->ISOPattern, $value, $matches)) {
      $this->year = 1 * $matches[1];
      $this->month = 1 * $matches[2];
      $this->day = 1 * $matches[3];
    } else if (preg_match($this->LocalPattern, $value, $matches)) {
      $this->year = 1 * $matches[3];
      $this->month = 1 * $matches[1];
      $this->day = 1 * $matches[2];
      if ($this->year < 100) {
        $date = getdate();
        $century = floor(($date['year']) / 100);
        $this->year = $century * 100 + $this->year;
      }
    } else {
      // Because we are called by isvalid
      return $value;
    }
    // Not choice because we don't know if it is valid yet.
    return $this->ISOValue();
  }
  
  function SQLType() {
    return "DATE";
  }  
}

///
// A DateField that is a birth date
//
// Requires a full 4-digit year
//
class BirthdateFormField extends DateFormField {

  function BirthdateFormField ($name, $description, $optional=false, $options=NULL) {
    global $mobile;
    $this->ISO = $mobile;
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'title' => "birth date"
      // 4-digit year for local pattern
      ,'placeholder' => $this->ISO ? date("Y-m-d") : date("m/d/Y")
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::DateFormField($name, $description, $optional, $options);
    // Override the default
    // Require 4-digit year
    $this->LocalPattern = "/^([01]?[0-9])[-\/ ]([0-3]?[0-9])[-\/ ]((?:[0-9]{2,2})?[0-9]{4,4})$/";
  }
}

///
// A FormField that is a Time of Day
//
// Allows a free-form format, either 24-hour or 12-hour + am/pm
//
class DaytimeFormField extends PatternFormField {
  var $ISO;
  // We allow seconds, for compatibility with SQL, but we ignore them for now
  var $ISOPattern = "/^([0-2][0-9])\:([0-6][0-9])\:?((?:[0-6][0-9])?)$/";
  var $LocalPattern = "/^([0-2]?[0-9])\:?((?:[0-6][0-9])?)\s*([apAP]?)[mM]?$/";
  var $hour;
  var $minute;
  
  function DaytimeFormField ($name, $description, $optional=false, $options=NULL) {
    global $mobile;
    $this->ISO = $mobile;
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'type' => $this->ISO ? "time" : "text"
      ,'maxlength' => 8
      ,'pattern' => $this->ISO ? $this->ISOPattern : $this->LocalPattern
      ,'title' => "time"
      ,'placeholder' => $this->ISO ? date("H:i") : date("g:i a")
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::PatternFormField($name, $description, $optional, $options);
  }

  function isvalid ($value) {
    if ((! $this->required) && empty($value)) {
      return true;
    } else {
      // We want ISO format always, heuristicate Local if necessary
      return preg_match($this->ISOPattern, $value) || preg_match($this->LocalPattern, $value);
    }
  }
  
  function ISOValue() {
    return ("" . number_pad($this->hour, 2) . ":" . number_pad($this->minute, 2));
  }

  function choice () {
    if ($this->valid) {
      if ($this->ISO) {
        return $this->ISOValue();
      } else {
        $h = $this->hour;
        // Not sure when ?: introduced to php
        return "" . (($h % 12) ? ($h % 12) : 12) . ":" . number_pad($this->minute, 2) . ($h < 12 ? " am" : " pm");
      }
    } else {
      return $this->value;
    }
  }

  function canonical ($value) {
    $value = parent::canonical($value);
    if (preg_match($this->ISOPattern, $value, $matches)) {
      $this->hour = 1 * $matches[1];
      $this->minute = 1 * $matches[2];
    } else if (preg_match($this->LocalPattern, $value, $matches)) {
      $this->hour = 1 * $matches[1];
      // Not sure when ?: introduced to php
      $this->minute = 1 * ($matches[2] ? $matches[2] : 0);
      $meridian = $matches[3];
      if ($meridian) {
        $this->hour = $this->hour % 12;
        if (strtolower($meridian) == 'p') {
          $this->hour = $this->hour + 12;
        }
      }
    } else {
      // Because we are called by isvalid
      return $value;
    }

    return $this->ISOValue();
  }
  
  function SQLType() {
    return "DATETIME";
  }
}



///
// A FormField that is a textarea
//
class TextAreaFormField extends FormField {

  function TextAreaFormField($name, $description, $optional=false, $options=NULL) {
    parent::FormField($name, $description, $optional, $options);
  }

  // Create the HTML form element for inputting this field
  function HTMLFormElement() {
    // Higlight incorrect values
    $class = $this->valid ? "" : ' class="invalid"';
    // Only insert the current value if it is valid.
    $val = ($this->hasvalue()) ? $this->HTMLValue() :  htmlspecialchars($this->default, ENT_QUOTES);
    return
<<<QUOTE

      <textarea{$class} name="{$this->input}" id="{$this->id}">{$val}</textarea>
QUOTE;
  }

  // Neil wants this value on a separate line
  function TextForm($brief=false) {
    $instance = is_null($this->instance) ? '' : " {$this->instance}";

    $text = $brief ? "{$this->id}" : "{$this->description}{$instance}";
    $text = $this->addLabelColon($text);
    if ($this->hasvalue()) {
      $text .= "\n\t" . $this->TextValue();
    }
    return $text;
  }
  
  function SQLType() {
    $max = $this->maxlength ? $this->maxlength : 255;
    if ($max < pow(2,8)) {
      return "VARCHAR(" . $max . ")";
    } else {
      return "TEXT";
    }
  }
}


///
// A ChoiceItem allows you to specify the items of a ChoiceFormField as
// a class so you can store additional data with each item and specify
// how the item is expressed in HTML and SQL.
class ChoiceItem {
  var $name;
  var $description;

  // @param $name:String The name of the item (stored in database)
  // @param $description:String The description of the item (displayed
  // on the form).  Optional, defaults to name.
  function ChoiceItem($name, $description=null) {
    $this->name = $name;
    $this->description = ($description ? $description : $name);
  }

  function HTMLValue() {
    return htmlspecialchars($this->name, ENT_QUOTES);
  }

  function TextValue() {
    return $this->description;
  }

  function description() {
    return htmlspecialchars($this->description, ENT_QUOTES);
  }
}

///
// Converts an array of `key => value` to an array of ChoiceItems
// with `key` as the database value and `value` as the description
// that will appear in the menu.
// Note that the key is replicated so that you can recover it directly // from the choiceItem
function arrayToChoiceItems ($choices) {
  $items = array();
  foreach ($choices as $name => $description) {
    $items[$name] = new ChoiceItem($name, $description);
  }
  return $items;
}


///
// Converts an array of values to an array of ChoiceItems with each
// `value` as the database value and ALSO as the description that will
// appear in the menu.  This is for the simple case where you want to
// store the text description in the database
function arrayToSimpleItems ($choices) {
  $items = array();
  foreach ($choices as $name => $description) {
    $items[$description] = $description;
  }
  return $items;
}

///
// A FormField that has a limited set of choices
//
class SimpleChoiceFormField extends FormField {
  // Array of possible choices
  var $choices;
  var $invalidChoice;

  function SimpleChoiceFormField($name, $description, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'choices' => NULL
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::FormField($name, $description, $optional, $options);
    $this->choices = $options['choices'];
    $this->invalidChoice = MD5('not_bloody_likely');
  }
  
  function setForm($form) {
    parent::setForm($form);
    if ($this->choices == null) {
      $this->choices = $this->form->choicesForField($this->name);
    } else if ($form instanceof DatabaseForm) {
      // If the database defines an enum, we need to use it for our choice array
      $choices = $this->form->choicesForField($this->name);
      if ($choices) {
        $this->choices = $choices;
      }
    }
  }

  function isvalid($key) {
    if (! $this->required) {
      return true;
    } else if (! (is_int($key) || is_string($key))) {
      return false;
    } else {
      // Can't check isset, because we want to allow null as a possible
      // value
      return array_key_exists($key, $this->choices);
    }
  }

  function canonical($key) {
    if ((! $this->required) &&
        (($key == $this->invalidChoice) || empty($key))) {
      return NULL;
    }
    // The canonical value needs to be `===` to the array key
    // since that is how we determine selected
    return array_search($this->choices[$key], $this->choices);
  }

  // We have to be a little more particular here
  function hasvalue() {
    return $this->valid
      && (is_int($this->value) || is_string($this->value));
  }

  function choice() {
    $choice = $this->choices[$this->value];
    if ($choice instanceof ChoiceItem) {
      return $choice->name;
    } else {
      return $choice;
    }
  }

  function HTMLValue() {
    if ($this->hasvalue()) {
      $choice = $this->choices[$this->value];
      if ($choice instanceof ChoiceItem) {
        return $choice->HTMLValue();
      } else {
        return htmlspecialchars($choice, ENT_QUOTES);
      }
    } else {
      return "";
    }
  }

  function SQLField() {
    // Coerce enums to the type we expect
    $keys = is_array($this->choices) ? array_keys($this->choices) : NULL;
    $field = parent::SQLField();
    if ($keys && is_numeric($keys[0])) {
      return "{$field}+0 AS {$field}";
    } else {
      return $field;
    }
  }

  // See FormField::SQLValue -- we store the value, not the presentation

  function TextValue() {
    if ($this->hasvalue()) {
      $choice = $this->choices[$this->value];
      if ($choice instanceof ChoiceItem) {
        return $choice->TextValue();
      } else {
        return $choice;
      }
    } else {
      return "";
    }
  }

  function SQLType() {
    $choices = "";
    foreach($this->choices as $key => $choice) {
      $choices .= ($choices ? ", " : "") . PHPtoSQL($choice);
    }
    return "ENUM({$choices})";
  }  
}

///
// Back-compatibility
//
class ChoiceFormField extends SimpleChoiceFormField {
  function ChoiceFormField($name, $description, $choices=NULL, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'choices' => $choices
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleChoiceFormField($name, $description, $optional, $options);
  }
}

///
// A FormField that will be represented as a radio button
//
// @param choices:array An array of the possible choices
class SimpleRadioFormField extends ChoiceFormField {

  function SimpleRadioFormField($name, $description, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'type' => 'radio'
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleChoiceFormField($name, $description, $optional, $options);
  }

  function HTMLFormElement() {
    global $debugging;
    $additional = $this->additionalInputAttributes();
    // Higlight incorrect values
    $class = $this->valid ? "" : ' class="invalid"';
    $element = "";
    // Debugging
    if ($debugging > 2) {
      $element .= "<div>valid: " . ($this->valid ? "true" : "false") . "</div>";
      $element .= "<div>choices keys: " . implode(",", array_keys($this->choices)) . "</div>";
      $element .= "<div>choices values: " . implode(",", $this->choices) . "</div>";
      $element .= "<div>value: {$this->value}</div>";
    }
    $element .=
<<<QUOTE

      <fieldset{$class} id="{$this->id}">
QUOTE;
    $hasselection = false;
    foreach ($this->choices as $key => $value) {
      $selected = ($this->value === $key) ? " checked" : "";
      if ($selected) { $hasselection = true; }
      if ($value instanceof ChoiceItem) {
        $desc = $value->description();
      } else {
        $desc = htmlspecialchars($value, ENT_QUOTES);
      }
      $element .=
<<<QUOTE

        <label for="{$this->id}">
          <input name="{$this->input}" type="{$this->type}" class="{$this->type}" value="{$key}"{$selected}{$additional}>
          <span>{$desc}</span>
        </label>
QUOTE;
    }
    // Ensure this field will be posted
    // For a radio button, only one thing can be selected, so we omit this if there
    // is a selection
    if (! $hasselection) {
      $element .=
<<<QUOTE

        <input style="display: none" type="radio" name="{$this->input}" value="$this->invalidChoice" checked>
QUOTE;
    }
    $element .=
<<<QUOTE

      </fieldset>
QUOTE;

    return $element;
  }
}

///
// Back-compatibility
//
class RadioFormField extends SimpleRadioFormField {
  function RadioFormField($name, $description, $choices=NULL, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'choices' => $choices
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleRadioFormField($name, $description, $optional, $options);
  }
}

///
// A FormField that has a limited set of choices, but allows more than
// one choice.
//
class SimpleMultipleChoiceFormField extends SimpleChoiceFormField {

  function SimpleMultipleChoiceFormField($name, $description, $optional=false, $options=NULL) {
    parent::SimpleChoiceFormField($name, $description, $optional, $options);
  }
  
  function unpack($value) {
    $keyarray = array();
    foreach ($this->choices as $key => $val) {
      if (((1 << $key) & $value) != 0) {
        $keyarray[] = $key;
      }
    }
    return $keyarray;
  }

  function isvalid($keyarray) {
    // Optional implies you don't need to have _any_ choices
    $valid = (! $this->required);
    if (is_numeric($keyarray)) {
      $keyarray = $this->unpack($keyarray);
    }
    if (is_array($keyarray)) {
      foreach ($keyarray as $key) {
        // Can't check isset, because we want to allow NULL as a possible
        // value
        if ($key == $this->invalidChoice) {
          // Ignore -- this is just to ensure a value is submitted/parsed
        } else if (array_key_exists($key, $this->choices)) {
          // We have at least one choice
          $valid = true;
        } else {
          return false;
        }
      }
    }
    return $valid;
  }

  function canonical($keyarray) {
    // The canonical value array needs to have elements `===` to the
    // choices keys since that is how we determine selected
    if (is_numeric($keyarray)) {
      $keyarray = $this->unpack($keyarray);
    }
    if (is_array($keyarray)) {
      return array_intersect(array_keys($this->choices), $keyarray);
    }
    return NULL;
  }

  // We have to be a little more particular here
  function hasvalue() {
    return $this->valid && is_array($this->value);
  }

  function choice() {
    return array_intersect_key($this->choices, array_flip($this->value));
  }

  function HTMLValue() {
    if ($this->hasvalue()) {
      $html = array();
      foreach ($this->choice() as $choice) {
        if ($choice instanceof ChoiceItem) {
          $html[] = $choice->HTMLValue();
        } else {
          $html[] = htmlspecialchars($choice, ENT_QUOTES);
        }
      }
      return join(",", $html);
    } else {
      return "";
    }
  }

  function SQLValue() {
    if ($this->hasvalue()) {
      $strings = array();
      $number = 0;
      $isnumeric = true;
      // NOT choice -- SQL Stores representation
      foreach ($this->value as $val) {
        $val = PHPtoSQL($val);
        $strings[] = $val;
        if (is_numeric($val)) {
          $number |= 1 << $val;
        } else {
          $isnumeric = false;
        }
      }
      if ($isnumeric) {
        return $number;
      } else {
        return "'" . join(",", $strings) . "'";
      }
    } else {
      return "DEFAULT";
    }
  }

  function TextValue() {
    if ($this->hasvalue()) {
      $text = array();
      foreach ($this->choice() as $choice) {
        if ($choice instanceof ChoiceItem) {
          $text[] = $choice->TextValue();
        } else {
          $text[] = $choice;
        }
      }
      // Neil wants these separated by newlines in text
      return "\t" . join("\n\t", $text);
    } else {
      return "";
    }
  }

  // Neil wants this value on a separate line
  function TextForm($brief=false) {
    $instance = is_null($this->instance) ? '' : " {$this->instance}";

    $text = $brief ? "{$this->id}" : "{$this->description}{$instance}";
    $text = $this->addLabelColon($text);
    if ($this->hasvalue()) {
      $text .= "\n" . $this->TextValue();
    }
    return $text;
  }

  function SQLType() {
    $choices = "";
    foreach($this->choices as $key => $choice) {
      $choices .= ($choices ? ", " : "") . PHPtoSQL($choice);
    }
    return "SET({$choices})";
  }  
}

///
// Back-compatibility
//
class MultipleChoiceFormField extends SimpleMultipleChoiceFormField {
  function MultipleChoiceFormField($name, $description, $choices=NULL, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'choices' => $choices
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleMultipleChoiceFormField($name, $description, $optional, $options);
  }
}

///
// A FormField that will be represented as a checkbox
//
// @param choices:array An array of the possible choices
class SimpleCheckboxFormField extends SimpleMultipleChoiceFormField {

  function CheckboxFormField($name, $description, $optional=false, $options=NULL) {
    parent::SimpleMultipleChoiceFormField($name, $description, $optional, $options);
  }

  function HTMLFormElement() {
    global $debugging;
    // Higlight incorrect values
    $class = $this->valid ? "" : ' class="invalid"';
    $element = "";
    // Debugging
    if ($debugging > 2) {
      $element .= "<div>valid: " . ($this->valid ? "true" : "false") . "</div>";
      $element .= "<div>choices keys: " . implode(",", array_keys($this->choices)) . "</div>";
      $element .= "<div>choices values: " . implode(",", $this->choices) . "</div>";
      $element .= "<div>value array keys:" . ($this->value ? implode(",", array_keys($this->value)) : '') . "</div>";
      $element .= "<div>value array values:" . ($this->value ? implode(",", $this->value) : '') . "</div>";
    }
    $element .=
<<<QUOTE

      <fieldset{$class} id="{$this->id}">
QUOTE;
    foreach ($this->choices as $key => $value) {
      $selected = ($this->value && in_array($key, $this->value)) ? " checked" : "";
      if ($value instanceof ChoiceItem) {
        $desc = $value->description();
      } else {
        $desc = htmlspecialchars($value, ENT_QUOTES);
      }
      $element .=
<<<QUOTE

        <label for="{$this->id}">
          <input name="{$this->input}[]" type="checkbox" class="checkbox" value="{$key}"{$selected}>
          <span>{$desc}</span>
        </label>
QUOTE;
    }
    // Ensure this field will be posted at least once (so it gets parsed)
    // For a checkbox multiple selections are allowed, so we can unilaterally 
    // include this -- the invalidChoice is ignored by isvalid/canonical
    {
      $element .=
<<<QUOTE

        <input style="display: none" type="checkbox" name="{$this->input}[]" value="$this->invalidChoice" checked>
QUOTE;
    }
    $element .=
<<<QUOTE

      </fieldset>
QUOTE;

    return $element;
  }
}

///
// A FormField that will be represented as a checkbox, which is either
// checked or not
//
//
// Deprecated:  CheckboxFormField with only one choice is equivalent
//
class SingleCheckboxFormField extends CheckboxFormField {

  function SingleCheckboxFormField($name, $description, $choices=null, $optional=false, $annotation="", $instance=NULL) {
    parent::CheckboxFormField($name, $description, $choices, $optional, $annotation, $instance);
  }
}

///
// A FormField that will be represented as a checkbox, which _must be
// checked
//
// Deprecated:  CheckboxFormField with only one choice and $optional=false is equivalent
//
class RequiredCheckboxFormField extends CheckboxFormField {

  function RequiredCheckboxFormField($name, $description, $choices=null, $annotation="", $instance=NULL) {
    parent::CheckboxFormField($name, $description, $choices, false, $annotation, $instance);
  }
}

///
// Back-compatibility
//
class CheckboxFormField extends SimpleCheckboxFormField {
  function CheckboxFormField($name, $description, $choices=NULL, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'choices' => $choices
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleCheckboxFormField($name, $description, $optional, $options);
  }
}

///
// A FormField that will be represented as a pull-down menu
//
// @param choices:array An array of the possible choices
class SimpleMenuFormField extends SimpleChoiceFormField {

  function MenuFormField($name, $description, $optional=false, $options=NULL) {
    parent::SimpleChoiceFormField($name, $description, $optional, $options);
  }

  // Writes out a <select> tag with a fake first option that
  // describes the choice you have to make
  function HTMLFormElement() {
    global $debugging;
    $additional = $this->additionalInputAttributes();
    // Higlight incorrect values
    $class = $this->valid ? "" : ' class="invalid"';
    $element = '';
    // Debugging
    if ($debugging > 2) {
      $element .= "<div>valid: " . ($this->valid ? "true" : "false") . "</div>";
      $element .= "<div>choices keys: " . implode(",", array_keys($this->choices)) . "</div>";
      $element .= "<div>choices values: " . implode(",", $this->choices) . "</div>";
      $element .= "<div>value: {$this->value}</div>";
    }
    $element .=
<<<QUOTE

      <select{$class} name="{$this->input}" id="{$this->id}"{$additional}>
        <option value="{$this->invalidChoice}">Select {$this->description}</option>
QUOTE;
    foreach ($this->choices as $key => $value) {
      $selected = ($this->value === $key) ? " selected" : "";
      if ($value instanceof ChoiceItem) {
        $desc = $value->description();
      } else {
        $desc = htmlspecialchars($value, ENT_QUOTES);
      }
      $element .=
<<<QUOTE

        <option value="{$key}"{$selected}>{$desc}</option>
QUOTE;
    }
    $element .=
<<<QUOTE

      </select>
QUOTE;
    return $element;
  }
}

///
// Back-compatibility
//
class MenuFormField extends SimpleMenuFormField {
  function MenuFormField($name, $description, $choices=NULL, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'choices' => $choices
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleMenuFormField($name, $description, $optional, $options);
  }
}

///
// A TimeFormField lets you pick a time in a range at particular
// intervals.
class SimpleTimeFormField extends SimpleMenuFormField {

  function TimeFormField($name, $description, $optional=false, $options=NULL) {
    global $html5;
    $choices = array();
    $start = $options['start'];
    $end = $options['end'];
    $interval = $options['interval'];
    for ($i = $start; $i <= $end; $i += $interval) {
      $hour = floor($i);
      $minute = floor(($i - $hour) * 60);
      $m = ($hour < 12 ? "am" : "pm");
      if ($hour > 12) { $hour -= 12; }
      $choices[] = $hour . ":" . ($minute < 10 ? "0" : "") . $minute . $m;
    }
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'type' => $html5 ? "time" : "text"
      ,'choices' => $choices
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleMenuFormField($name, $description, $optional, $options);
  }

  // Output 24-hour SQL time
  function SQLValue() {
    if ($this->hasvalue()) {
      return "'" . normalize_time($this->choice()) . "'";
    } else {
      return "DEFAULT";
    }
  }
  function SQLType() {
    return "TIME";
  }
}


///
// Back-compatibility
//
class TimeFormField extends SimpleTimeFormField {
  function TimeFormField($name, $description,$start, $end, $interval, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'start' => $start
      ,'end' => $end
      ,'interval' => $interval
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleTimeFormField($name, $description, $optional, $options);
  }
}


///
// A MenuItem allows you to specify the items of a menu as a class
// so you can store additional data with each item and specify how
// the item is expressed in HTML and SQL.
class MenuItem {
  var $name;

  function MenuItem($name) {
    $this->name = $name;
  }

  function HTMLValue() {
    return htmlspecialchars($this->name, ENT_QUOTES);
  }

  function TextValue() {
    return $this->name;
  }
}

///
// Custom menu selector for picking a MenuItem
//
// Non MenuItem entries may be included in the choices to 'document'
// your menu inline.
//
class SimpleMenuItemFormField extends SimpleMenuFormField {

  function SimpleMenuItemFormField($name, $description, $optional=false, $options=NULL) {
    parent::SimpleMenuFormField($name, $description, $optional, $options);
  }

  // Override to make sure they don't choose a separator
  function isvalid($value) {
    return parent::isvalid($value) &&
      $this->choices[$value] instanceof MenuItem;
  }

  // Override to interpret separators and MenuItems
  function HTMLFormElement() {
    // Higlight incorrect values
    $class = $this->valid ? "" : ' class="invalid"';
    $element =
<<<QUOTE

      <select{$class} name="{$this->input}" id="{$this->id}">
        <option>Select {$this->description}</option>
QUOTE;
    foreach ($this->choices as $key => $value) {
      if ($value instanceof MenuItem) {
        $selected = ($this->value === $key) ? " selected" : "";
        $desc = $value->HTMLValue();
        $element .=
<<<QUOTE

        <option value="{$key}"{$selected}>&nbsp;&nbsp;{$desc}</option>
QUOTE;
      } else {
        $desc = htmlspecialchars($value, ENT_QUOTES);
        $element .=
<<<QUOTE

        <option>{$desc}</option>
QUOTE;
      }
    }
    $element .=
<<<QUOTE

      </select>
QUOTE;
    return $element;
  }

  function HTMLValue() {
    if ($this->hasvalue()) {
      return $this->choice()->HTMLValue();
    } else {
      return "";
    }
  }

  // See FormField::SQLValue -- we store the value, not the presentation

  function TextValue() {
    if ($this->hasvalue()) {
      return $this->choice()->TextValue();
    } else {
      return "";
    }
  }
}

///
// Back-compatibility
//
class MenuItemFormField extends SimpleMenuItemFormField {
  function MenuItemFormField($name, $description, $choices=NULL, $optional=false, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'choices' => $choices
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::SimpleMenuItemFormField($name, $description, $optional, $options);
  }
}

?>
