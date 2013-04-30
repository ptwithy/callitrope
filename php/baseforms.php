<?php

/**
 * Copyright (c) 2012, callitrope
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

// See if our browser has HTML5 features like `placeholder`
// Webkit = Safari, Chrome; Presto = Opera
$html5 = preg_match("/webkit|presto/i", $_SERVER['HTTP_USER_AGENT']);
// Some stuff only looks good on mobile
$mobile = preg_match("/ipad|iphone|android/i",  $_SERVER['HTTP_USER_AGENT']);

// Have I told you how much PHP sucks?  Apparently you cannot
// define anonymous inner functions?  WTF?
function is_field ($f) { return $f instanceof FormField; };
function order ($a, $b) { return $a->priority - $b->priority; };

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

  // This holds all the fields in the form.  Each time you create
  // a field, it will be added to this array.
  var $fields;

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
    $this->fields = array();
    $this->sections = array();
    $this->startSection($name);
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
      $this->fields[$formField->name] = $formField;
    } else {
      $this->fields[] = $formField;
    }
  }

  function field($fieldName) {
    return $this->fields[$fieldName];
  }

  function fieldHasValue($fieldName) {
    $field = $this->fields[$fieldName];
    return $field && $field->hasvalue();
  }

  function fieldValue($fieldName) {
    $field = $this->fields[$fieldName];
    if ($field && $field->hasvalue()) {
      return $field->choice();
    }
  }

  function fieldHTMLValue($fieldName) {
    $field = $this->fields[$fieldName];
    if ($field && $field->hasvalue()) {
      return $field->HTMLValue();
    }
  }

  function fieldSQLValue($fieldName) {
    $field = $this->fields[$fieldName];
    if ($field) {
      // [2012-08-12 ptw] we know SQLValue returns the right thing if there
      // is no value
      return $field->SQLValue();
    }
  }

  function fieldTextValue($fieldName) {
    $field = $this->fields[$fieldName];
    if ($field && $field->hasvalue()) {
      return $field->TextValue();
    }
  }

  // Returns a string representing the HTML version of the form
  function HTMLForm($process=1, $buttons=null) {
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

  <form class="{$this->name}" name="{$this->name}" method="{$this->method}" action="{$this->action}">
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
  function SQLForm($section=null) {
    $sql = "";
    $fields = $section ? $this->sections[$section] : $this->fields;
    foreach ($fields as $field) {
      if ($field instanceof FormField) {
        $form = $field->SQLForm();
        if ($form !== null) {
          if ($sql != "") { $sql .= ", "; }
          $sql .= $form;
        }
      }
    }
    return $sql;
  }

  // Returns a string representing the SQL values of the form
  //
  // @param $section:string Restrict to the specified section, otherwise
  // all sections
  function SQLValues($section=null) {
    $sql = "";
    $fields = $section ? $this->sections[$section] : $this->fields;
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
    if ($source == NULL) { $source = $_POST; }
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
      if ($field->parseValue($source)) {
        // All good
      } else {
        // Bzzt!
        $ok = false;
        array_push($errors, $field->errorMessage());
      }
    }
    if ($validate) {
      $this->errorMessages = $errors;
    }
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
  function MultisectionForm($name, $action="", $method="post") {
    parent::Form($name, $action, $method);
  }
}


///
// Basic Form Field object
//
// You make one of these for each field in your form and add it to the
// form.
//
class FormField {
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

  // Create a form field.  Arguments are:
  // @param name:String The name of the field
  // @param description:String The English description of the field
  // @param optional:Boolean (optional) True if the field is not
  // required
  // @param annotation:String (optional) Additional description that
  // will appear to the right of the form
  // @param instance:Number (optional) An index number for multiple
  // occurences of the same field
  // @param priority:Number (optional) A priority for checking validity
  // higher values are checked first
  function FormField ($name, $description, $optional=false, $annotation="", $instance=NULL, $priority=0) {
    $this->name = $name;
    $this->description = $description;
    $this->type = "text";
    $this->required = (! $optional);
    $this->annotation = $annotation;
    $this->readonly = false;
    $this->valid = true;
    $this->instance = $instance;
    $instance = is_null($this->instance) ? '' : $this->instance;
    $this->id = "{$name}{$instance}";
    $multiple = is_null($this->instance) ? '' : '[]';
    $this->input = "{$name}{$multiple}";
    $this->priority = $priority;
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
    // Allow erasing of non-required values
    if ((! $this->required) && empty($value)) {
      unset($value);
    }
    return $value;
  }

  function setValue($v) {
    // If the value is valid, store it
    if ($this->isvalid($v)) {
      $this->value = $this->canonical($v);
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


  // Gets a value for this field from the posted form data.  Verifies
  // that it is valid, and stores the canonical value.  Returns a
  // boolean indicating whether or not a valid value was given.
  // @param source:Array array of values, indexed by field
  // name, to parse the value from
  function parseValue($source=NULL) {
    if ($source == NULL) { $source = $_POST; }
    // This allows you to have a dynamic form -- we won't check
    // fields that didn't get posted.
    if (! array_key_exists($this->name, $source)) {
      return true;
    }
    $v = $source[$this->name];
    if (! is_null($this->instance)) {
      $v = $v[$this->instance];
    }
    // If the posted value is valid, store it
    $this->setValue($v);
    return $this->valid;
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

  // Creates an HTML table row containing an input element
  // for entering this field in a form.
  function HTMLTableRow($usedivs=false) {
    $req = $this->required ? "<span class='required'>*</span>" : "";
    $instance = is_null($this->instance) ? '' : $this->instance;
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
    $form .= $this->HTMLFormElement();
    if ($this->readonly) {
      // We still need to submit the value
      $form .=
<<<QUOTE

      <input type="hidden" name="{$this->input}" id="{$this->id}" value="{$this->value}">
QUOTE;
    }
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

  function EmailFormField ($name, $description, $optional=false, $annotation="", $instance=NULL) {
    global $html5;
    parent::FormField($name, $description, $optional, $annotation, $instance);
    $this->type = $html5 ? "email" : "text";
  }

  function isvalid($value) {
    if ((! $this->required) && empty($value)) {
      return true;
    } else {
      return parent::isvalid($value) && is_email_valid($value);
    }
  }

  function errorMessage() {
    return $this->description . ': "' . $this->HTMLValue() . '" is not a valid email address. Please enter a valid email address.';
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
class NumberFormField extends FormField {
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
  function NumberFormField ($name, $description, $min=null, $max=null, $step=null, $optional=false, $annotation="", $instance=NULL) {
    global $mobile;
    parent::FormField($name, $description, $optional, $annotation, $instance);
    $this->type = $mobile ? "number" : "text";
    $this->min = $min;
    $this->max = $max;
    $this->step = $step;
    $this->title = "number";
    if ($this->min && $this->max) {
      $this->title .= " between {$this->min} and {$this->max}";
    }
  }

  function isvalid ($value) {
    if (($value == $this->default) ||
        ((! $this->required) && empty($value))) {
      return (! $this->required);
    } else {
      return parent::isvalid($value) &&
        is_numeric($value) &&
        ($this->min ? ($value >= $this->min) : true) &&
        ($this->max ? ($value <= $this->max) : true);
    }
  }

  function errorMessage () {
    return $this->description . ': "' . $this->HTMLValue() . '" is not a valid '
      . $this->title
      . '. Please enter a valid ' . $this->title
      . ($this->placeholder ? (' (' . $this->placeholder . ')') : '')    . '.';
  }

  function additionalInputAttributes () {
    $attrs = parent::additionalInputAttributes();
    if ($this->min) {
      $attrs .= " min='{$this->min}'";
    }
    if ($this->max) {
      $attrs .= " max='{$this->max}'";
    }
    if ($this->step) {
      $attrs .= " step='{$this->step}'";
    }
    return $attrs;
  }
}


///
// Abstract FormField that matches a pattern
//
abstract class PatternFormField extends FormField {
  // The pattern that you have to match 
  var $pattern;

  function PatternFormField ($name, $description, $optional=false, $annotation="", $instance=NULL) {
    parent::FormField($name, $description, $optional, $annotation, $instance);
  }

  function isvalid ($value) {
    if (($value == $this->default) ||
        ((! $this->required) && empty($value))) {
      return (! $this->required);
    } else {
      return parent::isvalid($value) &&
        preg_match($this->pattern,   $value);
    }
  }

  function errorMessage () {
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

  function StateFormField ($name, $description, $optional=false, $annotation="", $instance=NULL) {
    parent::PatternFormField($name, $description, $optional, $annotation, $instance);
    // Override the default
    $this->maxlength = 2;
    $this->pattern = "/^([A-Z]{2,2})$/";
    $this->title = "state designation";
    $this->placeholder = 'ST';
  }

  function canonical ($value) {
    $value = parent::canonical($value);
    $matches = array();
    preg_match($this->pattern, $value, $matches);
    return strtoupper($matches[1]);
  }
}

///
// A FormField that is a ZIP code
//
class ZIPFormField extends PatternFormField {

  function ZIPFormField ($name, $description, $optional=false, $annotation="", $instance=NULL) {
    global $mobile;
    parent::PatternFormField($name, $description, $optional, $annotation, $instance);
    // Override the default
    $this->maxlength = 10;
    $this->pattern = "/^([0-9]{5,5})-?([0-9]{4,4})?$/";
    // [2012-12-12 ptw] Mobile Webkit inserts commas if you use 'number'
    $this->type = $mobile ? "tel" : "text";
    $this->title = "ZIP code";
    $this->placeholder = '01234-5678';
  }

  function canonical ($value) {
    $value = parent::canonical($value);
    $matches = array();
    preg_match($this->pattern, $value, $matches);
    return $matches[1];
  }

  // ZIP-codes want to be a string, not a number
  function SQLValue() {
    if ($this->hasvalue()) {
      return "'" . addslashes($this->value) . "'";
    } else {
      return "DEFAULT";
    }
  }
}

///
// A FormField that is a phone number
//
class PhoneFormField extends PatternFormField {

  function PhoneFormField ($name, $description, $optional=false, $annotation="", $instance=NULL) {
    global $html5;
    parent::PatternFormField($name, $description, $optional, $annotation, $instance);
    // Override the default
    $this->type = $html5 ? "tel" : "text";
    $this->maxlength = 16;
    $this->pattern = "/^\(?([0-9]{3,3})\)?[-. ]?([0-9]{3,3})[-. ]?([0-9]{4,4})$/";
    $this->title = "phone number";
    $this->placeholder = "555-555-1234";
  }

  function isvalid ($value) {
    if (($value == $this->default) ||
        ((! $this->required) && empty($value))) {
      return (! $this->required);
    } else {
      return parent::isvalid($value);
    }
  }

  function canonical ($value) {
    if ((! $this->required) &&
        (($value == $this->default) || empty($value))) {
      unset($value);
      return $value;
    }
    $value = parent::canonical($value);
    $matches = array();
    preg_match($this->pattern, $value, $matches);
    return $matches[1] . "-" . $matches[2] . "-" . $matches[3];
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

  function DateFormField ($name, $description, $optional=false, $annotation="", $instance=NULL) {
    global $mobile;
    $this->ISO = $mobile;
    parent::PatternFormField($name, $description, $optional, $annotation, $instance);
    // Override the default
    $this->type = $this->ISO ? "date" : "text";
    $this->maxlength = 16;
    // We want ISO format always, heuristicate Local if necessary
    $this->pattern = $this->ISOPattern;
    $this->title = "date";
    $this->placeholder = $this->ISO ? date("Y-m-d") : date("m/d/y");
  }

  function isvalid ($value) {
    if (($value == $this->default) ||
        ((! $this->required) && empty($value))) {
      return (! $this->required);
    } else {
      return preg_match($this->ISOPattern, $value) || preg_match($this->LocalPattern, $value);
    }
  }

  function ISOValue () {
    return ("" . $this->year . "-" . number_pad($this->month, 2) . "-" . number_pad($this->day, 2));
  }
  
  function choice () {
    if ($this->valid) {
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
    if ((! $this->required) &&
        (($value == $this->default) || empty($value))) {
      unset($value);
      return $value;
    }
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
}

///
// A DateField that is a birth date
//
// Requires a full 4-digit year
//
class BirthdateFormField extends DateFormField {

  function BirthdateFormField ($name, $description, $optional=false, $annotation="", $instance=NULL) {
    parent::DateFormField($name, $description, $optional, $annotation, $instance);
    // Override the default
    $this->title = "birth date";
    // Require 4-digit year
    $this->LocalPattern = "/^([01]?[0-9])[-\/ ]([0-3]?[0-9])[-\/ ]((?:[0-9]{2,2})?[0-9]{4,4})$/";
    $this->placeholder = $this->ISO ? date("Y-m-d") : date("m/d/Y");
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
  
  function DaytimeFormField ($name, $description, $optional=false, $annotation="", $instance=NULL) {
    global $mobile;
    $this->ISO = $mobile;
    parent::PatternFormField($name, $description, $optional, $annotation, $instance);
    // Override the default
    $this->type = $this->ISO ? "time" : "text";
    $this->maxlength = 8;
    $this->pattern = $this->ISO ? $this->ISOPattern : $this->LocalPattern;
    $this->title = "time";
    $this->placeholder = $this->ISO ? date("H:i") : date("g:i a");
  }

  function isvalid ($value) {
    if (($value == $this->default) ||
        ((! $this->required) && empty($value))) {
      return (! $this->required);
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
    if ((! $this->required) &&
        (($value == $this->default) || empty($value))) {
      unset($value);
      return $value;
    }
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
}



///
// A FormField that is a textarea
//
class TextAreaFormField extends FormField {

  function TextAreaFormField($name, $description, $optional=false, $annotation="", $instance=NULL) {
    parent::FormField($name, $description, $optional, $annotation, $instance);
  }

  // Create the HTML form element for inputting this field
  function HTMLFormElement() {
    $instance = is_null($this->instance) ? '' : $this->instance;

    // Only insert the current value if it is valid.
    $val = ($this->hasvalue()) ? $this->HTMLValue() :  htmlspecialchars($this->default, ENT_QUOTES);
    return
<<<QUOTE

      <textarea name="{$this->input}" id="{$this->id}">{$val}</textarea>
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

  function SQLValue() {
    return "'" . addslashes($this->name) . "'";
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
class ChoiceFormField extends FormField {
  // Array of possible choices
  var $choices;

  function ChoiceFormField($name, $description, $choices, $optional=false, $annotation="", $instance=NULL) {
    parent::FormField($name, $description, $optional, $annotation, $instance);
    $this->choices = $choices;
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

  function SQLValue() {
    if ($this->hasvalue()) {
      $choice = $this->choices[$this->value];
      if ($choice instanceof ChoiceItem) {
        return "'" . $choice->SQLValue() . "'";
      } else {
        return "'" . addslashes($choice) . "'";
      }
    } else {
      return "DEFAULT";
    }
  }

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
}


///
// A FormField that will be represented as a radio button
//
// @param choices:array An array of the possible choices
class RadioFormField extends ChoiceFormField {
  var $hasselection;

  function RadioFormField($name, $description, $choices, $optional=false, $annotation="", $instance=NULL) {
    parent::ChoiceFormField($name, $description, $choices, $optional, $annotation, $instance);
    $this->hasselection = false;
    $this->type = 'radio';
  }

  function HTMLFormElement() {
    $additional = $this->additionalInputAttributes();
    $element = "";
    $element .=
<<<QUOTE

      <fieldset id="{$this->id}">
QUOTE;
    foreach ($this->choices as $key => $value) {
      $selected = ($this->value === $key) ? " checked" : "";
      if ($selected) { $this->hasselection = true; }
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
    if (! $this->hasselection) {
      $element .=
<<<QUOTE

        <input style="display: none" type="radio" name="{$this->input}" value="not_bloody_likely" checked>
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
class SingleCheckboxFormField extends RadioFormField {

  function SingleCheckboxFormField($name, $description, $choices, $optional=false, $annotation="", $instance=NULL) {
    parent::RadioFormField($name, $description, $choices, $optional, $annotation, $instance);
    $this->hasselection = true;
    $this->type = 'checkbox';
  }
}

///
// A FormField that will be represented as a checkbox, which _must be
// checked
//
class RequiredCheckboxFormField extends SingleCheckboxFormField {

  function RequiredCheckboxFormField($name, $description, $choices, $annotation="", $instance=NULL) {
    parent::SingleCheckboxFormField($name, $description, $choices, false, $annotation, $instance);
  }

  function parseValue($source=NULL) {
    if ($source == NULL) { $source = $_POST; }
    if (! array_key_exists($this->name, $source)) {
      return false;
    }
    return parent::parseValue($source);
  }

  function isvalid($value) {
    return parent::isvalid($value) &&
      (count($this->choices) == 1);
  }
}

///
// A FormField that has a limited set of choices, but allows more than
// one choice.
//
class MultipleChoiceFormField extends ChoiceFormField {

  function MultipleChoiceFormField($name, $description, $choices, $optional=false, $annotation="", $instance=NULL) {
    parent::ChoiceFormField($name, $description, $choices, $optional, $annotation, $instance);
  }

  function isvalid($keyarray) {
    $valid = (! $this->required);
    // Can't check isset, because we want to allow NULL as a possible
    // value
    if (isset($keyarray)) {
      foreach ($keyarray as $key) {
        if (! array_key_exists($key, $this->choices)) {
          return false;
        }
        $valid = true;
      }
    }
    return $valid;
  }

  function canonical($keyarray) {
    // The canonical value array needs to have elements `===` to the
    // choices keys since that is how we determine selected
    if (isset($keyarray)) {
      return array_intersect(array_keys($this->choices), $keyarray);
    }
    return null;
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
      $sql = array();
      foreach ($this->choice() as $choice) {
        if ($choice instanceof ChoiceItem) {
          $sql[] = $choice->SQLValue();
        } else {
          $sql[] = addslashes($choice);
        }
      }
      return "'" . join(",", $sql) . "'";
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
}

///
// A FormField that will be represented as a checkbox
//
// @param choices:array An array of the possible choices
class CheckboxFormField extends MultipleChoiceFormField {

  function CheckboxFormField($name, $description, $choices, $optional=false, $annotation="", $instance=NULL) {
    parent::MultipleChoiceFormField($name, $description, $choices, $optional, $annotation, $instance);
  }

  function HTMLFormElement() {
    $element = "";
    $element .=
<<<QUOTE

      <fieldset id="{$this->id}">
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
    $element .=
<<<QUOTE

      </fieldset>
QUOTE;

    return $element;
  }
}

///
// A FormField that will be represented as a pull-down menu
//
// @param choices:array An array of the possible choices
class MenuFormField extends ChoiceFormField {

  function MenuFormField($name, $description, $choices, $optional=false, $annotation="", $instance=NULL) {
    parent::ChoiceFormField($name, $description, $choices, $optional, $annotation, $instance);
  }

  // Writes out a <select> tag with a fake first option that
  // describes the choice you have to make
  function HTMLFormElement() {
    $additional = $this->additionalInputAttributes();
    $element = '';
    $element .=
<<<QUOTE

      <select name="{$this->input}" id="{$this->id}"{$additional}>
        <option>Select {$this->description}</option>
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
// A TimeFormField lets you pick a time in a range at particular
// intervals.
class TimeFormField extends MenuFormField {

  function TimeFormField($name, $description, $start, $end, $interval, $optional=false, $annotation="", $instance=NULL) {
    global $html5;
    $choices = array();
    for ($i = $start; $i <= $end; $i += $interval) {
      $hour = floor($i);
      $minute = floor(($i - $hour) * 60);
      $m = ($hour < 12 ? "am" : "pm");
      if ($hour > 12) { $hour -= 12; }
      $choices[] = $hour . ":" . ($minute < 10 ? "0" : "") . $minute . $m;
    }
    parent::MenuFormField($name, $description, $choices, $optional, $annotation, $instance);
    $this->type = $html5 ? "time" : "text";
  }

  // Output 24-hour SQL time
  function SQLValue() {
    if ($this->hasvalue()) {
      return "'" . normalize_time($this->choice()) . "'";
    } else {
      return "DEFAULT";
    }
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

  function SQLValue() {
    return "'" . addslashes($this->name) . "'";
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
class MenuItemFormField extends MenuFormField {

  function MenuItemFormField($name, $description, $classes, $optional=false, $annotation="", $instance=NULL) {
    parent::MenuFormField($name, $description, $classes, $optional, $annotation, $instance);
  }

  // Override to make sure they don't choose a separator
  function isvalid($value) {
    return parent::isvalid($value) &&
      $this->choices[$value] instanceof MenuItem;
  }

  // Override to interpret separators and MenuItems
  function HTMLFormElement() {
    $element =
<<<QUOTE

      <select name="{$this->input}" id="{$this->id}">
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

  function SQLValue() {
    if ($this->hasvalue()) {
      return $this->choice()->SQLValue();
    } else {
      return "DEFAULT";
    }
  }

  function TextValue() {
    if ($this->hasvalue()) {
      return $this->choice()->TextValue();
    } else {
      return "";
    }
  }
}

?>
