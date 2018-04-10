<?php
/**
 * General integration parts with other eventON sections
 */

class evotx_int{
	public function __construct(){
		if(is_admin()){
			add_filter('evo_csv_export_fields', array($this,'export_field_names'), 10,1);
			add_filter('evocsv_additional_csv_fields', array($this,'csv_importer_fields'), 10,1);
		}
	}
	
	// include ticket event meta data fields for exporting events as CSV
	function export_field_names($array){
		global $evotx; 
		$adds = $evotx->functions->get_event_cpt_meta_fields();

		foreach($adds as $ad){
			$array[$ad] = $ad;
		}
		return $array;
	}

	// for CSV Importer
	function csv_importer_fields($array){
		global $evotx; 
		$adds = $evotx->functions->get_event_cpt_meta_fields();

		return array_merge($array, $adds);
	}
}

new evotx_int();