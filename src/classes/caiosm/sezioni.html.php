<?php // mapIndex.html.php

$block = "";
foreach($this->sezioni_counter as $sezione => $counter) {
	$tot = $counter['tot'];
	$osm = $counter['osm'];
	$perc = number_format($osm/$tot*100, 2, '.', '');
	$link = 'resources/' . WebmappUtils::slugify($sezione) . '.html';
   $block .= "<tr>
                <td>$sezione</td>
                <td>$tot</td>
                <td>$osm</td>
                <td>$perc</td>
                <td><a href=\"$link\">Dettagli</a></td>
              </tr>\n";
}

$html = <<<EOS

<!DOCTYPE html>
<html>
    <head>

        <title>Sezioni CAI Toscana</title>
   <style>
#sezioni {
    font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
    border-collapse: collapse;
    width: 100%;
}

#sezioni td, #customers th {
    border: 1px solid #ddd;
    padding: 8px;
}

#sezioni tr:nth-child(even){background-color: #f2f2f2;}

#sezioni tr:hover {background-color: #ddd;}

#sezioni th {
    padding-top: 12px;
    padding-bottom: 12px;
    text-align: center;
    background-color: #4CAF50;
    color: white;
}
</style>

    </head>

    <body>
    <h1>Sentieristica - Sezioni CAI Toscana</h1>
 	<table id="sezioni">

 	<tr>
 	 <th>Sezione</th>
 	 <th>Totale</th>
 	 <th>OSM</th>
 	 <th>Perc.</th>
 	 <th>Dettagli</th>
 	</tr>

 	$block

 	</table>

    </body>

</html>

EOS;

