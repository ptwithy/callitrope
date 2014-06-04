<?php

///
// Special versions of forms for the Kingsbury club
//

include_once "baseforms.php";
include_once "multiformfield.php";


///
// A class for storing class descriptors
//
// @param name: the name of the class
// @param type: the type of the class
// @param contact: (optional) email contact for the class
// @param daytime: (optional, default true) 
//

class ClassDescriptor extends MenuItem {
  var $type;
  var $contact;
  var $daytime;
  
  function ClassDescriptor($name, $type, $contact, $daytime=true) {
    parent::MenuItem($name);
    $this->type = $type;
    $this->contact = $contact;
    $this->daytime = $daytime;
  }
}


///
// The totally custom class/day/time field
//
// It is actually composed of 3 other form fields.  A menu of classes
// taken, a menu of days, and a time field
//
class ClassDayTimeFormField extends FormMultiField {
  var $class;
  var $day;
  var $time;
  
  function ClassDayTimeFormField ($name, $description, $classes, $optional=false) {
    // TODO:  Somehow encode that if you pick a class, you also have to
    // pick a day and time.
    $choices = array();
    foreach($classes as $class) {
      if ($class instanceOf MenuItem) {
        $choices[$class->name] = $class;
      } else {
        $choices[] = $class;
      }
    }
    $this->class = new MenuItemFormField($name . "_class", "Class", $choices, $optional);
    $this->day = new MenuFormField($name . "_day", "Day",
      arrayToSimpleItems(array(
        "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday", "Monday-Thursday",
        "Week 1", "Week 2", "Week 3", "Week 4", "Week 5", "Week 6", "Week 7", "Week 8", "Week 9"
        )), false);
    $this->time = new TimeFormField($name . "_time", "Time", 5, 22, .25, false);

    // Pass these custom fields up
    $fields = array(
      'class' => $this->class,
      'day' => $this->day,
      'time' => $this->time
    );
    parent::FormMultiField($name, $description, $fields, $optional);
  }
  
  // Custom row builder.  Actually outputs two rows, first a label row
  // and then a row with the three sub-FormFields
  function HTMLTableRow() {
    $req = $this->required ? "<span class='required'>*</span>" : "";
    $alert = $this->valid ? "" : "class='invalid'";
    $form =
<<<QUOTE
  <tr class="{$this->name}">
    <td colspan="3" align="left">
      <label {$alert}>{$this->description}{$req}</label>
    </td>
  <tr class="{$this->name}">
    <td class="class">
QUOTE;
    $form .= $this->class->HTMLFormElement();
    $form .=
<<<QUOTE
    </td>
    <td class="day">
QUOTE;
    $form .= $this->day->HTMLFormElement();
    $form .=
<<<QUOTE
    </td>
    <td class="time">
QUOTE;
    $form .= $this->time->HTMLFormElement();
    $form .=
<<<QUOTE
    </td>
QUOTE;
    $form .=
<<<QUOTE
  </tr>

QUOTE;
    return $form;
  }

  // Custome isvalid
  function isvalid($value) {
    if ((! $this->required) && empty($value)) {
      return true;
    }
    return $this->class->isvalid($value['class']) &&
      ((! $this->class->choice()->daytime) ||
       ($this->day->isvalid($value['day']) &&
        $this->time->isvalid($value['time'])));
  }
  
  // Custom parser
  function parseValue($source=null) {
    $value = array();
    if ($this->class->isPresent($source) && $this->class->parseValue($source)) {
      $value['class'] = $this->class->value;
      if ($this->class->choice()->daytime) {
        if ($this->day->isPresent($source) && $this->day->parseValue($source)) {
          $value['day'] = $this->day->value;
        }
        if ($this->time->isPresent($source) && $this->time->parseValue($source)) {
          $value['time'] = $this->time->value;
        }
      }
    }
    $this->setValue($value);
    return $this->valid;
  }
  
  function errorMessage() {
    /*
    if (! $this->class->valid) { return $this->class->errorMessage(); }
    if ($this->class->choice()->daytime) {
      if (! $this->day->valid) { return $this->day->errorMessage(); }
      if (! $this->time->valid) { return $this->time->errorMessage(); }
    }
    */
    if ($this->class->valid && $this->class->choice()->daytime) {
      if (! $this->day->valid) {
        return "You must specify " . $this->day->description . " for " . $this->description . ".";
      } else if (! $this->time->valid) {
        return "You must specify " . $this->time->description . " for " . $this->description . ".";
      }
    }
    return parent::errorMessage();
  }


