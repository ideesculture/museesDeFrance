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

$va_campagnes= $this->getVar('campagnes');
$va_global= $this->getVar('global');
?>
	<h3>Statistiques :
		<div>
			<?php print (int) $va_global["nb_campagnes"]; ?> campagnes<br/>
			<?php print (int) $va_global["recolements_done"]; ?> récolements enregistrés<br/>
			<?php print (int) $va_global["recolements_left"]; ?> récolements en attente<br/>
			<?php print (int) $va_global["recolements_total"]; ?> objets dans les campagnes<br/>
			<?php print round($va_global["recolements_done"] / $va_global["recolements_total"] * 100, 2); ?>% réalisé<br/>
		</div>
	</h3>
<h3>Documentation :
	<div>Service des musées de France :<br/>
	<a  class='button' href="http://www.culture.gouv.fr/documentation/joconde/fr/partenaires/AIDEMUSEES/recolement-informatise.htm">Récolement informatisé</a><br/>
	<a  class='button' href="http://www.culture.gouv.fr/documentation/joconde/fr/partenaires/AIDEMUSEES/dossier-inp-inv-rec.pdf">Dossier de l'INP</a>
		</div>
</h3>
<h3>Assistance :
	<div>idéesculture :<br/>
		<a  class='button' href="mailto:contact@ideesculture.com">contact@ideesculture.com</a><br/>
	</div>
</h3>