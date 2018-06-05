<?php

// VENDOR
require 'vendor/gisconverter/gisconverter.php';
require 'vendor/GPXIngest/GPXIngest.php';

// Caricamento classi obbligatorie

require 'classes/WebmappExceptions.php';
require 'classes/WebmappUtils.php';
require 'classes/WebmappGeoJson.php';

require 'classes/WebmappProject.php';
require 'classes/WebmappProjectStructure.php';


require 'classes/task/WebmappAbstractTask.php';
require 'classes/task/WebmappBETask.php';
require 'classes/task/WebmappRouteTask.php';
require 'classes/task/WebmappTaskFactory.php';

require 'classes/features/WebmappAbstractFeature.php';
require 'classes/features/WebmappPoiFeature.php';
require 'classes/features/WebmappTrackFeature.php';
require 'classes/features/WebmappLayer.php';
require 'classes/features/WebmappRoute.php';

require 'classes/map/WebmappMap.php';

require 'classes/WebmappWP.php';

// CAIOSM
require 'classes/caiosm/WebmappOSMCAI.php';
require 'classes/caiosm/WebmappOSMListTask.php';
require 'classes/caiosm/WebmappOSMCAIRelationsTask.php';

// CUSTOM
require 'classes/custom/WebmappVetrinaToscanaTask.php';
require 'classes/custom/WebmappDrupalTask.php';
require 'classes/custom/WebmappPNABAlgorabTask.php';
