<?php
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
  function FileFormField ($name, $description, $optional=false, $annotation="", $instance=NULL, $priority=0,
                          $options=NULL) {
    $defaultoptions = array('directory' => 'files', 'maxsize' => 8388608);
    $options = $options ? array_merge($defaultoptions, $options) : $options;
    parent::PatternFormField($name, $description, $optional, $annotation, $instance=NULL, $priority=0);
    $this->directory = $options['directory'];
    $this->maxsize = $options['maxsize'];
    $this->type = "file";
    $this->title = "file";
    $this->placeholder = "file.ext";
    // Limit how crazy the file name can be
    $this->pattern = "/^(\\w| |[-_\\.]){1,64}$/";
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
    if ($this->autosubmit && (! $this->isvalid($this->value))) {
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
        $this->error = "File name must be short and simple.";
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
    if (parent::isvalid($filename)) {
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
              $this->error .= " [" . $tempname . "]";
            }
            break;
          case (! $this->validType($tempname)):
            // validType sets error
            break;
          case ($size < 0 || $size > $this->maxsize):
            $this->error = "File must be less than {$this->maxsize} bytes.";
            if ($debugging > 1) {
              $this->error .= " [" . $size . "]";
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
              $this->error .= " [" . $tempname . " => " . $filepath . "]";
            }
            break;
          default:
            // Make readable by web server
            chmod($filepath, 0644);
            break;
        }
      }
      if ($this->isvalid($filename)) {
        $valid = true;
      }
    }
    $this->value = $filename;
    $this->valid = $valid;
    return $valid;
  }
}

class ImageFormField extends FileFormField {
  var $store;
  var $width;
  var $height;
  var $idname;
  var $img;
  var $path;
  var $info;
  
  function ImageFormField ($name, $description, $optional=false, $annotation="", $instance=NULL, $priority=0,
                          $options=NULL) {
    $defaultoptions = array('directory' => 'images', 'store' => NULL, 'width' => NULL, 'height' => NULL, 'idname' => 'id');
    $options = $options ? array_merge($defaultoptions, $options) : $options;
    parent::FileFormField($name, $description, $optional, $annotation, $instance, $priority, $options);
    $this->store = $options['store'];
    $this->width = $options['width'];
    $this->height = $options['height'];
    $this->idname = $options['idname'];

    $this->title = "image";
    // This lets us refresh the form with the chosen image
    $this->autosubmit = true;
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
    $valid = $this->isvalid($this->value);
    if ($debugging > 1) {
      echo "<pre>valid: {$valid}; value: {$this->value}; path: {$this->path}</pre>";
    }
    if ($valid) {
      $info = $this->info;
      // get all the attributes we would normally give the input field
      $additional = $this->additionalInputAttributes();
      // We have an invisible form element that will repost the value (filename)
      // Then a file button that is transparent and overlays the image
      // so the user can click to replace the image
      // then the image itself
      //
      // See http://www.quirksmode.org/dom/inputfile.html
      $element =
<<<QUOTE

          <!-- Ensures our value is returned to us -->
          <input type="hidden" name="{$this->input}" id="{$this->id}_hidden" value="{$this->value}">
          <div style="
            position: relative;
          ">
            <!-- Invisible file button ovelays the image -->
            <input
              name="{$this->input}" id="{$this->id}" type="{$this->type}"{$additional} value="{$this->value}"
              onmouseover="{this.title = 'click to edit'; this.style.cursor = 'pointer'}"
              onchange="document.forms[0].submit()"
              style="
                position: relative;
                text-align: right;
                -moz-opacity:0 ;
                filter:alpha(opacity: 0);
                opacity: 0;
                z-index: 2;
                width: {$info['width']}px; height: {$info['height']}px;
              "
            />
            <div
              style="
                position: absolute;
                top: 0px;
                left: 0px;
                z-index: 1;
              "
            >
QUOTE;
      $element .= $this->HTMLValue();
      $element .= <<<QUOTE
            </div>
          </div>
QUOTE;
      return $element;
    }
    // If there is no image yet, just 
    return parent::HTMLFormElement();
  }
  
  function validType($tempname) {
    global $debugging;
    if (! parent::validType($tempname)) {
      return false;
    }
    $imgdetails = getimagesize($tempname);
    $mime_type = $imgdetails['mime'];
    if(($mime_type != 'image/jpeg') && ($mime_type != 'image/gif') && ($mime_type != 'image/png')) {
      $this->error = "Image must be jpeg, gif, or png.";
      if ($debugging > 1) {
        $this->error .= ": " . $mime_type;
      }
      return false;
    }
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
    if (($this->width != NULL) || ($this->height != NULL)) {
      return $this->moveFileWithResize($tempname, $filepath, $this->width, $this->height);
    } else {
      return parent::moveFile($tempname, $filepath);
    }
  }

  // http://stackoverflow.com/questions/14649645/resize-image-in-php
  // http://salman-w.blogspot.com/2008/10/resize-images-using-phpgd-library.html
  function moveFileWithResize($tempfile, $filepath, $w, $h) {
    list($width, $height, $image_type) = getimagesize($tempfile);
    $source_aspect = $width / $height;
    if ($w && (!$h)) { $h = $w / $source_aspect; }
    if ($h && (!$w)) { $w = $h * $source_aspect; }
    $dest_aspect = $w / $h;

    if ($width < $w && $height < $h) {
      $newwidth = $width;
      $newheight = $height;
    } else if ($dest_aspect > $source_aspect) {
      $newwidth = $h * $source_aspect;
      $newheight = $h;
    } else {
      $newwidth = $w;
      $newheight = $w / $source_aspect;
    }
    switch ($image_type) {
      case IMAGETYPE_GIF: $src = imagecreatefromgif($tempfile); break;
      case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($tempfile); break;
      case IMAGETYPE_PNG: $src = imagecreatefrompng($tempfile); break;
      default:  trigger_error('Unsupported filetype!', E_USER_WARNING);  break;
    }
    unlink($tempfile);
    $dst = imagecreatetruecolor($newwidth, $newheight);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
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
    $valid = parent::parseValue($source) && ($this->contentAccess() == 'file');
    if ($debugging > 2) {
      echo "<pre>valid: {$valid}; value: {$this->value}</pre>";
    }
    if ($valid) {
      $filepath = $this->filepath($this->value);
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
    return $this->valid = $valid;
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
    if ($this->isvalid($this->value)) {
      switch ($this->contentAccess()) {
        case 'sql':
          $id = urlencode($this->form->fieldValue($this->idname));
          $value = "<img id='{$this->id}' src='fetchasset.php5?&i={$id}' />";
          break;
        case 'file':
        default:
          $webpath = $this->webpath($this->value);
          $value = "<img id='{$this->id}' src='{$webpath}' />";
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
