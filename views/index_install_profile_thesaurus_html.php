<?php
$joconde_available = $this->getVar('joconde_available');
?>
<h1>Installation du plugin Musées de France</h1>

<?php if ($joconde_available == "false") : ?>
<h2>Installation du profil</h2>
<p><a href="<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/InstallProfileThesaurus/Profile">installer le profil XML</a>.</p>
<?php endif; ?>

<h2>Installation des thésaurus du Service des Musées de France</h2>
<p><a href="<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/InstallProfileThesaurus/Thesaurus">installer les thésaurus</a>.</p>
