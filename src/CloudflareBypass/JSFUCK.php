?php
    function decode_jsfuck_number($data){
    	//$data=str_replace(array("",""),"",$data);
    	$data=str_replace(array("+!+[]","+!![]","!+[]"),"+1",$data);
    	$data=str_replace(array("+[]","!![]","!+[]"),"+0",$data);
    	return "(".str_replace("plus","+",decode_bynary_jsfuck($data)).")";
    }
    function decode_bynary_jsfuck($data){
    	preg_match_all("/\((.*?)\)/",$data,$match);
    	$m1=$match[1];
    	$number="";
    	for($i=0;$i<count($m1);$i++){
    		$number.=eval('return '.str_replace("++","",str_replace(array("(",")"),"",$m1[$i])).';');
    	}
    	return $number;
    }
    function calculate_jsfuck($data){
    	$oprep=str_replace(array("-","*","/"),"_",$data);
    	$oprep=explode("_",$oprep);
    	$opres=$data;
    	for($i=0;$i<count($oprep);$i++){
    		$opres=str_replace($oprep[$i],decode_jsfuck_number($oprep[$i]),$opres);
    	}
    	return $opres;
    }
    function encode_jsfuck_number_simple($number){
    	$enctext="((";
    	for($i=0;$i<$number;$i++){
    		$enctext.="+!![]";
    	}
    	$enctext.="))";
    	return $enctext;
    }
?>
