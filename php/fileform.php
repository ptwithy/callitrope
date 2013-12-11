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

/**
 * File form field
 *
 * Prompts for a file to upload
 *
 * Key options:
 *   - directory: the directory to upload the files to
 *   - mazsize: limit on upload file size
 */
class FileFormField extends PatternFormField {
  var $directory;
  var $maxsize;
  var $error = "";
  
  // Create a form field.  Arguments are:
  // @param name:String The name of the field
  // @param description:String The English description of the field
  // @param optional:Boolean (optional) True if the field is not
  // required
  // @param annotation:String (optional) Additional description that
  // will appear to the right of the form
  // @param directory:String (optional) Name of the directory files
  // should be uploaded to
  function FileFormField ($name, $description, $optional=false, $options=NULL) {
    $defaultoptions = array(
      'type' => 'file'
      ,'title' => 'file'
      ,'placeholder' => 'file.ext'
      // Limit how crazy the file name can be
      ,'pattern' => "/^(\\w| |[-_\\.]){1,64}$/"
      ,'directory' => 'files'
      ,'maxsize' => 8388608
    );
    $options = $options ? array_merge($defaultoptions, $options) : $defaultoptions;
    parent::PatternFormField($name, $description, $optional, $options);
    $this->directory = $options['directory'];
    $this->maxsize = $options['maxsize'];
  }

  function HTMLTableColumn() {
    $element =
<<<QUOTE

      <input type="hidden" name="MAX_FILE_SIZE" value="{$this->maxsize}">
      <!-- Ensures our value is returned to us -->
      <input type="hidden" name="{$this->input}" id="{$this->id}_hidden" value="{$this->value}">
QUOTE;
    // For unknown reasons, the MAX_FILE_SIZE input has to come first
    $element .= parent::HTMLTableColumn();
    // File forms can be inscrutible if they are autosubmitted (e.g., images)
    if ($this->autosubmit && (! empty($this->value)) && (! $this->isvalid($this->value))) {
      $msg = "'{$this->HTMLValue()}' is not a valid {$this->title}";
      if ($this->error) {
        $msg .= " ({$this->error})";
      }
      $element .=
<<<QUOTE

    <p class="errortext">{$msg}</p>
QUOTE;
    }
    return $element;
  }
  
  function errorMessage() {
    $message = parent::errorMessage();
    if ($this->error) {
      $message .= " ({$this->error})";
    }
    return $message;
  }
  
  function contentAccess() {
    global $debugging;
    if (is_readable($this->filepath($this->value))) {
      return 'file';
    } else {
      return NULL;
    }
  }

  function contentAccessValid() {
    switch ($this->contentAccess()) {
      case 'file':
        return true;
      default:
        return false;
    }
  }
  
  // Tests if a value is valid for this field
  function isvalid($value) {
    global $debugging;
    switch (true) {
      case (! parent::isvalid($value)):
        $this->error = "File name must be short and simple";
        if ($debugging > 1) {
          $this->error .= " [" . $value . "]";
        }
        return false;
      case (! $this->contentAccessValid()):
        $this->error = "Content inaccessible";
        if ($debugging > 1) {
          $this->error .= " [" . $this->filepath($value) . "]";
        }
        return false;
      default:
        return true;
    }
  }
  
  function webpath($filename = NULL) {
    if ($filename == NULL) { $filename = $this->value; }
    return '/' . $this->directory . '/' . $filename;
  }
  
  function filepath($filename = NULL) {
    return $_SERVER['DOCUMENT_ROOT'] . $this->webpath($filename);
  }
  
  function dirpath() {
    return $this->filepath("");
  }
  
  function validType($tempname) {
    return true;
  }
  
  function moveFile($tempname, $filepath) {
    return move_uploaded_file($tempname, $filepath);
  }
  
  function uploading($source) {
    $input = $this->input;
    // We are only uploading if we actually got a file
    if (($source == $_POST) &&
        array_key_exists($input, $_FILES) &&
        array_key_exists('tmp_name', $_FILES[$input]) &&
        (! empty($_FILES[$input]['tmp_name']))) {
      return true;
    }
    return false;
  }

