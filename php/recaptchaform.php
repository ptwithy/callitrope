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
// A FormField that implements a CAPTCHA validator
//

require_once('recaptchalib.php');

class ReCaptchaFormField extends FormField {
  var $publickey;
  var $error = null;

  function ReCaptchaFormField($name, $description, $mykeys, $options=NULL) {
    // default options
    $defaultoptions = array(
      // Highest priority element, so it is only checked when everything else
      // is correct
      ,'priority' => PHP_INT_MAX
    );
    $this->options = $options = is_array($options) ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::FormField($name, $description, false, $options);
    $this->value = false;
    $this->valid = false;
    $this->publickey = $mykeys['publickey'];
    $this->privatekey = $mykeys['privatekey'];
  }

  // Custom parser
  function parseValue($source=NULL) {
    if ($source == NULL) { $source = $_POST; }
    // This allows you to have a dynamic form -- we won't check
    // fields that didn't get posted.
    if (! (array_key_exists("recaptcha_challenge_field", $source) &&
          array_key_exists("recaptcha_response_field", $source))) {
      return true;
    }
    $resp = recaptcha_check_answer($this->privatekey,
                                   $_SERVER["REMOTE_ADDR"],
                                   $source["recaptcha_challenge_field"],
                                   $source["recaptcha_response_field"]);
    if (!$resp->is_valid) {
      $this->value = false;
      $this->valid = false;
      $this->error = $resp->error;
    } else {
      $this->value = true;
      $this->valid = true;
      $this->error = null;
    }
    return $this->valid;
  }

  // Custom error message
  function errorMessage() { return "Invalid reCAPTCHA response"; }
   
  // Custom form element
  function HTMLFormElement() {
    return recaptcha_get_html($this->publickey, $this->error);
  }

  // Doesn't go in database
  function SQLForm() { return null; }
  function SQLValue() { return null; }

  // Doesn't go in email
  function TextForm($brief=false) { return ""; }
}

?>
