<?php 

// MAIN
class WebmappException extends Exception {}

// COnfigurazione
class WebmappExceptionConf extends WebmappException {}
class WebmappExceptionConfPostgis extends WebmappException {}

  // WebmappException Eccezioni usate da tutte le classi
class WebmappExceptionNoFile extends WebmappException {}
class WebmappExceptionNoDirectory extends WebmappException {}
class WebmappExceptionNoOSMRelation extends WebmappException {}
class WebmappExceptionNoOSMFeature extends WebmappException {}
class WebmappExceptionPOINoCoodinates extends WebmappException {}

// Parameters
class WebmappExceptionParameter extends WebmappException {}
class WebmappExceptionParameterMandatory extends WebmappException {}
class WebmappExceptionParameterError extends WebmappException {}

// POSTGIG
class WebmappExceptionPostgis extends WebmappException {}
class WebmappExceptionPostgisEmptySelect extends WebmappException {}

// GEOJSON
class WebmappExceptionGeoJson extends WebmappException {}
class WebmappExceptionGeoJsonBadGeomType extends WebmappException {}

// LOG FILE
class WebmappExceptionLog extends WebmappException {}
class WebmappExceptionLogPathNotExist extends WebmappExceptionLog {}
class WebmappExceptionLogPathIsNotWritable extends WebmappExceptionLog {}

?>