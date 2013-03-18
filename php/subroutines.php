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
// Master callitrope subroutines file
//
// Contains common php subroutines used by most callitrope client
// websites
//
// @author ptw@callitrope.com
///

///
// Helper function to report any errors
function print_error_and_exit () {
    global $debugging;
    echo "<p style='font-size: larger'>Error: Service Temporarily Unavailable. Please try again later.</p>";
    if ($debugging) {
        echo "<p style='font-size: smaller'>";
        echo "[Additional information: <span style='font-style: italic'>" . mysql_error() . "</span>]";
        echo "</p>";
    }
    exit;
}

///
// Validate an email address
//
// @param $email: the address to validate
// @param $dns: (optional) if true, verify that the host has DNS
// records
function is_email_valid($email, $dns=FALSE) {
    // Match address against reasonable pattern
    if (preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i',
                   $email)) {
        // Ignore these bozos
        if ($email == "sample@email.tst") return FALSE;
                   
        if (!$dns) return TRUE;

        // Verify that the domain exists
        list($uname, $domain) = explode("@" ,$email);

        if (checkdnsrr($domain + ".", "ANY")) {
            return TRUE;
        }
    }
    return FALSE;
}

///
// Find funny characters in a string
//
// @param $input: the string to check
// @param $multiline: (Optional) default false, set to true to allow multiple lines
function considered_harmful($input, $multiline=false) {
  // Construct pattern by quoting the punctuation you allow
  // pass '/' as the pattern delimiter
  // unquote '-' specially, since we are inside '[]'s
  $pattern = '/(\\w|' .
    ($multiline ? '\\s' : '[^\\S\r\n]') .
    // All non-alphanumeric characters other than \, -, ^ (at the
    // start) and the terminating ] are non-special in character
    // classes, and we have to escape the pattern delimiter /.
    '|[!@#$%&*()_\\-+=[\\]:;"\',.?\\/])*/';
  return preg_replace($pattern, '', $input);
}

///
// Functions to quote values for substituting in an SQL query or in
// generated HTML
//
// In SQL, you need to escape single-quotes with a slash to store them
// in a field as a string (which will be single-quoted.
// In HTML, you need to escape double-quotes (and other special
// characters) as HTML entities -- the double-quotes because they are
// what a PHP echo uses for its strings.

///
// SQL to HTML
//
// Escapes double-quotes and HTML special characters
function s2h($what) {
    //return htmlspecialchars($what, ENT_QUOTES);
    return htmlentities($what, ENT_QUOTES, "UTF-8");
}

///
// HTML to SQL
//
// Escapes single-quotes
function h2s($what) {
    return addslashes($what);
}

///
// remove spaces with trim and escape inner quotes with h2s
//
function clean($str) {
	return h2s(trim($str));
}

// 'underscored' SQL to HTML
//
// Field names in SQL cannot have spaces, so underscores are used.
// This translates a field name to English
function u2h($what) {
    return s2h(strtr($what, "_", " "));
}

///
// Used to display SQL errors
// e.g., 'mysql_query(...) or ode();'
function ode() {
    die("Error #". mysql_errno() . ": " . mysql_error());
}

///
// Create a subset of a 'lookup table' using a mask
//
// @param $set: a bitmask of keys to include
// @param $lookup: the lookup to subset
//
// Creates a subset of a 'lookup table' according to the mask $set.
// $lookup must have integer keys (typically id's), $set is a bitmask
// of which keys to include in the subset.  A typical use is to
// display the results of a multiple selection.
function subset_of_lookup($set, $lookup) {
    $subset = array();

    foreach($lookup as $key => $value) {
        if($set & (1 << ($key - 1))) {
            $subset[$key] = $value;
        }
    }
    return $subset;
}

///
// Convert a bitmap to a set (array of values)
function map_to_array($map) {
    // PHP does not support unsigned integers
    // This function will fail if $map is negative
    if ($map < 0) { die("Error: map $map is too big"); }

    $result = array();

    for($value = 0; $map; $map >>= 1, $value++) {
        if ($map & 1) {
            $result[] = $value;
        }
    }
    return $result;
}

///
// Convert a set (array of values) to a bitmap
function array_to_map($array) {
    $result = 0;

    // Treat no array as an empty array
    if ($array) {
        foreach($array as $value) {
            $bit = 1 << $value;
            // Fail if shift is bigger than an integer, or would set the
            // sign bit, since PHP does not support unsigned integers
            if ($bit <= 0) {
                die("Error: value $value out of range");
            }
            $result |= $bit;
        }
    }
    return $result;
}

