<?php

/**
 * Import Utils
 *
 * @license     GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author      Thibault Dory (thibault.dory@gmail.com)
 * @version     1.0
 */


function convert_footnote($matches) {

   $content = $matches[1];
   #Remove new lines
   $content = preg_replace("/\n/U", " ",$content);
   #Remove subscripts
   $content = preg_replace("/<sub>(.*)<\/sub>/U","$1",$content);
   #Remove superscript
   $content = preg_replace("/<sup>(.*)<\/sup>/U","$1",$content);

   return "((".$content."))";
}

function convert_table($table) {
   $new_table = "";
   $lines = explode("|-", $table);
   $count = 1;
   foreach ($lines as $line) {

           #Remove style inside columns
           $line = preg_replace('/\\|.*style=\\".*\\"/U', "",$line);
           #Remove style outside table
           $line = preg_replace("(style=\".*\")","",$line);
           #Remove div tags
           $line = preg_replace("/<div.*>(.*)<\/div>/U","$1",$line);
           #Remove double new lines into table line 
           $line = preg_replace("/\|(.*)\n\n(.*)\n/","|$1 $2\n",$line);
           #Remove lists inside table lines  
           $line = preg_replace("/\n  \* /U"," ",$line);
           $line = preg_replace("/ \* /U"," ",$line);
           #Check if there is a header, if not use the first line as header
           if (! strpos($line,'!') && $count == 1) {
               $line = preg_replace("/\|/","!",$line)."\n\n";
           }
            
           #Remove buggy div tags
           #$line = preg_replace("/\|*\n>(.*)<\/div>*\n*\|/U","|$1|",$line);

           #Convert ! to ^ in headings
           $line = trim(preg_replace('/\s+!/', ' ^', $line));
           #Add a ^ to end the heading line
           $line = strrev(preg_replace("/(.*\^)/U", '^ $1', strrev($line), 1));
           #Remove buggy new lines inside table lines
           $line = preg_replace("/\|(.*)\n\n/","|$1\n",$line);
           #Add NEWLINE separator between table lines
           $line = preg_replace("/\n\n/","NEWLINE",$line);
           ##Remove new lines into table lines 
           $line = trim(preg_replace('/\|(.*)\s+/', '|$1', $line));
           #Remove double NEWLINE (just below the table header) 
           $line = preg_replace("/NEWLINENEWLINE/","\n",$line);
           #Remove the other NEWLINE separator and replace it by | followed by a new line
           $line = preg_replace("/NEWLINE/","|\n",$line);
           if ($count != 1) {
              $line = $line." |\n";
           } else {
              $line = $line."\n";
           }

           #Replace == Headings == by ** Headings **
           $line = preg_replace("/=====(.*)=====/U","**$1**",$line);
           $line = preg_replace("/====(.*)====/U","**$1**",$line);
           $line = preg_replace("/===(.*)===/U","**$1**",$line);
           $line = preg_replace("/==(.*)==/U","**$1**",$line);
           $new_table = $new_table.$line;
           $count += 1;
   }

   return $new_table;
}


function get_images_from_html($myHTMLContent, $myWikiContent){

      #Detect if an image is present in the header, if it is the case, start taking picture into account one picture farther
      $imageInHeader = preg_match("/<DIV TYPE=HEADER>.*<IMG(.*)>.*<\/DIV>/s",  $myHTMLContent);
      if ($imageInHeader) {
        $imageOffset = 1;
      } else {
        $imageOffset = 0;
      }


      #Get all the images names, width and height
      preg_match_all("/<IMG SRC=\"(.*?)\".*ALIGN=(\S{1,}).*WIDTH=(\S{1,}).*HEIGHT=(\S{1,}).*?>/", $myHTMLContent, $image_tags);

      $image_patterns = array();
      $image_names = array();
      for($i=$imageOffset; $i<count($image_tags[0]); $i++) {
        if ($image_tags[2][$i] == "LEFT") {
          $left_align = " ";
          $righ_align = "";
        } elseif ($image_tags[2][$i] == "RIGHT") {
          $left_align = "";
          $righ_align = " ";
        } elseif ($image_tags[2][$i] == "CENTER") {
          $left_align = " ";
          $righ_align = " ";
        } else {
          $left_align = "";
          $righ_align = "";
        }
        array_push($image_names, "{{".$left_align."image:".$image_tags[1][$i]."?".$image_tags[3][$i]."x".$image_tags[4][$i].$righ_align."}}");
        array_push($image_patterns, "/\{\{wiki:\}\}/");
      }

      #Replace image tags with correct images
      $myWikiContent = preg_replace($image_patterns, $image_names, $myWikiContent, 1);

      #Some image align produce html align divs in the wiki text, replace them by hand
      $myWikiContent = preg_replace("/<div align=\"right\">{{image:(.*)}}<\/div>/U", "{{ image:$1}}", $myWikiContent);
      $myWikiContent = preg_replace("/<div align=\"left\">{{image:(.*)}}<\/div>/U", "{{image:$1 }}", $myWikiContent);
      $myWikiContent = preg_replace("/<div align=\"center\">{{image:(.*)}}<\/div>/U", "{{ image:$1 }}", $myWikiContent);

      return $myWikiContent;
}


