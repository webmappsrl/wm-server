<?php

class WebmappMptravelWebmappCategoryKTask extends WebmappAbstractTask
{
    private $__kBaseUrl = "/var/www/html/k.webmapp.it/mptravel/";
    private $__webmappCategoryEndpoint = 'taxonomies/webmapp_category.json';
    private $__apiBaseUrl = 'https://montepisano.travel/wp-json/wp/v2/webmapp_category/';
    private $__taxonomies = array(
        49 => 301, //Rete montepisano
        257 => 298, //Dove dormire
        259 => 297, //Agriturismo
        266 => 326, //Cantine
        267 => 299, // Cibo e prodotti locali
        269 => 308, //Ville e monumenti storici
        270 => 322, //Ristoranti
        271 => 306, //Serivizi
        272 => 325, //Agenzie di viaggio
        273 => 304, //B&B
        274 => 335, //Liquori e distillati
        275 => 327, //Pizzerie
        276 => 317, //Musei teatri e aree culturali
        278 => 324, //Miele
        280 => 321, //Hostel
        281 => 312, //Residence
        282 => 316, //Guide turistiche
        286 => 425, //Olio extra vergine di oliva
        288 => 311, //Piscine
        289 => 307, //Associazione
        290 => 305, //Guide ambientali
        365 => 421, //Chiese
        369 => 370, //Borghi e paesi
        373 => 420, //Aree protette e natura
        381 => 422, //Fortezze e torri
        423 => 424, //Punti di interesse
    );

    public function check()
    {
        echo "Checking file presence...";

        if (!file_exists($this->__kBaseUrl . $this->__webmappCategoryEndpoint)) {
            throw new WebmappExceptionNoFile("ERROR: Missing file " . $this->__kBaseUrl . $this->__webmappCategoryEndpoint, 1);
        }

        echo "Check OK\n";

        return true;
    }

    public function process()
    {
        $webmappCategoryJson = json_decode(file_get_contents($this->__kBaseUrl . $this->__webmappCategoryEndpoint), true);
        $resultJson = array();

        foreach ($webmappCategoryJson as $wc) {
            $translated = WebmappUtils::getJsonFromApi($this->__apiBaseUrl . $this->__taxonomies[$wc['id']]);
            $translation = array();
            $translation['name'] = $translated['name'];
            $translation['title'] = $translated['title'];
            $translation['description'] = $translated['description'];
            $newWc = $wc;
            $newWc['translations'] = array();
            $newWc['translations']['en'] = $translation;
            $resultJson[$wc['id']] = $newWc;
        }

        file_put_contents($this->__kBaseUrl . $this->__webmappCategoryEndpoint, json_encode($resultJson));

        return true;
    }
}