///
// Create a 'lookup map' from an SQL table
//
// @param $db: the database
// @param $table: the table
// @param $key: key name
// @param $value: value name
// @param $sort: what to sort by (default $key)
// @param $where: SQL where_definition (default none)
//
// Creates a 'lookup map' (array indexed by names) mapping the values
// in the column named by $key to the values in the column named by
// $value.  Useful for creating menus, etc.
function lookup_from_table($db, $table, $key, $value, $sort = "", $where = "") {
    if (empty($sort)) { $sort = $key; }
    $selector = "SELECT $key AS 'key', $value AS 'value' FROM $table";
    if (! empty($where)) {
        $selector .= " WHERE $where";
    }
    $selector .= " ORDER BY $sort ASC";
    $query = mysql_query($selector, $db) or ode();
    $array = array();
    while ($object = mysql_fetch_object($query)) {
        $array[$object->key] = $object->value;
    }
    return $array;
}

///
// Create an array of 'lookup map's from ALL enum columns in an SQL table
//
// @param $db: the database
// @param $table: the table
function lookups_from_table_enums ($db, $table) {
	$selector = "SHOW COLUMNS FROM $table";
    $query = mysql_query($selector, $db) or ode();
	$array = array();
	
	while($row = mysql_fetch_object($query)) {
 		if(ereg('set|enum', $row->Type)) {
			// enums start at 1, 0 is an invalid entry
 		  $start = ereg('set', $row->Type) ? 0 : 1;
			$keys = eval(ereg_replace('set|enum', 'return array', $row->Type).';');
			$map = array();
			// If NULL is allowed, make the first map entry empty
			if($row->Null == 'YES') {
				$map[''] = NULL;
			}
			foreach ($keys as $index => $name) {
				$map[$name] = $index + $start;
			}
			$array[$row->Field] = $map;
	 	}
	}
	return $array;
}

///
// Create an HTML menu (<select>) from a 'lookup map'
//
// @param $name: the name of the 'select'
// @param $array: the 'lookup map'
// @param $select: (optional) a singleton or array of selected items
// @param $properties: (optional) any additional properties of the
// 'select'
// @param $bitch: (optional) if true complain if select is not in $array
//
// Creates an HTML menu (using <select>) from a 'lookup map' (an
// array) by using the array indices as keys and the values as
// values.  The $select parameter can be a singleton or an array of
// values that should be already selected (e.g., for editing an
// existing entry).  $properties supplies additional attributes in the
// 'select' tag, e.g., 'multiple'.
function menu_from_array($name, $array, $select = "", $properties = "", $bitch=false) {
    $html = "<select name=\"$name\" $properties>";
    if ($bitch) {
      if (is_array($select)) {
      	$bitch = array_count_values($select) !=
      		array_count_values(array_intersect($select, $array));
      } else {
        // Use array_keys because the key in a map might be NULL!
      	$bitch = (! array_count_values(array_keys($array, $select)));
      }
     }
    foreach ($array as $this_key => $this_value) {
        if (is_array($select)) {
            $s = in_array($this_value, $select) ? "selected" : "";
        } else {
            $s = $select == $this_value ? "selected" : "" ;
        }
        $v = s2h($this_value);
        $k = s2h($this_key);
        $html .= "\n  <option value=\"$v\" $s>$k</option>";
    }
    if ($bitch) {
    	$html .= "\n  <option value=\"$select\" selected>***INVALID***</option>";
    }
    $html .= "\n</select>\n";

    return $html;
}

///
// Create a menu from a table
//
// Same as menu_from_array, but from an SQL table using the columns
// named $key and $value as the keys and values of the menu (by using
// lookup_from_table to create an array from the table).
function menu_from_table($db, $table, $name, $key, $value, $select = "", $properties = "", $sort = "", $where = "") {
    $array = lookup_from_table($db, $table, $key, $value, $sort, $where);

    return menu_from_array($name, $array, $select, $properties);
}

///
// Create an array whose keys are the columns of a table
//
// @param $db: the database
// @param $table: the table
//
// The values of the array are the column descriptors as returned by
// mysql.  The descriptor is an array with the following keys:
// Field, Type, Null, Key, Default, Extra
function columns_of_table($db, $table) {
    $selector = "SHOW COLUMNS FROM $table";
    $query = mysql_query($selector, $db) or ode();
    $columns = array();
    while ($object = mysql_fetch_object($query)) {
        $columns[$row->field] = $row;
    }
    return $columns;
}

