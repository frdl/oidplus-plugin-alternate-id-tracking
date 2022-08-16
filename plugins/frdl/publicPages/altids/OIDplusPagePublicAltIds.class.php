<?php

/*
 * OIDplus 2.0
 * Copyright 2022 Till Wehowski, Frdlweb
 *
 * Licensed under the MIT License.
 */

if (!defined('INSIDE_OIDPLUS')) die();

class OIDplusPagePublicAltIds extends OIDplusPagePluginPublic {


	public function action($actionID, $params) {

 
	}

	
	public function init($html=true) {
     
		OIDplus::db()->query("CREATE TABLE IF NOT EXISTS ###alt_ids (
                   `alt_id` int(11) NOT NULL AUTO_INCREMENT,
                   `id` varchar(256) NOT NULL,
                   `alt` varchar(256) NOT NULL,
                   `ns` varchar(32) NOT NULL DEFAULT 'guid',
                   `description` varchar(256) NOT NULL,
                   `t` int(11) NOT NULL,
                   PRIMARY KEY (`alt_id`),
                   UNIQUE KEY `id` (`id`,`alt`,`ns`) USING BTREE
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    
    
	}

	public function gui($id, &$out, &$handled) {
	 
	}

	public function publicSitemap(&$out) {
	 
	}

	public function tree(&$json, $ra_email=null, $nonjs=false, $req_goto='') {
		return false;
	}

 

	public function implementsFeature($id) {
		if (strtolower($id) == '1.3.6.1.4.1.37476.2.5.2.3.2') return true; // modifyContent
		if (strtolower($id) == '1.3.6.1.4.1.37476.2.5.2.3.3') return true; // beforeObject*, afterObject*
		if (strtolower($id) == '1.3.6.1.4.1.37476.2.5.2.3.4') return true; // whois*Attributes
		return false;
	}

	public function modifyContent($id, &$title, &$icon, &$text) {
		// Interface 1.3.6.1.4.1.37476.2.5.2.3.2

		$output = '';
		$doshow = false;
    
		$this->_handle($id);     
		
		if(!empty($output)){
		   $text.=$output;	
		}
	}
  
       
	protected function _handle($id){
				
		try{        
			$this->handleAltIds($id, true);     
		}catch(\Exception $e){       
			throw new OIDplusException($e->getMessage());    
		}
		
	}
	protected function _del($id){
	   $p= explode(':', $id, 2);
		$ns = $p[0];
		$IDX=$p[1];
		
	    OIDplus::db()->query("DELETE FROM ###alt_ids WHERE `id` = ? OR (`ns` = ? AND `alt_id` = ? )", [$id, $ns, $IDX]);
		
	}
	
	public function beforeObjectDelete($id) {  
		// Interface 1.3.6.1.4.1.37476.2.5.2.3.3    
               $this->_del($id);
		
	} 
	
	public function afterObjectDelete($id) {
		// Interface 1.3.6.1.4.1.37476.2.5.2.3.3
		$this->_del($id);
	}
	public function beforeObjectUpdateSuperior($id, &$params) {} // Interface 1.3.6.1.4.1.37476.2.5.2.3.3
	public function afterObjectUpdateSuperior($id, &$params) {
	
	} // Interface 1.3.6.1.4.1.37476.2.5.2.3.3
	public function beforeObjectUpdateSelf($id, &$params) {} // Interface 1.3.6.1.4.1.37476.2.5.2.3.3
	
	public function afterObjectUpdateSelf($id, &$params) {
	   $this->_del($id);	
	   $this->_handle($id);     
	} // Interface 1.3.6.1.4.1.37476.2.5.2.3.3
	
	
	
	public function beforeObjectInsert($id, &$params) {} // Interface 1.3.6.1.4.1.37476.2.5.2.3.3
	
	
	public function afterObjectInsert($id, &$params) {
	   $this->_handle($id);     
	} // Interface 1.3.6.1.4.1.37476.2.5.2.3.3
	
	

	public function tree_search($request) {
		return false;
	}

	public function whoisObjectAttributes($id, &$out) {
		// Interface 1.3.6.1.4.1.37476.2.5.2.3.4

		$xmlns = 'oidplus-frdlweb-altids-plugin';
		$xmlschema = 'urn:oid:1.3.6.1.4.1.37553.8.1.8.8.53354196964.641310544.1714020422';
		$xmlschemauri = OIDplus::webpath(__DIR__.'/altids.xsd',OIDplus::PATH_ABSOLUTE);

		$info = $this->handleAltIds($id, true);
                $canonicalShown = false;
		
		foreach($info['altIds'] as $alt){
 
     
			if(false === $canonicalShown && $alt['id'] === $id){
			    $canonicalShown=true;
				
				$out[] = [				
					'xmlns' => $xmlns,				
					'xmlschema' => $xmlschema,				
					'xmlschemauri' => $xmlschemauri,				
					'name' => 'canonical-identifier',				
					'value' => $alt['id'],			
				];
    
			}
      
		
			$out[] = [
				'xmlns' => $xmlns,
				'xmlschema' => $xmlschema,
				'xmlschemauri' => $xmlschemauri,
				'name' => 'alternate-identifier',
				'value' => $alt['ns'].':'.$alt['alt'],
			];     
  
		}
 
	
	}
	
	
	public function whoisRaAttributes($email, &$out) {
           // Interface 1.3.6.1.4.1.37476.2.5.2.3.4    

	} 
  
  
 
   public function getAltIdsInfo($id){
	try{
	   $obj = OIDplusObject::parse($id);
	 }catch(\Exception $e){
		$obj = false; 
	 }
	   
	   if($obj !== false){
		  $alt_ids = $obj->getAltIds();
		  $alt=[];
		  foreach($alt_ids as $a){
			   $alt[] = [
				     'id'=>$obj->nodeId(true),
				     'alt' =>$a->getId(),
				     'ns' => $a->getNamespace(),
				     'description' => $a->getDescription(),
				     
				  ];
		  }
		  
		  $res_alt_ids = [];
		  $res_alt=[];
			  $res = OIDplus::db()->query("select * from ".OIDplus::baseConfig()->getValue('TABLENAME_PREFIX', 'oidplus_')."alt_ids where id = ?", [$obj->nodeId(true)]);
			
		   while ($row = $res->fetch_array()) {
			  $res_alt_ids[]= new OIDplusAltId($row['ns'], $row['alt'],  $row['description']);
			  $res_alt[] = [
				     'id' => $row['id'],
				     'alt' => $row['alt'],
				     'ns' => $row['ns'],
				     'description' => $row['description'],
				     
				  ];
		  }   
		   
		   sort($alt);
		   sort($res_alt);
		   
		   $diff = array_udiff($alt, $res_alt, function($a, $b){
			 
			   
			  if(//0===count(array_diff($a, $b))  &&
				  $a['id'] === $b['id']
				  && $a['alt'] === $b['alt']
				  && $a['ns'] === $b['ns']
				//  && $a['description'] === $b['description']
				){
				 return 0;
			  }else if(//0 < count(array_diff($a, $b))   || 
				$a['id'] !== $b['id']
				  || $a['alt'] !== $b['alt']
				  || $a['ns'] !== $b['ns']
				 // || $a['description'] !== $b['description']
				){ 
				   return -1;
			  }else{ 
			  	return 1;  
			  }
		   });
		   
 
	   }//obj not false
	   
	   sort($diff);
	  return [
		  'altIds' => $alt,
		  'notInDB'=> $diff,
		  'inDB'=> $res_alt,
	  ];
   }
	
  public function handleAltIds($id, $insertMissing = false){
		  try{
	             $obj = OIDplusObject::parse($id);
	           }catch(\Exception $e){
	                $obj = false; 
	           }
	 $info = (false===$obj) ? $obj : $this->getAltIdsInfo($id);
	 if(false!==$obj && true === $insertMissing && 0<count($info['notInDB']) ){
			 
		// foreach(array_unique($info['notInDB']) as $num => $_inf){
		 foreach($info['notInDB'] as $num => $_inf){
				
			  try{	 
				 $res = OIDplus::db()->query("insert into ".OIDplus::baseConfig()->getValue('TABLENAME_PREFIX', 'oidplus_')."alt_ids set id = ?, alt = ?,ns = ?,description = ?, t = ?", [
					 $obj->nodeId(true),
					 $_inf['alt'],
					 $_inf['ns'],
					 $_inf['description'],	
					 time(),
				 ]); 
	         
			  }catch(\Exception $e){			
				  throw new OIDplusException($e->getMessage());	         
			  }
				
		 }
			 $info = $this->getAltIdsInfo($id);
	}
     return $info;
   } 
	
 }
