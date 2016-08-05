<?php

if (defined('KRB_ROOT')) {
  echo '<hr><b>KRB error :</b> can not query the minified functions again.<hr>';
  exit();
}

/* Force execution - development only > true | false */

global $krb_forced;
$krb_forced = false;

/*
  --------------------------------
  ##  ANATOMY OF THIS PAGE
  --------------------------------
  |
  |__ A#C - General functions
  |
  |__ B#C - Parse assets
  |  |
  |  |__ 1#3 - Don't minify assets
  |  |__ 2#3 - Do minify assets
  |  |__ 3#3 - Output the assets
  |
  |__ C#C - Parse (minify) html

*/

/* ----------------------------------------------------------- */
/* A#C - General functions */
/* ----------------------------------------------------------- */

DEFINE('KRB_ROOT', kirby()->site()->url() . '/');

/* Ref 1. http://www.minifier.org/ */
/* Ref 2. https://github.com/matthiasmullie/minify/issues/83 */
/* Ref 3. https://github.com/matthiasmullie/minify */

use MatthiasMullie\Minify;

function krb_msg($msg, $em) {
  $em_prefix = $em != 0?'%c':'';
  $em_postfix = $em == 1?', "color: #666;"':'';

    if ($em == 2) {
      $em_postfix = ', "background: #ddd;"';
    }

  echo chr(10) . '<script>if(window.console){console.log("' . $em_prefix . '[krb] ' . $msg .'"' . $em_postfix . ');}</script>' . chr(10);
}

/* Ref. http://stackoverflow.com/a/5501447 */

function krb_filesize($bytes) {

  if ($bytes >= 1073741824) {
    $bytes = number_format($bytes / 1073741824, 2) . ' GB';
  }

  elseif ($bytes >= 1048576) {
    $bytes = number_format($bytes / 1048576, 2) . ' MB';
  }

  elseif ($bytes >= 1024) {
    $bytes = number_format($bytes / 1024, 2) . ' KB';
  }

  elseif ($bytes > 1) {
    $bytes = $bytes . ' bytes';
  }

  elseif ($bytes == 1) {
    $bytes = $bytes . ' byte';
  }

  else {
    $bytes = '0 bytes';
  }

  return $bytes;
}

/* ----------------------------------------------------------- */
/* B#C - Minify all assets */
/* ----------------------------------------------------------- */

