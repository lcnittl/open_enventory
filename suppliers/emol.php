<?php
/*
Copyright 2006-2018 Felix Rudolphi and Lukas Goossen
open enventory is distributed under the terms of the GNU Affero General Public License, see COPYING for details. You can also find the license under http://www.gnu.org/licenses/agpl.txt

open enventory is a registered trademark of Felix Rudolphi and Lukas Goossen. Usage of the name "open enventory" or the logo requires prior written permission of the trademark holders. 

This file is part of open enventory.

open enventory is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

open enventory is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with open enventory.  If not, see <http://www.gnu.org/licenses/>.
*/
// emol
$GLOBALS["code"]="emol";
$code=$GLOBALS["code"];

$GLOBALS["suppliers"][$code]=array(
	"code" => $code, 
	"name" => "eMolecules.com", 
	"logo" => "emolecules-simple-300x56.gif", 
	"height" => 36, 
	"noExtSearch" => true, 
	"strSearchFormat" => "SMILES",

"init" => function () use ($code) {
	eval(getFunctionHeader());
	$suppliers[$code]["urls"]["server"]="http://www.emolecules.com"; // startPage
	$suppliers[$code]["urls"]["search"]=$urls["server"]."/cgi-bin/search?t=ex&q=";
	$suppliers[$code]["urls"]["substructure"]=$urls["server"]."/cgi-bin/search?t=ss&q=";
	$suppliers[$code]["urls"]["detail"]=$urls["server"]."/cgi-bin/more?vid=";
	$suppliers[$code]["urls"]["startPage"]=$urls["server"];
},
"requestResultList" => function ($query_obj) use ($code) {
	eval(getFunctionHeader());
	$retval["method"]="url";
	if ($query_obj["ops"][0]=="su") {
		$retval["action"]=$urls["substructure"];
	}
	else {
		$retval["action"]=$urls["search"];
	}
	$retval["action"].=$query_obj["vals"][0][0];
	return $retval;
},
"getDetailPageURL" => function ($catNo) use ($code) {
	eval(getFunctionHeader());
		return $urls["detail"].$catNo."&referrer=enventory";
},
"getInfo" => function ($catNo) use ($code) {
	eval(getFunctionHeader());
	$url=$self["getDetailPageURL"]($catNo);
	$my_http_options=$default_http_options;
	$my_http_options["redirect"]=maxRedir;
	$response=oe_http_get($url,$my_http_options); // vid_count[0]
	if ($response==FALSE) {
		return $noConnection;
	}

	return $self["procDetail"]($response,$catNo);
},
"getHitlist" => function ($searchText,$filter,$mode="ct",$paramHash=array()) use ($code) {
	eval(getFunctionHeader());
	$my_http_options=$default_http_options;
	$my_http_options["redirect"]=maxRedir;
	$responses=array();
	$responses[0]=oe_http_get($urls["search"].urlencode($searchText),$my_http_options);
	if ($responses[0]===FALSE) {
		return $noConnection;
	}
	
	$body=utf8_encode(@$responses[0]->getBody());
	$href=$self["getLink"]($body);
	if ($href) {
		$responses[1]=@http_get($urls["emol"]["server"].$href,$my_http_options);
	}
	return $self["procHitlist"]($responses);
},
"procDetail" => function (& $response,$catNo="") use ($code) {
	eval(getFunctionHeader());
	$body=@$response->getBody();
	
	// get all names and all CAS-Nrs, take shortest (seems to be best in most cases) 
	// take as name the 1st one which is contained in at least 3 others (case insenstive), otherwise the 1st
	preg_match_all("/(?ims)<td.*?<\/td>/",$body,$cells,PREG_PATTERN_ORDER);
	$cells=$cells[0];
	for ($e=0;$e<count_compat($cells)-1;$e++) {
		$name=strip_tags($cells[$e]);
		if (!in_array($name,array("Name:","CAS:"))) {
			continue;
		}
		
		$value=strip_tags($cells[$e+1]);
		if (empty($value)) {
			continue;
		}
		
		if ($name=="Name:") {
			$names[]=$value;
		}
		elseif ($name=="CAS:") {
			$cas_nrs[]=makeCAS($value);
		}
	}
	for ($d=0;$d<count_compat($names);$d++) {
		$found=0;
		$search=strtolower($names[$d]);
		if ($search=="") {
			continue;
		}
		for ($e=0;$e<count_compat($names);$e++) {
			if ($e==$d) {
			
			}
			elseif (strpos(strtolower($names[$e]),$search)!==FALSE) {
				$found++;
			}
			if ($found>=2) {
				$name=$names[$d];
				break 2;
			}
		}
	}
	if ($name=="") {
		$name=$names[0];
	}
	
	return array("molecule_name" => $name, "cas_nr" => getBestCAS($cas_nrs), "supplierCode" => "emol", "catNo" => $catNo);
},
"procHitlist" => function (& $responses) use ($code) {
	eval(getFunctionHeader());
	$body="";
	foreach ($responses as $response) {
		$body.=@$response->getBody();
	}
	
	// take compound with highest number of lines
	// split into lines
	preg_match_all("/(?ims)<tr.*?<\/tr>/",$body,$manyLines,PREG_PATTERN_ORDER);
	$manyLines=$manyLines[0];
	
	// go through lines
	$maxlines=0;
	for ($b=0;$b<count_compat($manyLines);$b++) {
		// indentify "View compound info"
		if (strpos($manyLines[$b],"view_compound_info_button")!==FALSE) {
			// handle old catNo
			if (!empty($actCatNo) && $actLines>$maxlines) {
				$catNo=$actCatNo;
				$maxlines=$actLines;
			}
			
			preg_match("/(?ims)\/cgi-bin\/more\?vid=([a-f\d]+)\D/",$manyLines[$b],$actCatNo);
			$actCatNo=$actCatNo[1];
			$actLines=1;
		}
		elseif (!empty($actCatNo)) {
			$actLines++;
		}
	}
	// handle old catNo
	if (!empty($actCatNo) && $actLines>$maxlines) {
		$catNo=$actCatNo;
		$maxlines=$actLines;
	}
	
	return array($self["getInfo"]($catNo)); // only best hit
},
"getBestHit" => function (& $hitlist,$name=NULL) use ($code) {
	if (count_compat($hitlist)>0) {
		return 0;
	}
},
"strSearch" => function ($smiles,$mode="se") use ($code) {
	eval(getFunctionHeader());
	return $self["getHitlist"]($smiles,$mode);
},
// custom
"cutList" => function ($body) use ($code) {
	cutRange($body,"summary=\"Content Table\"","summary=\"Page Jump\"");
	return $body;
},
"getLink" => function ($pageStr) use ($code) {
	preg_match("/(?ims)<a\shref=\"(\/cgi\-bin\/search[^\"]+)\">\d+<\/a>/",$pageStr,$result);
	return fixHtml($result[1]);
},
);
$GLOBALS["suppliers"][$code]["init"]();
//~ $suppliers[$code]["init"]();
?>