  function HTMLValue() {
    return $this->class->HTMLValue() . 
      ($this->class->choice()->daytime ?
        (" " .
          $this->day->HTMLValue() . " " .
          $this->time->HTMLValue()) :
        ("")
      );
  }
  
  function SQLValue() {
    die("Can't compute SQLValue for " . $this);
  }
  
  function TextValue() {
    die("Can't compute TextValue for " . $this);
  }

  function HTMLFormElement () {
    die("Can't compute HTMLFormElement for " . $this);
  }
  
  // Creates an SQL assignment expression for entering this field
  // into a database.
  // Adds a setter for the class type, for easier sorting...
  function SQLForm() {
    return $this->class->SQLForm() . 
      ($this->value && isset($this->value) ?
        (", {$this->name}_type = '" . addslashes($this->class->choice()->type) . "'") :
        ("")
      ) .
      ($this->class->choice()->daytime ?
        (", " .
          $this->day->SQLForm() . ", " .
          $this->time->SQLForm()) :
        ("")
      );
  }

  function TextForm() {
    if ($this->hasvalue()) {
      return $this->description . ": " .
        $this->class->TextValue() .       
        ($this->class->choice()->daytime ?
          (" on " .
            $this->day->TextValue() . " at " .
            $this->time->TextValue()) :
          ("")
        );
    }
    return "";
  }
  
  function EmailContact() {
    if ($this->valid) {
      return $this->class->choice()->contact;
    }
    return "";
  }

  function ClassType() {
    if ($this->valid) {
      return $this->class->choice()->type;
    }
    return "";
  }
}

///
// Multi-section form for Kingsbury.
//
// Probably generally useful -- could move into generic forms.
//
class KingsburyForm extends Form {
  var $last = 0;
  var $sectionName;
  var $sections;

  function KingsburyForm($name, $action, $method="post") {
    parent::Form($name, $action, $method);
    $this->sections = array();
  }
  
  function finishSection($footer="") {
    if (! empty($footer)) {
      $this->fields[] = $footer;
    }
    if ($this->last < count($this->fields)) {
      $this->sections[$this->sectionName] = array_slice($this->fields, $this->last);
      $this->last = count($this->fields);
    }
  }
  
  // Takes a string that will be inserted into the table
  function startSection($name, $header="") {
    $this->finishSection();
    $this->sectionName = $name;
    if (! empty($header)) {
      $this->fields[] = $header;
    }
  }
    
  // Override this to build multiple tables because the
  // ClassDayTimeFormField's are too big
  function HTMLFormTable() {
    $html = "";
    foreach ($this->sections as $name => $fields) {
      $html .=
<<<QUOTE
    <table class="{$name}">
    <col class="label" /><col class="field" /><col class="annotation" />
QUOTE;
      foreach ($fields as $field) {
        if ($field instanceof FormField) {
          $html .= $field->HTMLTableRow();
        } else {
          $html .= $field;
        }
      }
      $html .=
<<<QUOTE
    </table>
QUOTE;
    }
    return $html;
  }
  
  // Find the email contact(s) for the selected class(es)
  function EmailContact () {
    $email = "";
    foreach ($this->sections as $name => $fields) {
      foreach ($fields as $field) {
        if ($field instanceof ClassDayTimeFormField &&
            $field->valid &&
            isset($field->value)) {
          $contact = $field->EmailContact();
          if (! empty($contact)) {
            if (! strstr($email, $contact)) {
              if (! empty($email)) { $email .= ","; }
              $email .= $contact;
            }
          }
        }
      }
    }
    return $email;
  }

  // Find the class type(s) for the selected class(es)
  function ClassType () {
    $type = "";
    foreach ($this->sections as $name => $fields) {
      foreach ($fields as $field) {
        if ($field instanceof ClassDayTimeFormField &&
            $field->valid &&
            isset($field->value)) {
          $ct = $field->ClassType();
          if (! empty($ct)) {
            if (! strstr($type, $ct)) {
              if (! empty($type)) { $type .= ","; }
              $type .= $ct;
            }
          }
        }
      }
    }
    return $type;
  }
}

?>