function get_tagged_from_html($html, $type) {
  $results = array();
  $html = str_replace("\n"," ",$html);
  if ($type == "underlined") {
    preg_match_all("/<U>(.*)<\/U>/sU", $html, $tagged_sentences, PREG_OFFSET_CAPTURE);
  } elseif ($type == "italic") {
    preg_match_all("/<I>(.*)<\/I>/sU", $html, $tagged_sentences, PREG_OFFSET_CAPTURE);
  }


  foreach ($tagged_sentences[1] as $sentence_info) {

     #Get the offset at which begins the sentence
     $start = $sentence_info[1];
     #remove new lines from sentence
     $sentence = str_replace("\n"," ",$sentence_info[0]);
     #Find how many times this same sentence appears before the right one
     $pattern = preg_replace('/\\//', '\\/', preg_quote($sentence));
     preg_match_all("/".$pattern."/", $html, $matches, PREG_OFFSET_CAPTURE);

     #Remove html tags from sentence
     $clean_sentence = preg_replace("/<.*>/sU","",$sentence);
     $clean_pattern = preg_replace('/\\//', '\\/', preg_quote($clean_sentence));
     preg_match_all("/".$clean_pattern."/", $html, $clean_matches, PREG_OFFSET_CAPTURE);
     $count = count_from_pos($matches, $clean_matches, $start);
     $clean_start = $clean_matches[0][$count][1];
     if ($type == "italic") {

     #echo "sentence : ".$sentence."\n";
     #echo "sentence info : ".print_r($sentence_info, true)."\n";
     #echo "matches : ".print_r($matches, true)."\n";
     #echo "clean_matches : ".print_r($clean_matches, true)."\n";
     #echo "start : ".$start."\n";
     #echo "clean_start : ".$clean_start."\n";
     #echo "count : ".$count."\n"; 
     #echo "pattern : /".$pattern."/\n";
     #echo "###################################################\n";
     }

     $results[$clean_start] = array("sentence" => $clean_sentence, "count" => $count);
  }


  #echo "results: ".print_r($results, true)."\n";
  #echo "==========================================================================\n";

  return $results;
}


function count_from_pos($matches, $clean_matches, $pos){
  $count = 0;
  for($count=0; $count < count($clean_matches[0]); $count++) {
    #echo "clean match : ".print_r($clean_matches[0][$count], true)."\n";
    #echo "is equal : ".($match[1] == $pos)." \n";
    #echo "++++++++++++++\n";
    $clean_match = $clean_matches[0][$count];
    foreach ($matches[0] as $match) {
      #echo "match : ".print_r($match, true)."\n";
      #echo "relative pos : ".($pos+strpos($match[0], $clean_match[0]))."\n";
      if( ($pos + strpos($match[0], $clean_match[0])) == $clean_match[1]){
        return $count;
      }
    }
  }
  return $count;
}


function replace_from_list($myWikiContent, $list, $type) {
  if ($type == "underlined") {
    $tag = "__";
  } elseif ($type == "italic") {
    $tag = "//";
  }

  $occurences = array();

  foreach ($list as $pos => $sentence_info) {

     $sentence = $sentence_info["sentence"];
     $count = $sentence_info["count"];

     if (array_key_exists($sentence, $occurences)) {
       $occurences[$sentence] = 0;
     } else {
       $occurences[$sentence] = 0;
     }

     preg_match_all("/".preg_quote($sentence)."/", $myWikiContent, $matches, PREG_OFFSET_CAPTURE);
     #Find position in wiki content
     #echo "sentence : ".$sentence."\n";
     #echo "type : ".$type."\n";
     #echo "matches : ".print_r($matches, true)."\n";
     $start = $matches[0][$count + $occurences[$sentence]][1];
     #echo "start : ".$start."\n";
     #echo "##################################################################\n";
     $myWikiContent = substr($myWikiContent, 0, $start).str_replace($sentence, $tag.$sentence.$tag, substr($myWikiContent, $start, strlen($sentence))).substr($myWikiContent, $start+strlen($sentence));
  }
  return $myWikiContent;
}


#Compute if there is already a string in list that contains the range $start => $end
function is_in_list_range($list, $start, $end){
  foreach($list as $pos => $sentence){
    $current_end = $pos + strlen($sentence);
    if ($start >= $pos && $start<= $current_end){
      return true;
    }
  }
  return false;
}


?>