///
// Pad a number with 0's on the left
function number_pad($number,$n) {
  return str_pad((int) $number, $n, "0", STR_PAD_LEFT);
}

///
// Converts a time with AM/PM to 24 hour time
function normalize_time($time) {
    if (empty($time)) { return $time; }
    if (preg_match("/(\d*)\:?(\d*)\s*([ap]?).*/i", $time, $regs)) {
        // $regs[0] is the full match
        if (! $regs[2]) {
            $regs[2] = 0;
        }
        // 12am == 00:00, 12pm == 12:00
        $regs[1] = $regs[1] % 12;
        if ($regs[3] == 'p' || $regs[3] == 'P') {
            $regs[1] = $regs[1] + 12;
        }
        return "{$regs[1]}:{$regs[2]}";
    }
    return $time;
}

///
// Converts a time interval to hours:minutes
function normalize_interval($interval) {
    if (empty($interval)) { return $interval; }
    if (preg_match("/(\d*)\:(\d*)/i", $interval, $regs)) {
        // $regs[0] is the full match
        $interval = ($regs[1] * 60) + $regs[2];
    }

    return floor($interval / 60) . ':' . $interval % 60;
}

///
// Converts a date to an SQL date
function normalize_date($date) {
    if (empty($date)) { return $date; }
    // Note that this will fail for dates in the past, since it is
    // encoding the date as a UNIX timestamp.
    // --- there must be a better way
    $datetime = strtotime($date);
    if ($datetime == -1) return 0;
    return strftime("%Y-%m-%d", $datetime);
}

///
// Strip $ and ,'s
function normalize_dollars($dollars) {
    return preg_replace('/[$,]/', '', $dollars);
}

///
// Converts a phone number to the 'standard' pattern
function normalize_phone($phone) {
    $matches = array();
    preg_match("/^\(?([0-9]{3,3})\)?[-. ]?([0-9]{3,3})[-. ]?([0-9]{4,4})$/", $phone, $matches);
    return $matches[1] . "-" . $matches[2] . "-" . $matches[3];
}

///
// Converts newlines to paragraphs trying not to interleve <p></p>
// with any other html blocks
// [adapted from http://photomatt.net/scripts/autop]
function autop($pee, $br = 1) {
    if (empty($pee)) return $pee;
    $pee = $pee . "\n"; // just to make things a little easier, pad the end
    $pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
    $pee = preg_replace('!(<(?:table|ul|ol|li|pre|form|blockquote|h[1-6])[^>]*>)!', "\n$1", $pee); // Space things out a little
    $pee = preg_replace('!(</(?:table|ul|ol|li|pre|form|blockquote|h[1-6])>)!', "$1\n", $pee); // Space things out a little
    $pee = preg_replace("/(\r\n|\r)/", "\n", $pee); // cross-platform newlines
    $pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
    $pee = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "\t<p>$1</p>\n", $pee); // make paragraphs, including one at the end
    $pee = preg_replace('|<p>\s*?</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
    $pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
    $pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
    $pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
    $pee = preg_replace('!<p>\s*(</?(?:table|tr|td|th|div|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)!', "$1", $pee);
    $pee = preg_replace('!(</?(?:table|tr|td|th|div|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)\s*</p>!', "$1", $pee);
    if ($br) $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
    $pee = preg_replace('!(</?(?:table|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)\s*<br />!', "$1", $pee);
    $pee = preg_replace('!<br />(\s*</?(?:p|li|div|th|pre|td|ul|ol)>)!', '$1', $pee);
    $pee = preg_replace('/&([^#])(?![a-z]{1,8};)/', '&#038;$1', $pee);
    // Fix up Windoze lossage
    // This is really a heuristic
    return  cp1252_to_unicode($pee);
}

///
// Extended autop: tries not to interleve <p></p> with any other html blocks
// [from http://photomatt.net/scripts/autop]
function extended_autop($pee, $br=1) {
    $pee = $pee . "\n"; // just to make things a little easier, pad the end
    $pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
    $pee = preg_replace('!(<(?:table|ul|ol|li|pre|form|blockquote|h[1-6])[^>]*>)!', "\n$1", $pee); // Space things out a little
    $pee = preg_replace('!(</(?:table|ul|ol|li|pre|form|blockquote|h[1-6])>)!', "$1\n", $pee); // Space things out a little
    $pee = preg_replace("/(\r\n|\r)/", "\n", $pee); // cross-platform newlines 
    $pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
    $pee = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "\t<p>$1</p>\n", $pee); // make paragraphs, including one at the end 
    $pee = preg_replace('|<p>\s*?</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace 
    $pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
    $pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
    $pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
    $pee = preg_replace('!<p>\s*(</?(?:table|tr|td|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)!', "$1", $pee);
    $pee = preg_replace('!(</?(?:table|tr|td|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)\s*</p>!', "$1", $pee); 
    if ($br) $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
    $pee = preg_replace('!(</?(?:table|tr|td|dl|dd|dt|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)\s*<br />!', "$1", $pee);
    $pee = preg_replace('!<br />(\s*</?(?:p|li|pre|td|ul|ol)>)!', '$1', $pee);
    $pee = preg_replace('/&([^#])(?![a-z]{1,8};)/', '&#038;$1', $pee);
 
    return $pee; 
}

