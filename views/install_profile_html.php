<?php
    $joconde_available = $this->getVar('joconde_available');
    $joconde_installed = $this->getVar('joconde_installed');

?>

<h1>Installation du profil</h1>


<?php if ($joconde_available == "true") : ?>
    <h2>Aucune action requise</h2>
    <p>Le profil joconde est déjà disponible dans votre installation de Providence.</p>
<?php else : ?>
    <h2>Installation du profil Joconde parmi les profils disponibles</h2>

    <?php if(__CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__) : ?>
        <b>Attention, votre setup.php ne permet pas d'écraser la base existante.</b>
        <p>Vous devez modifier le fichier setup.php situé à la racine de l'installation de Providence.</p>
        <p>A l'aide d'un éditeur de texte, cherchez la ligne <b>__CA_ALLOW_INSTALLER_TO_OVERWRITE_EXISTING_INSTALLS__</b>
            et passez la valeur à <b><i>true</i></b></p>
        <p>Une fois ceci fait, vous pourrez relancer l'installation de la base de données avec les informations suivantes.</p>
        <hr/>
    <?php else : ?>

    <?php endif; ?>

    <?php if ($joconde_installed == "true") : ?>
        <p>Le profil Joconde vient d'être recopié dans les profils disponibles (répertoire install/profiles/xml).</p>
        <p>Veuillez relancer l'installation de la base en choisissant Joconde via : </p>
        <p><a href="http://<?php print __CA_SITE_HOSTNAME__.__CA_URL_ROOT__;?>/install">
                http://<?php print __CA_SITE_HOSTNAME__.__CA_URL_ROOT__;?>/install
        </a></p>
    <?php else : ?>
        <p>Le profil n'a pas pu être recopié dans le répertoire install/profiles/xml</p>
        <p>Veuillez vérifier les droits d'écriture sur le répertoire install/profiles/xml et réessayer.</p>
    <?php endif; ?>
<?php endif; ?>