  // This allows you to have a dynamic form -- we won't check
  // fields that didn't get posted.
  function isPresent($source) {
    global $debugging;
    $uploading = $this->uploading($source);
    if ($debugging > 2) {
      echo "<pre>uploading: {$uploading} </pre>";
    }
    return ($uploading || parent::isPresent($source));
  }
  
  // Implements "parsing" for an uploaded file
  function parseValue($source) {
    global $debugging;
    $filename = '';
    $valid = false;
    $input = $this->input;
    $uploading = $this->uploading($source);
    if ($uploading) {
      $filename = $_FILES[$input]['name'];
    } else if (array_key_exists($input, $source)) {
      $filename = $source[$input];
    }
    if ((! empty($filename)) && $this->isvalid($filename)) {
      $filename = $this->canonical($filename);
      $filepath = $this->filepath($filename);
      if ($uploading) {
        $tempname = $_FILES[$input]['tmp_name'];
        $size = $_FILES[$input]['size'];
        switch (true) {
          case ($_FILES[$input]["error"] != 'UPLOAD_ERROR_OK'):
            $this->error = "Error uploading";
            if ($debugging > 1) {
              $this->error .= " [" . $_FILES[$input]["error"] . "]";
            }
            break;
          case (! is_uploaded_file($tempname)):
            $this->error = "Invalid file";
            if ($debugging > 1) {
              $this->error .= " [{$tempname}]";
            }
            break;
          case (! $this->validType($tempname)):
            // validType sets error
            break;
          case ($size < 0 || $size > $this->maxsize):
            $this->error = "File must be less than {$this->maxsize} bytes";
            if ($debugging > 1) {
              $this->error .= " [{$size}]";
            }
            break;
          case (!is_writable($this->dirpath())):
            $this->error = "Directory inaccessible";
            if ($debugging > 1) {
              $this->error .= " [" . $this->dirpath() . "]";
            }
            break;
          // Try to move it
          case (! $this->moveFile($tempname, $filepath)):
            $this->error = "Error moving";
            if ($debugging > 1) {
              $this->error .= " [{$tempname} => {$filepath}]";
            }
            break;
          default:
            // Make readable by web server
            chmod($filepath, 0644);
            break;
        }
      }
    }
    if ($this->isvalid($filename)) {
      $valid = true;
    }
    $this->value = $filename;
    $this->valid = $valid;
    return $valid;
  }
}



/**
 * Image form field
 *
 * Prompts for an image to upload
 *
 * Key options:
 *   - directory: the directory to upload the images to
 *     must be accessible to the browser if you want previews
 *   - mazsize: limit on upload image size
 *   - width, height:  specify either or both to resize on upload
 *   - crop: true or a set of Jcrop options to crop first
 *
 * Relies on http://deepliquid.com/content/Jcrop.html jQuery library
 * which must be installed at:
 *   js/jquery.Jcrop.min.js
 *   css/jquery.Jcrop.css
 */
class ImageFormField extends FileFormField {
  var $store;
  var $width;
  var $height;
  var $img;
  var $path;
  var $info;
  var $crop;
  
  function ImageFormField ($name, $description, $optional=false, $options=NULL) {
    $defaultoptions = array(
      'title' => 'image'
      ,'placeholder' => 'name.png'
      // This lets us refresh the form with the chosen image
      ,'autosubmit' => true
      ,'directory' => 'images'
      ,'store' => NULL
      ,'width' => NULL
      ,'height' => NULL
      ,'crop' => NULL
    );
    $options = ($options ? array_merge($defaultoptions, $options) : $defaultoptions);
    parent::FileFormField($name, $description, $optional, $options);
    $this->store = $options['store'];
    $this->width = $options['width'];
    $this->height = $options['height'];
    $this->crop = $options['crop'];
  }

  function setForm($form) {
    parent::setForm($form);
    if ($this->store == NULL) {
      if ($form instanceof DatabaseForm) {
        $this->store = 'sql';
      } else {
        $this->store = 'file';
      }
    }
  }

