<?php

$html = <<<EOF
<!DOCTYPE html>
<html>
<head>
<title>$title</title>
<link href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css" rel="stylesheet" type="text/css">
<style>
table {
    font-family: arial, sans-serif;
    border-collapse: collapse;
    width: 100%;
}

td, th {
    border: 1px solid #dddddd;
    text-align: left;
    padding: 8px;
}

tr:nth-child(even) {
    background-color: #dddddd;
}
td.red {
    background-color: #FF0000;
}
</style>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#catasto').DataTable();
} );
</script>
</head>
<body>

<h1>$title</h1>

<p>
  <a href="$zip">Download all GPX (created by WMT)</a>
  <a href="$shp">Download SHP file</a>
  <a href="sezioni.html">Sezioni</a>
</p>

<table id="catasto" >
$thead
<tbody>
 $rows
</tbody>

</table>

<div id="footer">
<p>
  Questa pagina web &egrave; stata realizzata utilizzando i dati di <a href="http://openstreetmap.org">Openstreetmap</a>.<br />
  I metadati utilizzati sono tag di OSM definiti nella <a href="http://wiki.openstreetmap.org/wiki/CAI">convenzione</a> tra OSM e il Club Alpino Italiano.<br />
  Il software &egrave; stato realizzato da <a href="mailto:alessiopiccioli@webmapp.it">Alessio Piccioli</a> di <a href="http://webmapp.it">Webmapp s.r.l.</a>. 
</p>
</div>

</body>
</html>

EOF;
