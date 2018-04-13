<?php

$block = "";
foreach($this->items as $item) {
    if(trim($item['Sezione']==$sezione)) {
        $ref = $item['Num'];
        $da = $item['Da'];
        $a = $item['A'];
        $osmid = 'ND';
        $wmkt = $settore = $settore_id = 'ND';

        if(!empty($item['OSMID'])) {
            $osmid = $item['OSMID'] ;
            echo "Processing relation $osmid \n";
            $wmkt = "<a href=\"https://hiking.waymarkedtrails.org/#route?id=$osmid\" target=\"_blank\">[WMKT]</a>";
            $settore = WebmappUtils::getSettoriByOSMID($osmid);
            $settore_id = WebmappUtils::getSettoriIDByOSMID($osmid);
        }
        $block .= "<tr>
                    <td>$ref</td>
                    <td>$da</td>
                    <td>$a</td>
                    <td>$osmid</td>
                    <td>$wmkt</td>
                    <td>$settore</td>
                    <td>$settore_id</td>
                   </tr>";
    }
}

$html = <<<EOS

<!DOCTYPE html>
<html>
    <head>

        <title>Sezioni CAI Toscana - Sezione di $sezione</title>
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
    <h1>Sentieristica - CAI Toscana - Sezione di $sezione</h1>
    <p><a href="../sezioni.html">Torna a tutte le sezioni</a></p>
 	<table id="sezioni">

 	<tr>
     <th>Ref</th>
     <th>Da</th>
     <th>A</th>
     <th>OSMID</th>
     <th>Way Marked Trail</th>
     <th>Settore</th>
     <th>Settore ID</th>
 	</tr>

 	$block

 	</table>

    </body>

</html>

EOS;

