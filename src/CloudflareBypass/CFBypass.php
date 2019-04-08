<?php
namespace CloudflareBypass;
include("JSFUCK.php");
use \CloudflareBypass\Util\Logger;

/**
 * CF Bypass utility.
 * Bypasses CF (solves JS challenge)
 * @author Kyran Rana
 */
class CFBypass
{
    /**
     * Given page content and headers, will check if page is protected by CF.
     * (This method is NOT accurate and may fail in rare cases) 
     *
     * @access public
     * @param string $content  Response body
     * @param array $http_code  Response http code
     * @return bool 
     */
    public static function isBypassable( $content, $http_code )
    {
        // IUAM page should have a 503 error code.
        if ((int)$http_code !== 503)
            return false;

        /* IUAM page should have the following strings:
         * - jschl_vc, jschl_answer, pass, /cdn-cgi/l/chk_jschl
         */ 
        $required_fields = [ "s", "jschl_vc", "jschl_answer", "pass"];

        foreach ( $required_fields as $field ) {
            if ( strpos( $content, $field ) === false )
                return false;
        }

        return true;
    }


    /**
     * Assembles clearance link.
     * 
     * @access public
     * @param string $uri  URL components
     * @param string $s S code.
     * @param string $jschl_vc  JSCHL VC value
     * @param string $pass  Pass value
     * @param string $jschl_answer  JSCHL answer value
     */
    public static function assemble( $uri, $s, $jschl_vc, $pass, $jschl_answer ) {
        $query = [];
    
        if (isset( $uri['query'] ))
            parse_str( $uri['query'], $query );

        return sprintf("%s://%s/cdn-cgi/l/chk_jschl?%s", 
            $uri['scheme'], 
            $uri['host'],

            // add user params and cf params.
            http_build_query(array_merge( 
            [
                's'                 => $s, 
                'jschl_vc'          => $jschl_vc,
                'pass'              => $pass, 
                'jschl_answer'      => $jschl_answer             
            ], 
            $query )));;
    }


    /**
     * Solves JS challenge on the IUAM page and returns the following fields: 
     * - jschl_vc
     * - pass
     * - jschl_answer.
     *
     * @access public
     * @param string $iuam  CF IUAM page.
     * @param string $url  Request URL
     * @param boolean $verbose_mode  TRUE to enable verbose mode.
     * @throws \ErrorException  if "jschl_vc" and "pass" input values CAN NOT be extracted.
     * @throws \ErrorException  if JS challenge code CAN NOT be extracted
     * @throws \ErrorException  if PHP evaluation of JS challenge code FAILS
     * @return array  jschl_vc, pass, jschl_answer
     */
    public static function bypass( $iuam, $url, $verbose_mode=false )
    {
        // -- 1. Wait for 5 seconds.

        sleep(5);

        // Debug
        if ($verbose_mode)
            Logger::info("CFBypass 1. Waiting for 4 seconds...");
        


        try {

            // -- 2. Extract "s", "jschl_vc" and "pass" input values.

            $s          = self::getInputValue( $iuam, 's' );
            $jschl_vc   = self::getInputValue( $iuam, 'jschl_vc' );
            $pass       = self::getInputValue( $iuam, 'pass' );

            if ($jschl_vc === null || $pass === null) {
                throw new \ErrorException("Unable to fetch \"jschl_vc\" and \"pass\" parameters!");
            }

            // Debug
            if ($verbose_mode) {
                Logger::info("CFBypass 2. Fetching parameters...");
                Logger::info(sprintf( "\t\ts:\t%s", $s ));
                Logger::info(sprintf( "\t\tjschl_vc:\t%s", $jschl_vc ));
                Logger::info(sprintf( "\t\tpass:\t\t%s", $pass ));
            }



            // -- 3. Calculate JS challenge answer.

            $uri = parse_url( $url );

            $jschl_answer = self::getJschlAnswer( $iuam ,$url) + mb_strlen( $uri['host'] );

            // Debug
            if ($verbose_mode) {
                Logger::info("CFBypass 3. Calculating JS challenge answer...");
                Logger::info(sprintf( "\t\tjschl_answer:\t%s", $jschl_answer ));
            }
        
            return array( $s, $jschl_vc, $pass, $jschl_answer );

        } catch( Exception $ex ) {

            // Debug
            if ($verbose_mode)
                Logger::error(sprintf( "CFBypass ERROR: %s", $ex->getMessage() ));

            throw new \ErrorException( $ex );
        }
    }



