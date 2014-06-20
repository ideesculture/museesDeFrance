<?php
/* ----------------------------------------------------------------------
 * app/views/manage/widget_set_info_html.php :
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source places management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2010 Whirl-i-Gig
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
 * ----------------------------------------------------------------------
 */

$va_global = $this->getVar('global');
$va_campagnes_rd = $this->getVar('campagnes_rd');

//var_dump($va_campagnes_rd);die();
?>
<h3>Statistiques :
	<div>
		<?php foreach($va_campagnes_rd as $rd => $va_campagnes) : ?>
		<p>
			<b><?php print $rd; ?></b><br/>
			<?php print (int)count($va_campagnes["recolements"]); ?> campagnes<br/>
			<?php print (int)$va_campagnes["global"]["recolements_done"]; ?> récolements enregistrés<br/>
			<?php print (int)$va_campagnes["global"]["recolements_total"] - $va_campagnes["global"]["recolements_done"]; ?> récolements en attente<br/>
			<?php print (int)$va_campagnes["global"]["recolements_total"]; ?> objets à récoler<br/>
			<?php if ($va_campagnes["global"]["recolements_total"] > 0) print round($va_campagnes["global"]["recolements_done"] / $va_campagnes["global"]["recolements_total"] * 100); ?>
			% réalisé<br/>
		</p>
		<?php endforeach; ?>
	</div>
</h3>
<h3>Documentation :
	<div>Service des musées de France :<br/>
		<a class='button'
		   href="http://www.culture.gouv.fr/documentation/joconde/fr/partenaires/AIDEMUSEES/recolement-informatise.htm">Récolement
			informatisé</a><br/>
		<a class='button'
		   href="http://www.culture.gouv.fr/documentation/joconde/fr/partenaires/AIDEMUSEES/dossier-inp-inv-rec.pdf">Dossier
			de l'INP</a>
	</div>
</h3>
<h3>Assistance :
	<div>idéesculture :<br/>
		<a class='button' href="mailto:contact@ideesculture.com">contact@ideesculture.com</a><br/>
	</div>
</h3>