function krb($assets, $type, $version, $minify, $cache, $debug) {

/* Set | get some defaults */

  if (!isset($assets)) {
    echo '<hr><b>KRB error :</b> no assets are set.<hr>';
    exit();
  }

  if (!isset($type)) {
    echo '<hr><b>KRB error :</b> type can not be empty.<hr>';
    exit();
  }

  $types = array('css', 'js');

  if (!in_array(strtolower($type), $types)) {
    echo '<hr><b>KRB error :</b> no valid type set.<hr>';
    exit();
  }

  if (!isset($version)) {
    $version = -1;
  }

  if (!isset($minify)) {
    $minify = true;
  }

  if (!isset($cache)) {
    $cache = true;
  }

  if (!isset($debug)) {
    $debug = false;
  }

/* Get some vars */

  $cache = $cache == true? '?v=' . $version : '';
  $type = strtolower($type);
  $output = '';

/* --------------------------------- */
/* 1#3 - Assets must NOT be minified */
/* --------------------------------- */

  if ($minify != true) {

      if ($debug == true) {
        krb_msg('----------------------------------------------', 0);
        krb_msg('optimising > disabled [' . $type .'] : current version ' . $version, 2);
      }

/* Not-minified assets are more than one */

    if (is_array($assets)) {

      foreach ($assets as &$uri) {

        switch ($type) {
          case 'css':
            $output .= '<link rel="stylesheet" href="' . KRB_ROOT . $uri . $cache . '">' . chr(10);
            break;
          case 'js':
            $output .= '<script src="' . KRB_ROOT . $uri . $cache . '"></script>' . chr(10);
            break;
        }

        if ($debug == true) {
          krb_msg(' reading   > ' . $uri . ' [' . krb_filesize(filesize($uri)) . ']', 1);
        }
      }

/* Not-minified asset is single */

    } else {

      $uri = $assets;

      switch ($type) {
        case 'css':
          $output .= '<link rel="stylesheet" href="' . KRB_ROOT . $uri . $cache . '">' . chr(10);
          break;
        case 'js':
          $output .= '<script src="' . KRB_ROOT . $uri . $cache . '"></script>' . chr(10);
          break;
      }

        if ($debug == true) {
          krb_msg(' reading   > ' . $uri . ' [' . krb_filesize(filesize($uri)) . ']', 1);
        }

    }

/* --------------------------------- */
/* 2#3 - Assets MUST be minified     */
/* --------------------------------- */

  } else {

      switch ($type) {
        case 'css':
          $krb = c::get('krb_css_path', 'assets/css/style.min.css');
          break;
        case 'js':
          $krb = c::get('krb_js_path', 'assets/js/script.min.js');
          break;
      }

/* Check for previous version, before minifying all assets */

  $krb_needed = false;

  $version_file = $krb . '.version';

/* Version-file doesn't exists */

  if (file_exists($version_file)) {
    $version_prev = file_get_contents($version_file);
  } else {
    fopen($version_file, "w");
    $version_prev = null;
  }

/* When a new version is available - or the minified file does not exist - minify the assets */

  global $krb_forced;

  if ($version_prev != $version || !file_exists($krb) || $krb_forced) {

  $krb_needed = true;

/* Update the version-file with the newest number */

    $fp = fopen($version_file, "r+");

      while (!flock($fp, LOCK_EX)) {
      }

    fwrite($fp, $version);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

/* Load the required scripts */

    require_once 'src/Minify.php';
    require_once 'src/Exception.php';
    require_once 'src/Converter.php';

      switch ($type) {
        case 'css':
          require_once 'src/CSS.php';
          $minifier = new Minify\CSS();
          break;
        case 'js':
          require_once 'src/JS.php';
          $minifier = new Minify\JS();
          break;
      }

/* Minified assets are more than one */

      if ($debug == true) {
        krb_msg('----------------------------------------------', 0);
        krb_msg('optimising > started [' . $type . ']', 2);

        $processed = 0;
        $filesizes = 0;
      }

      if (is_array($assets)) {

        foreach ($assets as &$uri) {

            if ($debug == true) {
              $processed++;
              $filesizes += filesize($uri);
              krb_msg(' minifying > ' . $uri . ' [' . krb_filesize(filesize($uri)) . ']', 1);
            }

          $minifier->add($uri);
        }

/* Minified asset is single */

      } else {

        $uri = $assets;

          if ($debug == true) {
            $processed++;
            $filesizes += filesize($uri);
            krb_msg(' minifying > ' . $uri . ' [' . krb_filesize(filesize($uri)) . ']', 1);
          }

        $minifier->add($uri);

      }

      if ($debug == true) {
        $plural = $processed > 1?'s':'';
        krb_msg('original   > ' . $processed . ' ' . $type . ' file' . $plural . ' [' . krb_filesize($filesizes) . ']', 0);
      }

/* Minify the asset(s) */

      switch ($type) {
        case 'css':
          $minifier->minify($krb);
          break;
        case 'js':
          $minifier->minify($krb);
          break;
      }

    }

/* --------------------------------- */
/* 3#3 - Output the script-tag(s)    */
/* --------------------------------- */

/* Build the output tags */

/* Ref. http://stackoverflow.com/a/10731231 */

    switch ($type) {
      case 'css':
        $output .= '<link rel="stylesheet" href="' . KRB_ROOT . $krb . $cache . '" id="krb_css">' . chr(10);
        break;
      case 'js':
        $krb_defer = c::get('krb_js_defer', false) == true?' defer':'';
        $krb_async = c::get('krb_js_async', false) == true?' async':'';
        $output .= '<script'. $krb_async . $krb_defer . ' src="' . KRB_ROOT . $krb . $cache . '" id="krb_js"></script>' . chr(10);
        break;
    }

    if ($debug == true && $krb_needed == true) {

      $plural = $processed > 1?' ':'';

      krb_msg('minified   > 1 ' . $type . ' file ' . $plural . '[' . krb_filesize(filesize($krb)) . ']', 0);
      krb_msg('difference > ' . sprintf('%0.2f', (($filesizes - filesize($krb)) / $filesizes) * 100) . '%', 2);
      krb_msg('finalised  > ' . KRB_ROOT . $krb . $cache, 0);
      krb_msg('optimising > ended [' . $type .'] : old version ' . $version_prev . ' | new version ' . $version, 2);

    } else if ($debug == true && $krb_needed == false) {

      krb_msg('----------------------------------------------', 0);
      krb_msg('optimising > not needed [' . $type .'] : old version ' . $version_prev . ' | new version ' . $version, 2);
      krb_msg('finalised  > ' . KRB_ROOT . $krb . $cache, 0);

    }

  }

  echo $output;

}

/* ----------------------------------------------------------- */
/* C#C - Minify all html */
/* ----------------------------------------------------------- */

/* Only minify when explicity set - skip panel and downloads always */

if (c::get('krb_html_min') == true && !function_exists('panel') && strpos($_SERVER['REQUEST_URI'], 'download') != true):

/* Ref. http://stackoverflow.com/a/6225706 */

  function krb_html($buffer) {

    $search = array(
      '/\>[^\S ]+/s', /* strip whitespaces after tags, except space */
      '/[^\S ]+\</s', /* strip whitespaces before tags, except space */
      '/(\s)+/s' /* shorten multiple whitespace sequences */
    );

    $replace = array(
      '>',
      '<',
      '\\1'
    );

    $buffer = preg_replace($search, $replace, $buffer);

/* Ref. http://stackoverflow.com/a/3235781 */

    $buffer = preg_replace('/<!--(.*)-->/Uis', '', $buffer);
    $buffer = preg_replace('/> </Uis', '><', $buffer);
    $buffer = preg_replace('/>  </Uis', '><', $buffer);

    return $buffer;
  }

  ob_start('krb_html');

endif;

?>