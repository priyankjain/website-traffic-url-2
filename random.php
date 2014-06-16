<?php
$file = fopen("tco.txt","r");
$urls = array();
while(!feof($file)){
	$urls[] = fgets($file);	
}
shuffle($urls);
fclose($file);
$file=fopen("tco_random.txt","w+");
for($i=0;$i<count($urls);$i++)
{
	fputs($file,$urls[$i]);
}
fclose($file);
?>