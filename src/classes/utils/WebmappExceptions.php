<?php

// MAIN
class WebmappException extends Exception
{
}

class WebmappExceptionFatalError extends WebmappException
{
}

// Configurazione
class WebmappExceptionConf extends WebmappException
{
}

class WebmappExceptionConfPostgis extends WebmappException
{
}

class WebmappExceptionConfEndpoint extends WebmappException
{
}

class WebmappExceptionConfTask extends WebmappException
{
}

class WebmappExceptionConfCrypt extends WebmappException
{
}

// TASK
class WebmappExceptionTask extends WebmappException
{
}

class WebmappExceptionAllRoutesTask extends WebmappExceptionTask
{
}

class WebmappExceptionAllRoutesTaskNoEndpoint extends WebmappExceptionAllRoutesTask
{
}

class WebmappExceptionAllRoutesTaskNoRouteIndex extends WebmappExceptionAllRoutesTask
{
}

// WebmappException Eccezioni usate da tutte le classi
class WebmappExceptionHttpRequest extends WebmappException
{
}

class WebmappExceptionHoquRequest extends WebmappException
{
}

class WebmappExceptionNoFile extends WebmappException
{
}

class WebmappExceptionNoDirectory extends WebmappException
{
}

class WebmappExceptionNoOSMRelation extends WebmappException
{
}

class WebmappExceptionNoOSMFeature extends WebmappException
{
}

class WebmappExceptionPOINoCoodinates extends WebmappException
{
}

class WebmappExceptionNoFeature extends WebmappException
{
}

// Parameters
class WebmappExceptionParameter extends WebmappException
{
}

class WebmappExceptionParameterMandatory extends WebmappException
{
}

class WebmappExceptionParameterError extends WebmappException
{
}

// POSTGIG
class WebmappExceptionPostgis extends WebmappException
{
}

class WebmappExceptionPostgisEmptySelect extends WebmappException
{
}

class WebmappExceptionPostgisNoFeature extends WebmappException
{
}

// GEOJSON
class WebmappExceptionGeoJson extends WebmappException
{
}

class WebmappExceptionGeoJsonBadGeomType extends WebmappException
{
}

// Features
class WebmappExceptionFeatures extends WebmappException
{
}

class WebmappExceptionFeaturesNoGeometry extends WebmappExceptionFeatures
{
}

class WebmappExceptionFeatureStillExists extends WebmappException
{
}

class WebmappExceptionTaxonomyStillExists extends WebmappException
{
}

// TRACKS
class WebmappExceptionsFeaturesTracks extends WebmappExceptionFeatures
{
}

class WebmappExceptionsFeaturesTracksRelatedPoisBadWPURL extends WebmappExceptionsFeaturesTracks
{
}

class WebmappExceptionsFeaturesTracksRelatedPoisBadSource extends WebmappExceptionsFeaturesTracks
{
}

class WebmappExceptionsFeaturesTracksRelatedPoisNoWPNOSource extends WebmappExceptionsFeaturesTracks
{
}

?>