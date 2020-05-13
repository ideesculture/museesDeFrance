<?php
require_once __CA_BASE_DIR__ . '/vendor/phpoffice/phpexcel/Classes/PHPExcel.php';
require_once __CA_BASE_DIR__ . '/vendor/phpoffice/phpexcel/Classes/PHPExcel/Writer/Excel2007.php';

$campagnes = $this->getVar('campagnes');
$rd = $this->getVar('rd');

$vo_phpexcel = new PHPExcel();

$vo_phpexcel->getProperties()->setCreator("CollectiveAccess - recolement SMF");
$vo_phpexcel->getProperties()->setLastModifiedBy("CollectiveAccess - recolement SMF");
$vo_phpexcel->getProperties()->setTitle("Tableau de suivi du récolement décennal");
$vo_phpexcel->getProperties()->setSubject("Tableau de suivi du récolement décennal");
$vo_phpexcel->getProperties()->setDescription("Office 2003 XLS – By Webdev – With PHPExel");

$vo_phpexcel->setActiveSheetIndex(0);

$sheet = $vo_phpexcel->getActiveSheet();
$sheet->setTitle('tableau de suivi');

$sheet->SetCellValue('A1', 'Tableau de suivi '.$rd);


if (!isset($campagnes) || !$campagnes) {
	$sheet->SetCellValue('A3', "Aucune campagne de récolement n'est accessible.");
} else {
	$line = 3;

	$sheet->SetCellValue('A3', "Localisation");
	$sheet->SetCellValue('B3', "Localisation (code)");
	$sheet->SetCellValue('C3', "Caractérisation espace");
	$sheet->SetCellValue('D3', "Type de collection\n(champ couvert)");
	$sheet->SetCellValue('E3', "Conditionnement des biens à récoler");
	$sheet->SetCellValue('F3', "Nombre\nd'objets");
	$sheet->SetCellValue('G3', "Accessibilité");
	$sheet->SetCellValue('H3', "Campagne");
	$sheet->SetCellValue('I3', "Intervenants");
	$sheet->SetCellValue('J3', "Dates\nprévisionnelles");
	$sheet->SetCellValue('K3', "Dates\neffectives");
	$sheet->SetCellValue('L3', "Procès verbal");

	for ($column = 'A'; $column <= 'L'; $column++) {
		$cell = $column . $line;
		//Largeur
		$sheet->getColumnDimension($column)->setWidth(16.5);
		//Mettre une bordure sur une case
		$sheet->getStyle($cell)->getBorders()->getLeft()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		$sheet->getStyle($cell)->getBorders()->getRight()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		$sheet->getStyle($cell)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		$sheet->getStyle($cell)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		$sheet->getStyle($cell)->getFont()->setBold(true);
	}

	$line++;

	//Reset largeur colonne F (nombre)
	$sheet->getColumnDimension('F')->setWidth(10);

	foreach ($campagnes as $campagne) {
		$sheet->SetCellValue('A' . $line, $campagne["localisation"]);
		$sheet->SetCellValue('B' . $line, $campagne["localisation_code"]);
		$sheet->SetCellValue('C' . $line, $campagne["caracterisation"]);
		$sheet->SetCellValue('D' . $line, $campagne["champs"]);
		$sheet->SetCellValue('E' . $line, $campagne["conditionnement"]);
		$sheet->SetCellValue('F' . $line, $campagne["nombre"]);
		$sheet->SetCellValue('G' . $line, $campagne["accessibilite"]);
		$sheet->SetCellValue('H' . $line, $campagne["idno"] . " : " . $campagne["name"]);
		$sheet->SetCellValue('I' . $line, $campagne["intervenants"]);
		$sheet->SetCellValue('J' . $line, $campagne["date_campagne_prev"]);
		$sheet->SetCellValue('K' . $line, $campagne["date_campagne"]);
		$sheet->SetCellValue('L' . $line, $campagne["date_campagne_pv"]);

		//Bordures
		for ($column = 'A'; $column <= 'L'; $column++) {
			$cell = $column . $line;
			//Mettre une bordure sur une case
			$sheet->getStyle($cell)->getBorders()->getLeft()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
			$sheet->getStyle($cell)->getBorders()->getRight()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
			$sheet->getStyle($cell)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
			$sheet->getStyle($cell)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
		}

		$sheet->getRowDimension(1)->setRowHeight(-1);
		$line++;

	}

	//Gérer le style de la police
	$sheet->getStyle('A1')->getFont()->setSize(16);
	$sheet->getStyle('A1')->getFont()->setBold(true);

	$sheet->getRowDimension('3')->setRowHeight(33);

	$sheet->duplicateStyleArray(
		array(
			'alignment' => array(
				'wrap' => true,
				'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
				'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
			)
		), 'A3:L3');
	$sheet->duplicateStyleArray(
		array(
			'alignment' => array(
				'wrap' => true,
				'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
			)
		), 'A4:L256');

}

$vo_phpexcel_writer = new PHPExcel_Writer_Excel2007($vo_phpexcel);
$vo_phpexcel_writer->save(__CA_BASE_DIR__ . "/app/plugins/museesDeFrance/download/tableau-suivi.xlsx");

?>

<h1><?php print $rd; ?></h1>
<h2>Tableau de suivi</h2>

<p>Vous pouvez télécharger depuis cette page le fichier au format .xls (Microsoft Excel).<br/> Ce fichier est lisible
	également avec OpenOffice ou LibreOffice.</p>

<a class="form-button" href="<?php print __CA_URL_ROOT__; ?>/app/plugins/museesDeFrance/download/tableau-suivi.xlsx">
	<span class="form-button">
		<img class="form-button-left"
		     src="<?php print __CA_URL_ROOT__; ?>/app/plugins/museesDeFrance/views/images/page_white_excel.png"
		     align=center>
		&nbsp; télécharger
	</span>
</a>