    // {{{ Getters

    /**
     * Get input value.
     *
     * @access public
     * @param string $iuam  CF IUAM page.
     * @param string name  input name
     * @return string  value.
     */
    public static function getInputValue( $iuam, $name )
    {
        preg_match( '/name="' . $name . '" +value="(.+?)"/', $iuam, $matches );

        return isset( $matches[1] ) ? $matches[1] : null;
    }


    /**
     * Gets jschl answer.
     *
     * @access public
     * @param string $iuam  CF IUAM page.
     * @return float  jschl answer.
     */
    public static function getJschlAnswer( $iuam,$url )
    {
    	//View Page Test
        //echo str_replace("plus","",str_replace("function(p){return eval",'function(p){alert(eval((true+"")[0]+".ch"+(false+"")[1]+(true+"")[1]+Function("return escape")()(("")["italics"]())[2]+"o"+(undefined+"")[2]+(true+"")[3]+"A"+(true+"")[0]+"("+p+")"));return eval',str_replace("t.firstChild.href",'"'.$url.'"',str_replace("4000","0",str_replace("f.submit();","",str_replace("f.action += location.hash;","f.action =\"".$url."cdn-cgi/l/chk_jschl\";",$iuam))))));
       
        //Second Function
        preg_match('/\(function\(p\)\{.*\}\((.*?)\)\)\);/',$iuam,$function2);
        $calc=calculate_jsfuck($function2[1]);
        $link=str_replace(array("https://","http://"),"",$url);
        $link=substr($link,0,-1);
        $charted_text = hexdec(bin2hex(mb_convert_encoding(mb_substr($link, eval('return '.$calc.';'), 1, 'UTF-8'),'UTF-32BE','UTF-8')));
        $iuam = str_replace($function2[0],"(plus".$charted_text."));",$iuam);
        
        //First Function
        preg_match('/function\(p\)\{.*\(p\)\}\(\);/',$iuam,$function1);
        preg_match('/<div style=\".*\" id=\"cf-dn-.*\">(.*?)</',$iuam,$replacer);
        $c = $replacer[1];
        $iuam = str_replace($function1[0],$c.";",$iuam);
        // -- 1. Extract JavaScript challenge from IUAM page.
        $iuam_jschl = "";
        
        preg_match( '/(?<=s,t,o,p,b,r,e,a,k,i,n,g,f,\s)(\w+)={"(\w+)":(.+?)(?=})/', $iuam, $iuam_jschl_def_matches );
        list( $_, $var1, $var2, $code ) = $iuam_jschl_def_matches;
        
        $iuam=str_replace("a.value = (+$var1.$var2).toFixed(10); '; 121'","",$iuam);
        preg_match_all( '/' . $var1 . '\.' . $var2 . '[+\-*\/]?=.+?;/', $iuam, $iuam_jschl_matches );
        $iuam_jschl = "=$code\n";
        foreach ( $iuam_jschl_matches[0] as $jschl_match ) {
            $iuam_jschl.= str_replace( array("$var1.$var2",";"), '', $jschl_match ) . "\n";
        }
        
        // -- 2. Solve JavaScript challenge.
        $iuam_jschl_ex=explode("\n",$iuam_jschl);
        $longcalc="";
        for($i=0;$i<count($iuam_jschl_ex)-1;$i++){
        	$dataiuam=explode("=",$iuam_jschl_ex[$i]);
        	if(strlen($dataiuam[0])>0){
        		if(is_numeric($dataiuam[1])){
        			$longcalc=eval('return '.$longcalc.$dataiuam[0].$dataiuam[1].';');
        		}else{
        			$longcalc=eval('return '.$longcalc.$dataiuam[0].eval('return '.calculate_jsfuck($dataiuam[1]).';').';');
        		}
        	}else{
        		
        		if(is_numeric($dataiuam[1])){
        			$longcalc=$dataiuam[1];
        		}else{
        			$longcalc=eval('return '.calculate_jsfuck($dataiuam[1]).';');
        		}
        	}
        }
        $jschl_answer=eval('return '.$longcalc.';');
        return round( $jschl_answer, 10 );
    }

    // }}}
}
