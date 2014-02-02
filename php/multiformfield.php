<?php

///
// Special forms to support callitrope clients
//

///
// A field of subfields
//
class FormMultiField extends FormField {
  var $fieldspecs;
  var $fields;
  var $showlabels = false;
  
  function FormMultiField ($name, $description, $fields, $optional=false, $options = NULL) {
    // default options
    $defaultoptions = array(
      // Back-compatibility, $options used to be $annotation
      'annotation' => is_string($options) ? $options : ''
      ,'showlabels' => false
    );
    $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;

    $this->showlabels = $options['showlabels'];  
    $this->fieldspecs = $fields;
    parent::FormField($name, $description, $optional, $options);
  }

  function setInstance($instance=NULL) {
    parent::setInstance($instance);
    foreach ($this->fieldspecs as $key => $field) { 
      $field->setInstance($instance);
    }
  }
  
  function setForm($form) {
    parent::setForm($form);
    $fields = array();
    foreach ($this->fieldspecs as $spec) {
      // Add the field (also sets the form on the field)
      // And if it is a field (result is non-null) add 
      // it to our list of fields
      if ($field = $form->addField($spec, true)) {
        $fields[$field->id] = $field;
      }
    }
    $this->fields = $fields;
  }

  // A non-required field is only valid if all of its required
  // subfields are either valid or empty
  function isvalid ($value) {
    $valid = true;
    // Initially, required comes from us
    $required = $this->required;
    foreach ($this->fields as $key => $field) {
      if ($field->isvalid($value[$key])) {
        if ($field->required) {
          // We got a valid required field, that makes us required
          $required = true;
        }
      } else {
        if ($field->required) {
          // We got an invalid required field, that makes us invalid
          $valid = false;
        }
      }
    }
    return (! $required) || $valid;
  }
  
  function errorMessage() {
    foreach ($this->fields as $key => $field) {
      if ($field->required && (! $field->valid)) {
        return "You must specify " . $field->description . " for " . $this->description . ".";
      }
    }
    return parent::errorMessage();
  }
    
  function canonical($value) {
    $result = array();
    foreach ($this->fields as $key => $field) {
      $result[$key] = $field->canonical($value[$key]);
    }
    return $result;
  }
  
  function isPresent($source) {
    // If any sub-field is present, we are present
    foreach ($this->fields as $key => $field) {
      if ($field->isPresent($source)) { return true; }
    }
    return false;
  }  
  
  // If any of the sub-fields are required, but the field itself is
  // optional, we either require all or none of the required subfields
  // to be valid.
  function parseValue($source=null) {
    $value = array();
    $valid = true;
    // Initially, required comes from us
    $required = $this->required;
    foreach ($this->fields as $key => $field) {
      if ($field->isPresent($source)) {
        $field->parseValue($source);
        $value[$key] = $field->value;
      }
    }
    $this->setValue($value);
    return $this->valid;
  }

  // Custom column builder
  function HTMLTableColumn() {
    $element =
<<<QUOTE
      <fieldset id="{$this->id}">
QUOTE;
    foreach ($this->fields as $key => $field) {
      $element .=
<<<QUOTE
        <div class="{$field->name}">
QUOTE;
      $element .= ($this->showlabels ? $field->HTMLTableRow(true) : $field->HTMLTableColumn());
      $element .=
<<<QUOTE
        </div>
QUOTE;
    }
<<<QUOTE
      </fieldset>
QUOTE;
    return $element;
  }
    
  function HTMLValue() {
    $result = "";
    foreach ($this->fields as $key => $field) {
      $result .= ($result ? " " : "") . $field->HTMLValue();
    }
    return $result;
  }
  
  function SQLValue() {
    $result = "";
    foreach ($this->fields as $key => $field) {
      $result .= ($result ? ", " : "") . $field->SQLValue();
    }
    return $result;
  }
  
  function TextValue() {
    $result = "";
    foreach ($this->fields as $key => $field) {
      $result .= ($result ? " " : "") . $field->TextValue();
    }
    return $result;
  }

  function HTMLFormElement () {
    // Should only be called by HTMLTableColumn, which never happens for this
    // class
    die("Shouldn't happen: HTMLFormElement for " . $this);
  }
  
  // Creates an SQL assignment expression for entering this field
  // into a database.
  function SQLForm() {
    $result = "";
    foreach ($this->fields as $key => $field) {
      $result .= ($result ? ", " : "") . $field->SQLForm();
    }
    return $result;
  }
  
  function SQLTableColumn() {
    $result = "";
    foreach ($this->fields as $key => $field) {
      $result .= ($result ? ", " : "") . $field->SQLTableColumn();
    }
    return $result;
  }
  
  
  // Create an SQL expression that will fetch the field's canonical value
  function SQLField() {
    $result = "";
    foreach ($this->fields as $key => $field) {
      $result .= ($result ? ", " : "") . $field->SQLField();
    }
    return $result;
  }  

  function TextForm($brief=false) {
    $instance = is_null($this->instance) ? '' : " $this->instance";

    $label = $brief ? "{$this->id}" : "{$this->description}{$instance}";
    $label = $this->addLabelColon($label);
    $result = "";
    $sep = $brief ? '; ' : "\n  ";
    foreach ($this->fields as $key => $field) {
      $result .= ($result ? $sep : "") . $field->TextForm($brief);
    }
    return $label . ($brief ? ' ' : "\n  ") . $result;
  }
}

?>
