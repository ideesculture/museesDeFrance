<?php
    $vn_id = $this->getVar("id");

    MetaTagManager::addLink('stylesheet', __CA_URL_ROOT__."/app/plugins/museesDeFrance/assets/css/museesDeFrance.css",'text/css');

?>
<h1>Transfert des objets d'un ensemble dans l'inventaire</h1>
<script>

    function doClear()
    {
        console.log("doclear");
        document.getElementById("divProgress").innerHTML = "";
    }

    function log_message(message)
    {
        console.log("log_message");
        document.getElementById("divProgress").innerHTML += message + '<br />';
    }

    function ajax_stream()
    {
        console.log("ajax_stream");

        if (!window.XMLHttpRequest)
        {
            log_message("Your browser does not support the native XMLHttpRequest object.");
            return;
        }

        try
        {
            var xhr = new XMLHttpRequest();
            xhr.previous_text = '';

            //xhr.onload = function() { log_message("[XHR] Done. responseText: <i>" + xhr.responseText + "</i>"); };
            xhr.onerror = function() { log_message("[XHR] Fatal Error."); };
            xhr.onreadystatechange = function()
            {
                try
                {
                    if (xhr.readyState > 2)
                    {
                        var new_response = xhr.responseText.substring(xhr.previous_text.length);
                        //console.log(new_response);
                        var result = JSON.parse( new_response );
                        //console.log(result);
                        //log_message(result.message);
                        //update the progressbar
                        console.log(result.progress );
                        document.getElementById('progressor').style.width = result.progress + "%";
                        document.getElementById('message').innerHTML = result.message;
                        if (result.progress > 0) {
                            document.getElementById('launchbutton').style.display = "none";
                            document.getElementById('progression').innerHTML = Math.round(result.progress) + "%";
                        }
                        if(result.progress == "100") {
                            document.getElementById('message').innerHTML = "Transfert terminé.";
                            //document.getElementById('backlink').style.display = "block";
                            document.getElementById('indexlink').style.display = "block";
                        }
                        xhr.previous_text = xhr.responseText;
                    }
                }
                catch (e)
                {
                    //log_message("<b>[XHR] Exception: " + e + "</b>");
                }


            };
            console.log("<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/InventaireBiensAffectes/TransferSetAjax/id/<?php print $vn_id; ?>");
            xhr.open("GET", "<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/InventaireBiensAffectes/TransferSetAjax/id/<?php print $vn_id; ?>", true);
            xhr.send("Making request...");
        }
        catch (e)
        {
            log_message("<b>[XHR] Exception: " + e + "</b>");
        }
    }

</script>

<div id="divProgress"></div>
<div style="border:1px solid #ccc; width:300px; height:20px; overflow:auto; background:#eee;width:100%;">
    <div id="progressor" style="background:#07c; width:0%; height:100%;"></div>
</div>
<p><small id="progression"></small> <span id="message"></span></p>
<p>
    <a id="backlink" style="display:none" onclick="parent.history.back();return false;">Retour</a>
    <a id="launchbutton" href='#' onclick="ajax_stream();" class='form-button'><span class='form-button'><img src='<?php print __CA_URL_ROOT__; ?>/themes/default/graphics/buttons/save.png' border='0' alt='Save' class='form-button-left' style='padding-right: 10px;'/> Transférer les objets de l'ensemble vers l'inventaire</span></a>
    <a id="indexlink" style="display:none" href="<?php print __CA_URL_ROOT__; ?>/index.php/museesDeFrance/InventaireBiensAffectes/Index">Afficher l'inventaire des biens affectés</a>
</p>
