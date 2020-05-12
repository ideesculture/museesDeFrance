<style>
	html, body {
		font-family: monospace;
	}
</style>
<?php
	

/**
 * Execute the given command by displaying console output live to the user.
 *  @param  string  cmd          :  command to be executed
 *  @return array   exit_status  :  exit status of the executed command
 *                  output       :  console output of the executed command
 */
function liveExecuteCommand($cmd)
{

    while (@ ob_end_flush()); // end all output buffers if any

    $proc = popen("$cmd 2>&1 ; echo Exit status : $?", 'r');

    $live_output     = "";
    $complete_output = "";

    while (!feof($proc))
    {
        $live_output     = fread($proc, 4096);
        $complete_output = $complete_output . $live_output;
        echo "$live_output<br/>";
        @ flush();
    }

    pclose($proc);

    // get exit status
    preg_match('/[0-9]+$/', $complete_output, $matches);

    // return exit status and intended output
    return array (
                    'exit_status'  => intval($matches[0]),
                    'output'       => str_replace("Exit status : " . $matches[0], '', $complete_output)
                 );
}
$command = 'cd '.__DIR__.'/tmp/ && /usr/local/bin/wkhtmltopdf --footer-right [page]/[topage] --footer-font-size 8 inventaire.html inventaire.pdf';
var_dump($command);
$result = liveExecuteCommand($command);

if($result['exit_status'] === 0){
   // do something if command execution succeeds
   print "---------------------<br/>";
   print "<a target='_blank' href='/app/plugins/museesDeFrance/tmp/inventaire.pdf'>Télécharger l'inventaire généré</a> <small>Attention, fichier souvent de plus 100 MO.</small>";
   
} else {
    print "Error : ".$result['exit_status'];
}