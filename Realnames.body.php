<?php 

/**
 * @file
 * @ingroup Extensions
 * @authors Olivier Finlay Beaton (olivierbeaton.com) 
 * @copyright cc-by http://creativecommons.org/licenses/by/3.0/  
 * @since 2011-09-15, 0.1
 * @note requires MediaWiki 1.7.0
* @note coding convention followed: http://www.mediawiki.org/wiki/Manual:Coding_conventions 
 */

if ( !defined( 'MEDIAWIKI' ) ) {
        die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

/**
 * @ingroup Extensions
 * @since 2011-09-15, 0.1
 * @note requires MediaWiki 1.7.0
 */ 
class ExtRealnames {
  /**
   * A cache of user objects, 
   */     
  protected static $users = array();
  
  /**
   * checks a data set to see if we should proceed with the replacement.
   * @param[in] $m \array keyed with a string called <em>username</em>   
   * @return \string text to replace the match with     
   * @since 2011-09-16, 0.1  
   * @note public since it is called from an anonymous function      
   */     
  public static function checkBare($m) {
    global $wgRealnamesDebug; 
        
    if ($wgRealnamesDebug) {
      echo 'checkBare: ';
      var_dump($m);
      echo "<br/>\n";    
    }
    
    // we do not currently do any checks on Bare replacements, a User: find is 
    // always valid but we could add one in the future, and the debug 
    // information is still conveniant and keeps things consistent with checkLink
        
    return self::replace($m);
  }
  
  
  /**
   * checks a data set to see if we should proceed with the replacement.
   * @param[in] $m \array keyed with strings called 
   *    \li<em>linkstart</em>
   *    \li<em>username</em> 
   *    \li<em>realname</em>
   *    \li<em>linkend</em>
   * @return \string text to replace the match with                
   * @since 2011-09-16, 0.1  
   * @note public since it is called from an anonymous function   
   */
  public static function checkLink($m) {
    global $wgRealnamesDebug;
    
    if ($wgRealnamesDebug) {
      echo 'checkLink: ';
      var_dump($m);
      echo "<br/>\n";    
    }
   
    // some links point to user pages but do not display the username, we can safely ignore those
    // we need to urldecode the link for accents and special characters,
    // and ensure our username has underscores instead of spaces to match our link
    // before being able to do the comparison.
    if (urldecode($m['linkuser']) != str_replace(' ','_',$m['username'])) { 
      return $m['all'];
    }
         
    return self::replace($m);
  }
   
  /**
   * formats the final string in the configured style to display the real name.  
   * @param[in] $m \array keyed with strings called 
   *    \li<em>linkstart</em>
   *    \li<em>username</em> 
   *    \li<em>realname</em>
   *    \li<em>linkend</em>
   * @return \string formatted text to replace the match with                
   * @since 2011-09-16, 0.1
   * @see $wgRealnamesLinkStyle
   * @see $wgRealnamesBareStyle
   * @see $wgRealnamesStyles
   * @see $wgRealnamesBlank            
   */ 
  protected static function display($m) {
    global $wgRealnamesDebug, 
      $wgRealnamesLinkStyle, $wgRealnamesBareStyle, 
      $wgRealnamesStyles, $wgRealnamesBlank;
    
    // what kind of formatting will we do?
    $style = $wgRealnamesLinkStyle;
    if (empty($m['linkstart'])) {
      if ($wgRealnamesBareStyle !== false) {
        $style = $wgRealnamesBareStyle;
      }      
      $m['linkstart'] = '';
      $m['linkend'] = '';
    }
    
    if (empty($style)) {
      // error
      if ($wgRealnamesDebug) {
        echo 'display: error, blank style configuration<br>\n';          
      }
      return $m['all'];
    }
    
    // get the formatting code      
    $format = $wgRealnamesStyles[$style];
    
    if (empty($style)) {
      // error
      if ($wgRealnamesDebug) {
        echo 'display: error, blank format configuration<br>\n';          
      }
      return $m['all'];
    }
    
    // we have a blank username, and the admin doesn't want to see them, 
    // or his chosen format will not display a username at all
    if (empty($m['realname']) && (
      !$wgRealnamesBlank || strpos($format,'$2') === false
      )) {
      // swap in the username where they expected the realname
      $format = str_replace('$3','$2',$format);          
    }
    
    // plug in our values to the format desired
    $text = wfMsgReplaceArgs($format, array( // redo to ensure order
      $m['linkstart'],
      str_replace('_', ' ',$m['username']),
      str_replace('_', ' ',$m['realname']),
      $m['linkend']
      )); 
    
    if ($wgRealnamesDebug) {
        echo "display: replacing with \n";
        var_dump($text);  
        echo "<br/>\n";         
      }
    
    return $text;  
  } // function  
   
  /**
   * change all usernames to realnames   
   * @param[inout] &$out OutputPage The OutputPage object.
   * @param[inout] &$sk Skin object that will be used to generate the page, added in 1.13.
   * @return \bool true, continue hook processing
   * @since 2011-09-16, 0.1      
   * @see hook documentation http://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
   * @note requires MediaWiki 1.7.0      
   */     
  public static function hookBeforePageDisplay(&$out, &$sk = false) {
    global $wgRealnamesDebug;
    
    // special user page handling
    if ($out->getTitle()->getNamespace() == 2) { // User:             
      // swap out the specific username from title
      // this overcomes the problem lookForBare has with spaces and underscores in names
      $out->setPagetitle(self::lookForBare($out->getPageTitle(),'/User:('.$out->getTitle()->getText().')/'));
    }
       
    // article title
    if ($wgRealnamesDebug) {
      echo "hookBeforePageDisplay: searching article title...<br>\n";          
    }
    // this should also affect the html head title
    $out->setPageTitle(self::lookForBare($out->getPageTitle()));

    // subtitle (say, on revision pages)
    if ($wgRealnamesDebug) {
      echo "hookBeforePageDisplay: searching article subtitle...<br>\n";          
    }
    $out->setSubtitle(self::lookForLinks($out->getSubtitle()));

    // article html text
    if ($wgRealnamesDebug) {
      echo "hookBeforePageDisplay: searching article body...<br>\n";          
    }
    $out->mBodytext = self::lookForLinks($out->getHTML()); 
 
    return true;
  } // function
  
  /**
   * scan and replace plain usernames of the form User:username into real names.
   * @param[in] \string text to scan
   * @param[in] \string pattern to match, \bool false for default
   * @return \string with realnames replaced in      
   * @since 2011-09-16, 0.1
   * @bug we have problems with users with underscores (they become spaces) or spaces, we tend to just strip the User: and leave the username, but we only modify the first word so some weird style might screw it up (2011-09-17, of     
   */     
  public static function lookForBare($text,$pattern=false) {
    if (empty($pattern)) {
      // considered doing [^<]+ here to catch names with spaces or underscores, 
      // which works for most titles but is not universal 
      $pattern = '/User:([^ \t]+)/'; 
    }
    return preg_replace_callback(
      $pattern,
      create_function(
        '$matches',
        'return ExtRealnames::checkBare(array(  
          \'all\' => $matches[0],
          \'username\' => $matches[1],
          ));'), // can't use self::, it's an anonymous function
      $text
      );            
  } // function
  
  /**
   * scan and replace username links into realname links
   * @param[in] \string text to scan
   * @param[in] \string pattern to match, \bool false for default   
   * @return \string with realnames replaced in
   * @since 2011-09-16, 0.1           
   */  
  protected static function lookForLinks($text,$pattern=false) {
    if (empty($pattern)) {
      $pattern = '/(<a\b[^">]+href="[^">]+User:([^"\\?\\&>]+)[^>]+>)(?:User:)?([^>]+)(<\\/a>)/';
    }  
    return preg_replace_callback(
      $pattern,
      create_function(
        '$matches',
        'return ExtRealnames::checkLink(array(
          \'all\' => $matches[0],
          \'linkstart\' => $matches[1],
          \'linkuser\' => $matches[2],
          \'username\' => $matches[3],
          \'linkend\' => $matches[4],
          ));'), // can't use self::, it's an anonymous function
      $text
      );
  } // function
  
  /**
   * obtains user information based on a match for future replacement
   * @param[in] $m \array keyed with strings called 
   *    \li<em>linkstart</em> (optional)
   *    \li<em>username</em> 
   *    \li<em>realname</em> (optional)
   *    \li<em>linkend</em> (optional)
   * @return \string formatted text to replace the match with                
   * @since 2011-09-16, 0.1  
   */     
  protected static function replace($m) {  
    global $wgRealnamesDebug;
    
    if ($wgRealnamesDebug) {
      echo "replace: matched<br>\n";
    }
    
    if (empty(self::$users[$m['username']])) {
      self::$users[$m['username']] = User::newFromName( $m['username'] );  
    }
    $user = self::$users[$m['username']]; 
    if (!is_object($user)) {
      if ($wgRealnamesDebug) {
        echo "replace: skipped, invalid user<br>\n";          
      }  
      return $m['all'];
    }

    // this may be blank        
    $m['realname'] = htmlspecialchars( trim( $user->getRealname() ) );
    
    // re-do the name just in case we can get a cleaner version
    // may not be necesary
    $m['username'] = htmlspecialchars( trim( $user->getName() ) );

    return self::display($m);
  } // function

} // class