<?php
require_once "System.php";
require_once('SyntaxConverter.php');
require_once('ImportUtils.php');

/**
 * Action Plugin
 *
 * @license     GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author      Thibault Dory (thibault.dory@gmail.com)
 * @version     1.0
 */

if(!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

class action_plugin_docimporter extends DokuWiki_Action_Plugin {
  function register($controller) {
    $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_importer', array());
  }

  function _importer(&$event, $param) {
    if ( auth_quickaclcheck( $ID ) < AUTH_EDIT ) return;
    if ( $event->data != 'doc2dw' ) return;
    if ( $_FILES['doc'] ) {

      $pageName = $_POST["title"];
      $nameSpace = $_POST["ns"];
      if (!$nameSpace) {
	$nameSpace = "wiki";
      }
      #Create temporary directory
      $tempDir = System::mktemp("-d word_upload");

      $fileName = basename($_FILES['doc']['name']);
      $fileName = preg_replace("/ /", "_", $fileName);
      $agnosticFileName = preg_split("/\./", $fileName);
      $agnosticFileName = $agnosticFileName[0];
      if(move_uploaded_file($_FILES['doc']['tmp_name'], $tempDir."/". $fileName)){

        #Convert the doc to HTML and MediaWiki
        $result = exec("export HOME=/tmp && cd ".$tempDir." && convert_to_mediawiki ".$fileName);

        #Load html and MediaWiki
        $myWikiContent = file_get_contents($tempDir."/".$agnosticFileName.".txt");
        $myHTMLContent = file_get_contents($tempDir."/".$agnosticFileName.".html");

        #Replace nbsp by space
        $myWikiContent = preg_replace("/&nbsp;/", " ",$myWikiContent);
        #Remove center tags
        $myWikiContent = preg_replace("/<center>/", " ",$myWikiContent);
        $myWikiContent = preg_replace("/<\/center>/", " \n",$myWikiContent);

        #Remove buggy Toc tags
        $myWikiContent = preg_replace("/\\[#_Toc.*\\]/", " \n",$myWikiContent);

        #Convert from MediaWiki to dokuwiki
        $converter = new MediaWiki2DokuWiki_MediaWiki_SyntaxConverter($myWikiContent);
        $myWikiContent = $converter->convert();

        #Remove buggy //
        $myWikiContent = preg_replace("/\/\//", "",$myWikiContent);

        #Get undernlined sentences from html and replace them in the wiki text 
        $underlined_sentences = get_tagged_from_html($myHTMLContent, "underlined");
        $myWikiContent = replace_from_list($myWikiContent, $underlined_sentences, "underlined");

        #Get italic sentences from html and replace them in the wiki text
        $italic_sentences = get_tagged_from_html($myHTMLContent, "italic");
        $myWikiContent = replace_from_list($myWikiContent, $italic_sentences, "italic");

        #Get all the images from the html
        $myWikiContent = get_images_from_html($myHTMLContent, $myWikiContent);

        #Convert tables      
        $raw_tables = array();
        $good_tables = array();
        preg_match_all("/\{\|(.*)\|\}/sU", $myWikiContent, $tables);
        foreach ($tables[1] as $match) {
            array_push($raw_tables, '{|'.$match.'|}');
            array_push($good_tables, convert_table($match));
        }
        $myWikiContent = str_replace($raw_tables, $good_tables, $myWikiContent);

        #Transform references into footnotes
        $myWikiContent = preg_replace_callback("/<ref.*>(.*)<\/ref>/sU", "convert_footnote",$myWikiContent);

        #Remove junk <br/> tags
        $myWikiContent = preg_replace("/<br\/>/", "\n",$myWikiContent);

        #Remove junk <references/>
        $myWikiContent = preg_replace("/----\n<references\/>/", "",$myWikiContent);

        #Remove ** that does not have the corresponding closing **
        $content_lines = explode(PHP_EOL, $myWikiContent);
        $myWikiContent = "";
        foreach ($content_lines as $line) {
            $first = strpos($line, "**");
            if ($first) {
                $second = strpos($line, "**", $first+2);
                if (!$second) {
                    $line = preg_replace("/\*\*/U","",$line);
                }
            }
            $myWikiContent = $myWikiContent.$line."\n";
        }

        #Send the wiki page
        $username = $this->getConf('api_username');
        $password = $this->getConf('api_password');
        $client = new IXR_Client('http://localhost/dokuwiki/lib/exe/xmlrpc.php');
        $ok = $client->query('dokuwiki.login', $username, $password);
        if ($ok) {
          $attrs = array('sum' => 'First try', 'minor' => false);
          $result = $client->query('wiki.putPage', $nameSpace.':'.$pageName, $myWikiContent, $attrs);

          #Send all the images as attachements 
          if ($handle = opendir($tempDir)) {

            while (false !== ($entry = readdir($handle))) {
                if (strpos($entry,'.png') !== false || strpos($entry,'.jpg') !== false || strpos($entry,'.gif') !== false) {
                     $image_data = file_get_contents($tempDir."/".$entry, true);
                     //$image_data = base64_encode($image_data);
                     $image_data = new IXR_Base64($image_data);
                     $attrs = array('ow' => true);
                     $client->query('wiki.putAttachment', $entry, $image_data, $attrs);
                }
            }

            closedir($handle);

          }
        }
      }

      $event->data = $this->getConf('parserPostDisplay');
      send_redirect(wl($nameSpace.':'.$pageName, '', true, '&'));
    }
  }
}

?>
