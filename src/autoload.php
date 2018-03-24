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


require 'classes/WebmappAbstractTask.php';
require 'classes/WebmappBETask.php';
require 'classes/WebmappDrupalTask.php';
require 'classes/WebmappRouteTask.php';
require 'classes/WebmappTaskFactory.php';

require 'classes/WebmappAbstractFeature.php';
require 'classes/WebmappPoiFeature.php';
require 'classes/WebmappTrackFeature.php';
require 'classes/WebmappLayer.php';
require 'classes/WebmappRoute.php';

require 'classes/WebmappMap.php';

require 'classes/WebmappWP.php';

// CAIOSM
require 'classes/caiosm/WebmappOSMListTask.php';
require 'classes/caiosm/WebmappOSMCAIRelationsTask.php';

// CUSTOM
require 'classes/custom/WebmappVetrinaToscanaTask.php';