  function HTMLFormElement() {
    global $debugging;
    $valid = (! empty($this->value)) && $this->isvalid($this->value);
    if ($debugging > 1) {
      echo "<pre>valid: {$valid}; value: {$this->value}; path: {$this->path}</pre>";
    }
    $element = "";
    $cropping = $this->crop && $this->contentAccess() == 'file';
    // get all the attributes we would normally give the input field
    $additional = $this->additionalInputAttributes();
    $form = $this->form;
    $formname = $form->name;
    if ($valid) {
      $info = $this->info;
      // See http://www.quirksmode.org/dom/inputfile.html
      // As modified by ptw:
      // Depending on whether we are cropping or not, we overlay the image
      // with a transparent file input button that auto-submits, or create 
      // an off screen file input
      if (! $cropping) {
        $element .= <<<QUOTE

          <div style="position: relative; overflow: hidden;">
            <div style="position: relative; z-index: 1;">
QUOTE;
      }
      // Here is the image
      $element .= $this->HTMLValue();
      if (! $cropping) {
        $element .= <<<QUOTE

            </div>
            <!-- Invisible file button ovelays the image -->
            <!-- text-align and font-size are to push the text box out of the frame -->
            <input
              name="{$this->input}" id="{$this->id}" type="{$this->type}"{$additional} value="{$this->value}"
              onmouseover="{this.title = 'click to edit'; this.style.cursor = 'pointer'}"
              onchange="document.getElementById('${formname}').submit()"
              style="
                position: absolute;
                top: 0px;
                left: 0px;
                margin: 0;
                border: none;
                padding: 0;
                text-align: left;
                -moz-opacity:0 ;
                filter:alpha(opacity: 0);
                opacity: 0;
                z-index: 2;
                font-size: {$info['height']}px;
                width: {$info['width']}px; height: {$info['height']}px;
              "
            />
          </div>
QUOTE;
      }
    } // End valid
    if ($cropping || (! $valid)) {
      $element .= <<<QUOTE
      
          <!-- Off-screen input for when cropping or not valid -->
          <input
            name="{$this->input}" id="{$this->id}" type="{$this->type}"{$additional} value="{$this->value}"
            onchange="document.getElementById('${formname}').submit()"
            style="position: absolute; left: -9999px;"
          />
QUOTE;
    }      
    $element .= <<<QUOTE

        <div class="buttons">
QUOTE;
    if ($valid && $cropping) {
      $element .= <<<QUOTE

          <!-- Cropping inputs -->
          <input type="hidden" id="{$this->id}_x" name="{$this->id}_x" />
          <input type="hidden" id="{$this->id}_y" name="{$this->id}_y" />
          <input type="hidden" id="{$this->id}_w" name="{$this->id}_w" />
          <input type="hidden" id="{$this->id}_h" name="{$this->id}_h" />
          <input type="submit" id="{$this->id}_crop" name="{$this->id}_crop" value="Crop Image" />
QUOTE;
    }
    $label = $valid ? "Replace Image" : "Choose Image";
    // We use a label for the file input around a normal button to trigger the
    // file input and still auto-submit without IE setting off an alarm
    // Finally we have to kludge around IE only passing clicks to labels that
    // contain text, not images...
    $element .= <<<QUOTE

          <!-- styleable replace/choose button -->
          <!-- using label to intecept click and send it to the file input element -->
          <!-- so we don't trigger security alerts on IE -->
          <label for="{$this->id}" id="{$this->id}_label">
            <!-- IE will only let you click on text in a label, not an image -->
            <!--[if IE]>
            <div style="position: relative; overflow: hidden;">
              <div style="position: relative; z-index: 1;">
                <input type="button" id="{$this->id}_button" value="{$label}" />
              </div>
              <div
                  style="
                    position: absolute;
                    top: 0px;
                    left: 0px;
                    margin: 0;
                    border: none;
                    padding: 0;
                    -moz-opacity:0 ;
                    filter:alpha(opacity: 0);
                    opacity: 0;
                    z-index: 2;
                    font-size: 50px;
                    cursor: default;
                  "
                >
                {$label}
              </div>
            </div>
            <![endif]-->
            <!--[if !IE]> -->
              <!-- This button is for "display only", so it looks like all other buttons -->
              <input type="button" id="{$this->id}_button" value="{$label}" />
            <!-- <![endif]-->
          </label>
        </div>
QUOTE;
    return $element;
  }
  