///
// Heuristicate common Windoze characters to correct unicode
function cp1252_to_unicode($str) {
    /*
     * From the cp1252 table at:
     * ftp://ftp.unicode.org/Public/MAPPINGS/VENDORS/MICSFT/WINDOWS/CP1252.TXT
     *
     * 0x80 0x20AC  #EURO SIGN
     * 0x81         #UNDEFINED
     * 0x82 0x201A  #SINGLE LOW-9 QUOTATION MARK
     * 0x83 0x0192  #LATIN SMALL LETTER F WITH HOOK
     * 0x84 0x201E  #DOUBLE LOW-9 QUOTATION MARK
     * 0x85 0x2026  #HORIZONTAL ELLIPSIS
     * 0x86 0x2020  #DAGGER
     * 0x87 0x2021  #DOUBLE DAGGER
     * 0x88 0x02C6  #MODIFIER LETTER CIRCUMFLEX ACCENT
     * 0x89 0x2030  #PER MILLE SIGN
     * 0x8A 0x0160  #LATIN CAPITAL LETTER S WITH CARON
     * 0x8B 0x2039  #SINGLE LEFT-POINTING ANGLE QUOTATION MARK
     * 0x8C 0x0152  #LATIN CAPITAL LIGATURE OE
     * 0x8D         #UNDEFINED
     * 0x8E 0x017D  #LATIN CAPITAL LETTER Z WITH CARON
     * 0x8F         #UNDEFINED
     * 0x90         #UNDEFINED
     * 0x91 0x2018  #LEFT SINGLE QUOTATION MARK
     * 0x92 0x2019  #RIGHT SINGLE QUOTATION MARK
     * 0x93 0x201C  #LEFT DOUBLE QUOTATION MARK
     * 0x94 0x201D  #RIGHT DOUBLE QUOTATION MARK
     * 0x95 0x2022  #BULLET
     * 0x96 0x2013  #EN DASH
     * 0x97 0x2014  #EM DASH
     * 0x98 0x02DC  #SMALL TILDE
     * 0x99 0x2122  #TRADE MARK SIGN
     * 0x9A 0x0161  #LATIN SMALL LETTER S WITH CARON
     * 0x9B 0x203A  #SINGLE RIGHT-POINTING ANGLE QUOTATION MARK
     * 0x9C 0x0153  #LATIN SMALL LIGATURE OE
     * 0x9D         #UNDEFINED
     * 0x9E 0x017E  #LATIN SMALL LETTER Z WITH CARON
     * 0x9F 0x0178  #LATIN CAPITAL LETTER Y WITH DIAERESIS
     */

    $patterns = array("\x80", "\x82", "\x83", "\x84", "\x85", "\x86", "\x87", "\x88", "\x89", "\x8A", "\x8B", "\x8C", "\x8E", "\x91", "\x92", "\x93", "\x94", "\x95", "\x96", "\x97", "\x98", "\x99", "\x9A", "\x9B", "\x9C", "\x9E", "\x9F");
    $substitutions = array("&#20AC;", "&#201A;", "&#0192;", "&#201E;", "&hellip;", "&#2020;", "&#2021;", "&#02C6;", "&#2030;", "&#0160;", "&#2039;", "&#0152;", "&#017D;", "&lsquo;", "&rsquo;", "&ldquo;", "&rdquo;", "&bull;", "&ndash;", "&mdash;", "&#02DC;", "&trade;", "&#0161;", "&#203A;", "&#0153;", "&#017E;", "&#0178;");
    return str_replace($patterns, $substitutions, $str);
}

// [2005-01-20 mch]  Added for Geppy's calendar

