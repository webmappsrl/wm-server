<?php

// VENDOR
require 'vendor/gisconverter/gisconverter.php';
require 'vendor/GPXIngest/GPXIngest.php';

// Configuration file
$conf=__DIR__.'/config.json';
if(!file_exists($conf)) {
	throw new Exception("Impossibile eseguire il server: manca il file di configurazione $conf", 1);	
}
$wm_config = json_decode(file_get_contents($conf),TRUE);

// Caricamento classi obbligatorie
require 'classes/utils/WebmappExceptions.php';
require 'classes/utils/WebmappUtils.php';
require 'classes/utils/WebmappGeoJson.php';
require 'classes/utils/WebmappOSMRelation.php';

require 'classes/task/WebmappProject.php';
require 'classes/task/WebmappProjectStructure.php';

require 'classes/cli/WebmappCli.php';
require 'classes/cli/WebmappCliCommand.php';

require 'classes/task/WebmappAbstractTask.php';
require 'classes/task/WebmappBETask.php';
require 'classes/task/WebmappRouteTask.php';
require 'classes/task/WebmappCustomConfigTask.php';
require 'classes/task/WebmappTaskFactory.php';

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
require 'classes/caiosm/WebmappOSMListTask.php';
require 'classes/caiosm/WebmappOSMCAIRelationsTask.php';

// CUSTOM
require 'classes/custom/WebmappVetrinaToscanaTask.php';
require 'classes/custom/WebmappDrupalTask.php';
require 'classes/custom/WebmappPNABAlgorabTask.php';
