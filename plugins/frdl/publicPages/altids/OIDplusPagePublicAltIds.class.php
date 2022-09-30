<?php

/*
 * OIDplus 2.0
 * Copyright 2022 Daniel Marschall, ViaThinkSoft / Till Wehowski, Frdlweb
 *
 * Licensed under the MIT License.
 */
/*
*  ToDo: Cache proxy (to canonical) response?
*        proxy (to canonical) if POST/PUT/DELETE Method or if CLI?
*        DONE: change cache to "per ID -" or getAlternativesForQuery($id) Cache instead of the entire DB?
*/
if (!defined('INSIDE_OIDPLUS')) die();

class OIDplusPagePublicAltIds extends OIDplusPagePluginPublic {

	protected static $cache = null;
	protected static $caches = [];
	
	public function action($actionID, $params) {

	}

	public function gui($id, &$out, &$handled) {
	
	}

	public function publicSitemap(&$out) {
	 
	}

	public function tree(&$json, $ra_email=null, $nonjs=false, $req_goto='') {
		return false;
	}

	
        public function readAll($noCache = false) {
			if(true !== $noCache && null !== self::$cache){
			   return self::$cache;	
			}
                $alt_ids = array();
                $rev_lookup = array();

                $res = OIDplus::db()->query("select id from ###objects");
                while ($row = $res->fetch_array()) {
                        $obj = OIDplusObject::parse($row['id']);
                        if (!$obj) continue; // e.g. if plugin is disabled
                        $ary = $obj->getAltIds();
                        foreach ($ary as $a) {
                                $origin = $obj->nodeId(true);
                                $alternative = $a->getNamespace() . ':' . $a->getId();

                                if (!isset($alt_ids[$origin])) $alt_ids[$origin] = array();
                                $alt_ids[$origin][] = $alternative;

                                if (!isset($rev_lookup[$alternative])) $rev_lookup[$alternative] = array();
                                $rev_lookup[$alternative][] = $origin;
                        }
                }
              
			
		self::$cache = array($alt_ids, $rev_lookup);		
		return self::$cache;
        }
	
	
       public function getAlternativesForQuery($id/* 1.3.6.1.4.1.37476.2.5.2.3.7 signature takes just 1 param!? , $noCache = false*/) {				
	       if(/*true !== $noCache && */isset(self::$caches[$id]) ){			  
		    return self::$caches[$id];				
	       }	   
     
			 list($ns, $altIdRaw) = explode(':', $id, 2);
			 if($ns === 'weid'){
				$id='oid:'.\WeidOidConverter::weid2oid($id);
			 }	   	   
		 
	       list($alt_ids, $rev_lookup) = $this->readAll(false);
		   
		     $res = [
			   $id,
			 ];
		     if(isset($rev_lookup[$id])){
				 $res = array_merge($res, $rev_lookup[$id]);
		     }
		    foreach($alt_ids as $original => $altIds){
				if($id === $original || in_array($id, $altIds) ){
					 $res = array_merge($res, $altIds);
					 $res = array_merge($res, [$original]);
				}
			}
			
		   $weid = false;
		   foreach($res as $alt){
			 list($ns, $altIdRaw) = explode(':', $alt, 2);			
			 if($ns === 'oid'){
				$weid=\WeidOidConverter::oid2weid($altIdRaw);
				 break;
			 }	   
		   }

		   if(false !== $weid){
			 $res[]=$weid;   
		   }
		   $res = array_unique($res);		   		   
               
		   self::$caches[$id] = $res;
           return $res;
        }
	

	public function implementsFeature($id) {
		if (strtolower($id) == '1.3.6.1.4.1.37476.2.5.2.3.4') return true; // whois*Attributes
		if (strtolower($id) == '1.3.6.1.4.1.37476.2.5.2.3.7') return true; // getAlternativesForQuery
		return false;
	}	

	public function tree_search($request) {
	
		return false;
	}

	public function getCanonical($id){
		foreach($this->getAlternativesForQuery($id) as $alt){
			 list($ns, $altIdRaw) = explode(':', $alt, 2);
			 if($ns === 'oid'){
				 return $alt;
			 }
		}
	
		return false;
	}
	
	public function whoisObjectAttributes($id, &$out) {
		// Interface 1.3.6.1.4.1.37476.2.5.2.3.4

		$xmlns = 'oidplus-frdlweb-altids-plugin';
		$xmlschema = 'urn:oid:1.3.6.1.4.1.37553.8.1.8.8.53354196964.641310544.1714020422';
		$xmlschemauri = OIDplus::webpath(__DIR__.'/altids.xsd',OIDplus::PATH_ABSOLUTE);
		
		$handleShown = false;
		$canonicalShown = false;
		
		foreach($this->getAlternativesForQuery($id) as $alt){
			
			list($ns, $altIdRaw) = explode(':', $alt, 2);
				 
 			if(false === $canonicalShown && $ns === 'oid'){
			    $canonicalShown=true;
				
				$out[] = [				
					'xmlns' => $xmlns,				
					'xmlschema' => $xmlschema,				
					'xmlschemauri' => $xmlschemauri,				
					'name' => 'canonical-identifier',				
					'value' => $ns.':'.$altIdRaw,			
				];
    
			}
     
			if(false === $handleShown && $alt === $id){
			    $handleShown=true;
				
				$out[] = [				
					'xmlns' => $xmlns,				
					'xmlschema' => $xmlschema,				
					'xmlschemauri' => $xmlschemauri,				
					'name' => 'handle-identifier',				
					'value' => $alt,			
				];
    
			}
      
		
			$out[] = [
				'xmlns' => $xmlns,
				'xmlschema' => $xmlschema,
				'xmlschemauri' => $xmlschemauri,
				'name' => 'alternate-identifier',
				'value' => $ns.':'.$altIdRaw,
			];     
  
		}
 
	
	}
	
	
	public function whoisRaAttributes($email, &$out) {
           // Interface 1.3.6.1.4.1.37476.2.5.2.3.4    

	} 
  

 
 }
