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
                          $options=array('directory' => 'files', 'maxsize' => 8388608)) {
    parent::PatternFormField($name, $description, $optional, $annotation, $instance=NULL, $priority=0);
    $this->directory = array_key_exists('directory', $options) ? $options['directory'] : 'files';
    $this->maxsize = array_key_exists('maxsize', $options) ? $options['maxsize'] : 8388608;;
    $this->type = "file";
    $this->title = "file";
    $this->placeholder = "file.ext";
    // Limit how crazy the file name can be
    $this->pattern = "/^(\\w| |[-_\\.]){1,64}$/";
  }

  function HTMLTableColumn() {
    // Once a file has been uploaded, we just display the filename
    // The user can choose a different file by erasing the filename
    if ($this->isvalid($this->value)) {
      $this->type = 'text';
      $this->readonly = 'true';
    } else {
      $this->type = 'file';
    }
    $element =
<<<QUOTE

      <input type="hidden" name="MAX_FILE_SIZE" value="{$this->maxsize}">
QUOTE;
    // For unknown reasons, the MAX_FILE_SIZE input has to come first
    $element .= parent::HTMLTableColumn();

    return $element;
  }
  
  function errorMessage() {
    $message = parent::errorMessage();
    if ($this->error) {
      $message .= " (" . $this->error . ")";
    }
    return $message;
  }
  
  
  // Tests if a value is valid for this field
  function isvalid($value) {
    global $debugging;
    switch (true) {
      case (! parent::isvalid($value)):
        $this->error = "File name must be short and simple.";
        return false;
      case (! is_readable($this->filepath($value))):
        $this->error = "File inaccessible";
        if ($debugging) {
          $this->error .= ": " . $this->filepath($value);
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
  
  function validType($tempname) {
    return true;
  }
  
  function moveFile($tempname, $filepath) {
    return move_uploaded_file($tempname, $filepath);
  }
  
  // Implements "parsing" for an uploaded file
  function parseValue($source=NULL) {
    global $debugging;
    if ($source == NULL) { $source = $_POST; }
    $filename = '';
    $valid = false;
    $input = $this->input;
    $uploading = ($source == $_POST && array_key_exists($input, $_FILES));
    if ($uploading) {
      // We are coming from a posted form
      // which uploaded a file
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
            if ($debugging) {
              $this->error .= ": " . $_FILES[$input]["error"];
            }
            break;
          case (! is_uploaded_file($tempname)):
            $this->error = "Invalid file";
            if ($debugging) {
              $this->error .= ": " . $tempname;
            }
            break;
          case (! $this->validType($tempname)):
            // validType sets error
            break;
          case ($size < 0 || $size > $this->maxsize):
            $this->error = "File must be less than {$this->maxsize} bytes.";
            if ($debugging) {
              $this->error .= ": " . $size;
            }
            break;
          case (!is_writable($this->filepath(""))):
            $this->error = "Directory inaccessible";
            if ($debugging) {
              $this->error .= ": " . $this->filepath("");
            }
            break;
          // Try to move it
          case (! $this->moveFile($tempname, $filepath)):
            $this->error = "Error moving";
            if ($debugging) {
              $this->error .= ": " . $tempname . " => " . $filepath;
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
  var $img;
  
  function ImageFormField ($name, $description, $optional=false, $annotation="", $instance=NULL, $priority=0,
                          $options=array('directory' => 'images', 'maxsize' => 8388608, 'store' => NULL, 'width' => NULL, 'height' => NULL)) {
    parent::FileFormField($name, $description, $optional, $annotation, $instance, $priority, $options);
    if (array_key_exists('store', $options)) {
       $this->store = $options['store'];
    }
    if (array_key_exists('width', $options)) {
      $this->width = $options['width'];
    }
    if (array_key_exists('height', $options)) {
      $this->height = $options['height'];
    }

    $this->title = "image";
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

  function HTMLTableColumn() {
    $element = parent::HTMLTableColumn();
    // Can't use $this->valid as the form starts out valid
    if ($this->isvalid($this->value)) {
      switch (true) {
        case ($this->store == 'sql' && $this->form->fieldHasValue('id')):
          $id = urlencode($this->form->fieldValue('id'));
          $element .=
<<<QUOTE

        <img src='fetchasset.php5?&i={$id}' />
QUOTE;
          break;
        case ($this->store == 'file'):
        // sql that has not yet been stored
        default:
          $webpath = $this->webpath($this->value);
          $element .=
<<<QUOTE

        <img src='{$webpath}' />
QUOTE;
          break;
      }
    }
    return $element;
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
      if ($debugging) {
        $this->error .= ": " . $mime_type;
      }
      return false;
    }
    return true;
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
    if ($w && (!$h)) { $h = $w; }
    if ($h && (!$w)) { $w = $h; }
    list($width, $height, $image_type) = getimagesize($tempfile);
    $source_aspect = $width / $height;
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
  
  function isvalid($value) {
    // Bit of a kludge
    if ($value == "{$this->name}.png") {
      return true;
    }
    return parent::isvalid($value);
  }
  
  
  function parseValue($source=NULL) {
    if ($source == NULL) { $source = $_POST; }
    $input = $this->input;
    if (($this->store == 'sql') && ($source != $_POST)) {
      $this->value = "{$this->name}.png";
      $valid = array_key_exists($input, $source);
      if ($valid) {
        $this->img = $source[$input];
      }
      return $this->valid = $valid;
    }
    $valid = parent::parseValue($source);
    if ($valid) {
      $filepath = $this->filepath($this->value);
      $this->img = file_get_contents($filepath);
    }
    return $valid;
  }

  function SQLType() {
    return "LONGBLOB";
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