  function validType($tempname) {
    global $debugging;
    if (! parent::validType($tempname)) {
      return false;
    }
    list($width, $height, $image_type) = getimagesize($tempname);
    if(($image_type != IMAGETYPE_JPEG) && ($image_type != IMAGETYPE_GIF) && ($image_type != IMAGETYPE_PNG)) {
      $this->error = "Image must be jpeg, gif, or png";
      if ($debugging > 1) {
        $this->error .= " [not {$image_type}]";
      }
      return false;
    }
//     if ($this->crop && ($this->width != $width || $this->height != $height)) {
//       $this->error = "Image must be cropped first.";
//       if ($debugging > 1) {
//         $this->error .= " [{$width} != {$this->width} || {$height} != {$this->height}]";
//       }
//       return false;
//     }
    return true;
  }
  function contentAccess() {
    global $debugging;
    $access = parent::contentAccess();
    if ($access != NULL) {
      return $access;
    } else if ($this->store == 'sql') {
      return 'sql';
    } else {
      return NULL;
    }
  }

  function contentAccessValid() {
    switch ($this->contentAccess()) {
      case 'sql':
        return true;
      default:
        return parent::contentAccessValid();
    }
  }

  // Resizes the file before moving it if width/height are set
  function moveFile($tempname, $filepath) {
    if ($this->crop || ($this->width == NULL) && ($this->height == NULL)) {
      return parent::moveFile($tempname, $filepath);
    } else {
      return $this->moveFileWithResize($tempname, $filepath, $this->width, $this->height);
    }
  }

  // http://stackoverflow.com/questions/14649645/resize-image-in-php
  // http://salman-w.blogspot.com/2008/10/resize-images-using-phpgd-library.html
  function moveFileWithResize($tempfile, $filepath, $w, $h, $crop=NULL) {
    list($width, $height, $image_type) = getimagesize($tempfile);
    switch ($image_type) {
      case IMAGETYPE_GIF: $src = imagecreatefromgif($tempfile); break;
      case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($tempfile); break;
      case IMAGETYPE_PNG: $src = imagecreatefrompng($tempfile); break;
      default:  trigger_error('Unsupported filetype!', E_USER_WARNING);  break;
    }
    unlink($tempfile);
    $x = 0;
    $y = 0;
    if (is_array($crop)) {
      if ($crop['x'] > 0) {
        $x = $crop['x'];
      }
      if ($crop['y'] > 0) {
        $y = $crop['y'];
      }
      if ($crop['w'] > 0) {
        $width = $crop['w'];
      }
      if ($crop['h'] > 0) {
        $height = $crop['h'];
      }
    }
    $source_aspect = $width / $height;
    if ($w && (!$h)) { $h = $w / $source_aspect; }
    if ($h && (!$w)) { $w = $h * $source_aspect; }
    $dest_aspect = $w / $h;

    if ($dest_aspect > $source_aspect) {
      $newwidth = $h * $source_aspect;
      $newheight = $h;
    } else {
      $newwidth = $w;
      $newheight = $w / $source_aspect;
    }
    $dst = imagecreatetruecolor($newwidth, $newheight);
    imagecopyresampled($dst, $src, 0, 0, $x, $y, $newwidth, $newheight, $width, $height);
    imagedestroy($src);
    // We always resize to png
    $result = imagepng($dst, $filepath);
    imagedestroy($dst);
    return $result;
  }
  
  function setImage($string) {
    // delete any previous
    $this->clearImage();
    $_SESSION[$this->id] = $this->img = $string;
    // Get info about image -- wish we had `getimagesizefromstring` not available until PHP 5.2
    $img = imagecreatefromstring($string);
    $this->info = array(
      'width' => imagesx($img)
      ,'height' => imagesy($img));
    $this->path = $this->filepath($this->value);
    imagedestroy($img);
  }
  
  function clearImage() {
    unset($_SESSION[$this->id]);
    unset($this->img);
    unset($this->info);
    if (isset($this->path) && file_exists($this->path)) {
      if (! unlink($this->path)) {
        trigger_error("Could not unlink '{$this->path}'", E_USER_WARNING);
      }
    }
    unset($this->path);
  }    
  
