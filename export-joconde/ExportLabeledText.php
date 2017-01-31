<?php
/** ---------------------------------------------------------------------
 * ExportLabeledText.php : defines Labeled Text export format (one line with field header/one line with field value)
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2013 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This source code is free and modifiable under the terms of
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * @package CollectiveAccess
 * @subpackage Export
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */

require_once(__CA_LIB_DIR__.'/ca/Export/BaseExportFormat.php');

class ExportLabeledText extends BaseExportFormat {
	# ------------------------------------------------------

	# ------------------------------------------------------
	public function __construct(){
		$this->ops_name = 'LABELEDTXT';
		$this->ops_element_description = _t('ASCII Text file. One line with field header/one line with field value');
		parent::__construct();
	}
	# ------------------------------------------------------
	public function getFileExtension($pa_settings) {
		return 'txt';
	}
	# ------------------------------------------------------
	public function getContentType($pa_settings) {
		return 'text/txt';
	}
	# ------------------------------------------------------
	public function processExport($pa_data,$pa_options=array()){
		$va_labeled_txt = "";

		$va_replaced_characters = array('\n','\r','\t');
		$va_replacement_characters = array("\n","\r","\t");

		$vs_eol = (isset($pa_options['settings']['LABELEDTXT_record_separator']) ? $pa_options['settings']['LABELEDTXT_end_of_line'] : "\n");
		$vs_record_separator = (isset($pa_options['settings']['LABELEDTXT_record_separator']) ? $pa_options['settings']['LABELEDTXT_record_separator'] : "\n");
		// Doing simple search & replace for \n, \r and \t
		$vs_eol = str_replace($va_replaced_characters,$va_replacement_characters,$vs_eol);
		$vs_record_separator = str_replace($va_replaced_characters,$va_replacement_characters,$vs_record_separator);

		foreach($pa_data as $pa_item){
			switch($pa_item['element']) {
				case 'REF':
				default:
					$va_labeled_txt .= $pa_item['element'].$vs_eol;
					$va_labeled_txt .= $pa_item['text'].$vs_eol;
					break;
			}
		}
		return $va_labeled_txt.$vs_record_separator;
	}
	# ------------------------------------------------------
	public function getMappingErrors($t_mapping){
		$va_errors = array();
		$va_top = $t_mapping->getTopLevelItems();

		foreach($va_top as $va_item){
			$t_item = new ca_data_exporter_items($va_item['item_id']);

			$vs_element = $va_item['element'];
			/*if(!is_numeric($vs_element)){
				$va_errors[] = _t("Element %1 is not numeric",$vs_element);
			}
			if(intval($vs_element) <= 0){
				$va_errors[] = _t("Element %1 is not a positive number",$vs_element);
			}
			*/
			if(sizeof($t_item->getHierarchyChildren())>0){
				$va_errors[] = _t("LABELEDTXT exports can't be hierarchical",$vs_element);
			}
		}

		return $va_errors;
	}
	# ------------------------------------------------------
}
BaseExportFormat::$s_format_settings['LABELEDTXT'] = array(
	'LABELEDTXT_record_separator' => array(
		'formatType' => FT_TEXT,
		'displayType' => DT_SELECT,
		'width' => 40, 'height' => 1,
		'takesLocale' => false,
		'default' => '"',
		'label' => _t('Record separator'),
		'description' => _t('Record separator. Default to \n (carriage return).')
	),
	'LABELEDTXT_end_of_line' => array(
		'formatType' => FT_TEXT,
		'displayType' => DT_SELECT,
		'width' => 40, 'height' => 1,
		'takesLocale' => false,
		'default' => '"',
		'label' => _t('End of line characters'),
		'description' => _t('End of line characters. Defaut to \n (carriage return).')
	)
);
