<?php

date_default_timezone_set('Etc/UTC');
// VENDOR
//require 'vendor/gisconverter/gisconverter.php';
require 'vendor/GPXIngest/GPXIngest.php';
require_once 'vendor/gisconverter/vendor/autoload.php';
require 'vendor/PHPMailer-master/PHPMailerAutoload.php';

// Configuration file
$conf = __DIR__ . '/config.json';
if (!file_exists($conf)) {
    throw new Exception("Impossibile eseguire il server: manca il file di configurazione $conf", 1);
}
$wm_config = json_decode(file_get_contents($conf), true);

// Caricamento classi obbligatorie
require 'classes/utils/WebmappExceptions.php';
require 'classes/utils/WebmappUtils.php';
require 'classes/utils/WebmappPostGis.php';
require 'classes/utils/WebmappPostGisOSM.php';
require 'classes/utils/WebmappGeoJson.php';

// OSM
require 'classes/osm/WebmappOSMFeature.php';
require 'classes/osm/WebmappOSMRelation.php';
require 'classes/osm/WebmappOSMSuperRelation.php';

require 'classes/task/WebmappProject.php';
require 'classes/task/WebmappProjectStructure.php';

require 'classes/cli/WebmappCli.php';
require 'classes/cli/WebmappCliCommand.php';
require 'classes/cli/WebmappCliWebcacheCommand.php';

// TASK
require 'classes/task/WebmappAbstractTask.php';
require 'classes/task/WebmappBETask.php';
require 'classes/task/WebmappWPTask.php';
require 'classes/task/WebmappATask.php';
require 'classes/task/WebmappItemListTask.php';
require 'classes/task/WebmappWebappElbrusTask.php';
require 'classes/task/WebmappSingleMapTask.php';
require 'classes/task/WebmappMergeTaxonomiesTask.php';
require 'classes/task/WebmappSingleTask.php';
require 'classes/task/WebmappRouteTask.php';
require 'classes/task/WebmappCustomConfigTask.php';
require 'classes/task/WebmappTranslateTask.php';
require 'classes/task/WebmappAddTermNameToFeaturesTask.php';
require 'classes/task/WebmappTaskFactory.php';
require 'classes/task/WebmappOverpassQueryTask.php';

// TASK K
require 'classes/task/WebmappAllRoutesTask.php';
require 'classes/task/WebmappKTracksTask.php';

// Features
require 'classes/features/WebmappAbstractFeature.php';
require 'classes/features/WebmappPoiFeature.php';
require 'classes/features/WebmappTrackFeature.php';
require 'classes/features/WebmappLayer.php';
require 'classes/features/WebmappRoute.php';

require 'classes/map/WebmappMap.php';

require 'classes/utils/WebmappWP.php';

// CAIOSM
require 'classes/caiosm/WebmappOCListTask.php';
require 'classes/caiosm/WebmappOSMCAI.php';
require 'classes/caiosm/WebmappOSMCAI2.php';
require 'classes/caiosm/WebmappOSMListTask.php';
require 'classes/caiosm/WebmappOSMCAIRelationsTask.php';

// CUSTOM
require 'classes/custom/WebmappTrentinoATask.php';
require 'classes/custom/WebmappTrentinoAGalleryTask.php';
require 'classes/custom/WebmappVetrinaToscanaTask.php';
require 'classes/custom/WebmappPranzosanofuoricasaTask.php';
require 'classes/custom/WebmappDrupalTask.php';
require 'classes/custom/WebmappPNABAlgorabTask.php';
require 'classes/custom/WebmappAlaTask.php';
require 'classes/custom/WebmappEmpTask.php';
require 'classes/custom/WebmappPfEventsTask.php';
require 'classes/custom/WebmappTDPTask.php';
require 'classes/custom/WebmappMaratonaDiPisaTask.php';
require 'classes/custom/WebmappFSTTask.php';
require 'classes/custom/WebmappTrentinoKTask.php';
require 'classes/custom/WebmappSITRTTask.php';
require 'classes/custom/WebmappCovidTask.php';
require 'classes/custom/WebmappCovidRTTask.php';
require 'classes/custom/WebmappCovidPisaTask.php';
require 'classes/custom/WebmappIntense2ExportTask.php';
require 'classes/custom/WebmappMptravelWebmappCategoryKTask.php';

// SIMAP
require 'classes/simap/WebmappSIMapCheckTask.php';
require 'classes/simap/WebmappSIMapFindGhostTask.php';
require 'classes/simap/WebmappSIMapCSVTask.php';

// to remove (once simap2 is concluded)
require 'classes/custom/WebmappSIMapTask.php';
require 'classes/custom/WebmappSIMapStatsTask.php';
