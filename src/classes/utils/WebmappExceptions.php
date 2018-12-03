<?php 
// COnfigurazione
class WebmappExceptionConf extends Exception {}
class WebmappExceptionConfPostgis extends Exception {}

  // WebmappException Eccezioni usate da tutte le classi
class WebmappExceptionNoFile extends Exception {}
class WebmappExceptionNoDirectory extends Exception {}
class WebmappExceptionNoOSMRelation extends Exception {}
class WebmappExceptionNoOSMFeature extends Exception {}
class WebmappExceptionPOINoCoodinates extends Exception {}

// Parameters
class WebmappExceptionParameter extends Exception {}
class WebmappExceptionParameterMandatory extends Exception {}
class WebmappExceptionParameterError extends Exception {}

// POSTGIG
class WebmappExceptionPostgis extends Exception {}
class WebmappExceptionPostgisEmptySelect extends Exception {}

?>