  function parseValue($source) {
    global $debugging;
    $input = $this->input;

    $valid = parent::parseValue($source) && (! empty($this->value)) && ($this->contentAccess() == 'file');
    if ($debugging > 2) {
      echo "<pre>valid: {$valid}; value: {$this->value}</pre>";
    }
    if ($valid) {
      $filepath = $this->filepath($this->value);
      $cropbutton = $this->id . '_crop';
      if ($this->crop && isset($source[$cropbutton]) && $source[$cropbutton] == 'Crop Image') {
        $crop = array();
        $crop['x'] = $source[$this->id . '_x'];
        $crop['y'] = $source[$this->id . '_y'];
        $crop['h'] = $source[$this->id . '_h'];
        $crop['w'] = $source[$this->id . '_w'];
        $this->moveFileWithResize($filepath, $filepath, $this->width, $this->height, $crop);
      }
      $this->setImage(file_get_contents($filepath));
      return $this->valid = $valid;
    }
    if ($debugging > 2) {
      echo "<pre>source: "; print_r($source); echo "</pre>";
    }
    if ($debugging > 1) {
      echo "<pre>uploading: " . ($this->uploading($source) ? 'true' : 'false') . "</pre>";
      echo "<pre>array_key_exists(\$input, \$source): ". (array_key_exists($input, $source) ? 'true' : 'false') . "</pre>";
      echo "<pre>isset(\$this->id, \$_SESSION): ". (isset($this->id, $_SESSION) ? 'true' : 'false') . "</pre>";
    }
    if (($this->contentAccess() == 'sql') && (! $this->uploading($source))) {
      if (($source != $_POST) && isset($source[$input])) {
        $this->value = 'sql';
        $this->setImage($source[$input]);
        return $this->valid = true;
      }
      if (isset($_SESSION[$this->id])) {
        $this->value = 'sql';
        $this->setImage($_SESSION[$this->id]);
        return $this->valid = true;
      }
    }
    return $this->valid = $valid || (! $this->required);
  }

  static $onetime = '

    <!-- jquery needed for image cropping -->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="js/jquery.Jcrop.min.js"></script>
    <link rel="stylesheet" href="css/jquery.Jcrop.css" type="text/css" />
';  
  
  function head() {
    parent::head();
    if ($this->crop && $this->contentAccess() == 'file') {
      echo self::$onetime;
      self::$onetime = "";

      $options = "";
      if (is_array($this->crop)) {
        foreach ($this->crop as $key => $value) {
          $options .= "{$key}: " . PHPtoSQL($value) . ",";
        }
      }
      echo <<<QUOTE
      
        <script type="text/javascript">
          \$(function(){
            \$('#{$this->id}_img').Jcrop({
              {$options}
              onSelect: {$this->id}_updateCoords
            });
          });

          function {$this->id}_updateCoords(c)
          {
            \$('#{$this->id}_x').val(c.x);
            \$('#{$this->id}_y').val(c.y);
            \$('#{$this->id}_w').val(c.w);
            \$('#{$this->id}_h').val(c.h);
          };
        </script>
QUOTE;
    }
  }

  function finalize() {
    if ($this->store == 'sql') {
      $this->clearImage();
    }
  }

  function SQLType() {
    return "LONGBLOB";
  }

  function HTMLValue() {
    // Can't use $this->valid as the form starts out valid
    $value = parent::HTMLValue();
    if ((! empty($this->value)) && $this->isvalid($this->value)) {
      switch ($this->contentAccess()) {
        case 'sql':
          $id = urlencode($this->form->recordID);
          $value = "<img id='{$this->id}_img' src='fetchasset.php5?&i={$id}' />";
          break;
        case 'file':
        default:
          $webpath = $this->webpath($this->value);
          $value = "<img id='{$this->id}_img' src='{$webpath}' />";
          break;
      }
    }
    return $value;
  }
  
  function SQLValue() {
    if ($this->hasvalue()) {
      // Really must use prepared statement!
      return "'" . addslashes($this->img) . "'";
    } else {
      return "DEFAULT";
    }
  }
}
?>