//  ##################################################
//  ##                RockSHOX - PHIL.              ##
//  ##                allan albacete                ##
//  ## Date validation in format of (YYYY-MM-DD) or ##
//  ##                  (YYYY/MM/DD) or             ##
//  ##           (MM-DD-YYYY) or (MM/DD/YYYY)       ##
//  ##              hope it helps  may 5, 2004      ##
//  ##################################################
function validate_date($date, $minyear = 0, $maxyear = 9999) {
    // echo "validate_date passed date= $date";

    if ((ereg ("([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})", $date, $regs)) or
        (ereg ("([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})", $date, $regs))){

        list($wholeyear, $year,$month,$day) = $regs;

    }
    elseif((ereg ("([0-9]{1,2})-([0-9]{1,2})-([0-9]{4})", $date, $regs)) or
           (ereg ("([0-9]{1,2})/([0-9]{1,2})/([0-9]{4})", $date, $regs))){

        list($wholeyear,$month,$day,$year) = $regs;
    }
    else {
        // print 'validate_date: Invalid date';
        return false;
    }

    if ($day > 31){
        // print 'validate_date: Invalid Day';
        return false;
    }
    elseif ($month > 12){
        // print 'validate_date: Invalid Month';
        return false;
    }
    elseif ($year < $minyear || $year > $maxyear) {
    	return false;
    }
    //      print $regs[0];
    return true;
}

///
// Calculate HMAC according to RFC2104
// http://www.ietf.org/rfc/rfc2104.txt
//
// Used internally by pw_encode and pw_check
function hmac($key, $data, $hash = 'md5', $blocksize = 64) {
    if (strlen($key)>$blocksize) {
        $key = pack('H*', $hash($key));
    }
    $key  = str_pad($key, $blocksize, chr(0));
    $ipad = str_repeat(chr(0x36), $blocksize);
    $opad = str_repeat(chr(0x5c), $blocksize);
    return $hash(($key^$opad) . pack('H*', $hash(($key^$ipad) . $data)));
}

///
// Encode a password for storing in database
//
// @param string $password: the password to encode
//
// @return string: the encoded value
function pw_encode($password) {
    $seed = substr('00' . dechex(mt_rand()), -3) .
        substr('00' . dechex(mt_rand()), -3) .
        substr('0' . dechex(mt_rand()), -2);
    return hmac($seed, $password, 'md5', 64) . $seed;
}

///
// Check a password against the stored value
//
// @param string $password: the password to check
// @param string $stored_value: the previously computed encoding (see
// pw_encode)
//
// @return boolean: true if the password matches, otherwise false
function pw_check($password, $stored_value) {
    $seed = substr($stored_value, 32, 8);
    return hmac($seed, $password, 'md5', 64) . $seed==$stored_value;
}

///
// Useful session starter
// @param int lifetime: number of seconds to live, defaults to forever
// @param string path: defaults to /
// @param string domain: defaults to whole domain
// @param bool secure: defaults to false
// @param bool httponly: defaults to true
function ptw_session_start($expires=null, $path=null, $domain=null, $secure=null, $httponly=null) {
    $name = "PHPSESSID";

    if (is_null($expires)) {
        $expires = 0;
    }
    if (is_null($path)) {
        $path = '/';
    }
    if (is_null($domain)) {
        $domain = 
            strtolower(
                preg_replace('/^[Ww][Ww][Ww]\./', '.',
                             preg_replace('/:[0-9]*$/', '', $_SERVER['HTTP_HOST'])));
    }
    if (is_null($secure)) {
        $secure = false;
    }
    if (is_null($httponly)) {
        $httponly = true;
    }
   
    // Our php does not support httponly?
    session_set_cookie_params($expires, $path, $domain, $secure);
    // Force a cookie to be sent
    if (array_key_exists($name, $_COOKIE)) {
        session_id($_COOKIE[$name]);
    }
    session_start();
}

///
// Get the directory of the current script
function current_directory() {
    $url = parse_url($_SERVER['REQUEST_URI']);
    $parts = explode('/', $url['path']);
    $parts[count($parts)-1] = '';
    return implode('/', $parts);
}

///
// Random password generator
function generatePassword($length = 24) {
    if(function_exists('openssl_random_pseudo_bytes')) {
        $password = base64_encode(openssl_random_pseudo_bytes($length, $strong));
        if($strong == TRUE)
            return substr($password, 0, $length); //base64 is about 33% longer, so we need to truncate the result
    }
    
    //fallback to mt_rand if php < 5.3 or no openssl available
    $characters = '0123456789';
    $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz/+'; 
    $charactersLength = strlen($characters)-1;
    $password = '';
    
    //select some random characters
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[mt_rand(0, $charactersLength)];
    }        
    
    return $password;
} 

?>
