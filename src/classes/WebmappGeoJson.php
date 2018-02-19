<?php 

class WebmappExceptionGeoJsonOddList extends Exception {}

class WebmappGeoJson {

	private $features = array();

	public function addLineString($coordinates,$properties=array()) {
		//TODO: controlli del parametro
		$f=array();
		$f['properties']=array('empty'=>'yes');
		$f['type']='Feature';
		$f['geometry']['type']='LineString';
		$f['geometry']['coordinates']=$coordinates;
		$this->features[]=$f;
		return true;
	}
	/**
	* Convert parameter $list lat1,lon1,lat2,lon2,...,latN,lon2
	* to coordinates array(array(lon1,lat1),array(lon2,lat2),...,array(lonN,latN))
	* an then calls addLineString
    *
	**/
	public function addLineStringByList($list,$properties=array()) {
		$v = explode(',', $list);
		$nv = count($v);
		if ($nv==0) return false;
		if ($nv % 2 != 0) {
			throw new WebmappExceptionGeoJsonOddList("Number of parameter in list must be even: $nv is odd.", 1);
		}
		$coordinates=array();
		for ($i=0; $i < $nv/2; $i++) {
			$j=2*$i;
			$coordinates[]=array($v[$j+1],$v[$j]);
		}
		return $this->addLineString($coordinates,$properties);
	}

	/**
	{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": {},
      "geometry": {
        "type": "LineString",
        "coordinates": [
          [
            76.640625,
            58.63121664342478
          ],

	**/
	public function __toString() {
		$jg = array();
		$jg['type']='FeatureCollection';
		$jg['features']=$this->features;
		return json_encode($jg);
	}
}