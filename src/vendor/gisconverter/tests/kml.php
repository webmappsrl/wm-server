<?php

class kml extends PHPUnit_Framework_TestCase
{
    private $decoder = null;

    public function setup()
    {
        if (!$this->decoder) {
            $this->decoder = new Symm\Gisconverter\Decoders\KML();
        }
    }

    /**
     * @expectedException Symm\Gisconverter\Exceptions\InvalidText
     */
    public function testInvalidText1()
    {
        $this->decoder->geomFromText('<Crap></Crap>');
    }

    /**
     * @expectedException Symm\Gisconverter\Exceptions\InvalidText
     */
    public function testInvalidText2()
    {
        $this->decoder->geomFromText('<Point><coordinates>10, 10<coordinates></Point>');
    }

    public function testPoint()
    {
        $geom = $this->decoder->geomFromText('<Point><coordinates>10,10</coordinates></Point>');
        $this->assertEquals($geom->toKML(), '<Point><coordinates>10,10</coordinates></Point>');

        $geom = $this->decoder->geomFromText('  <Point>  <coordinates>10,  10 </coordinates></Point> ');
        $this->assertEquals($geom->toKML(), '<Point><coordinates>10,10</coordinates></Point>');

        $geom = $this->decoder->geomFromText('<Point><coordinates>0,0</coordinates></Point>');
        $this->assertEquals($geom->toKML(), '<Point><coordinates>0,0</coordinates></Point>');

        $geom = $this->decoder->geomFromText('<Point><coordinates>10,10</coordinates><crap>some stuff</crap></Point>');
        $this->assertEquals($geom->toKML(), '<Point><coordinates>10,10</coordinates></Point>');
    }

    /**
     * @expectedException Symm\Gisconverter\Exceptions\InvalidText
     */
    public function testInvalidPoint1()
    {
        $this->decoder->geomFromText('<Point>10, 10</Point>');
    }

    /**
     * @expectedException Symm\Gisconverter\Exceptions\InvalidText
     */
    public function testInvalidPoint2()
    {
        $this->decoder->geomFromText('<Point><coordinates>10, 10</coordinates><coordinates>10, 10</coordinates></Point>');
    }

    public function testLineString()
    {
        $geom = $this->decoder->geomFromText('<LineString><coordinates>3.5,5.6 4.8,10.5 10,10</coordinates></LineString>');
        $this->assertEquals($geom->toKML(), '<LineString><coordinates>3.5,5.6 4.8,10.5 10,10</coordinates></LineString>');
    }

    public function testLinearRing()
    {
        $geom = $this->decoder->geomFromText('<LinearRing><coordinates>3.5,5.6 4.8,10.5 10,10 3.5,5.6</coordinates></LinearRing>');
        $this->assertEquals($geom->toKML(), '<LinearRing><coordinates>3.5,5.6 4.8,10.5 10,10 3.5,5.6</coordinates></LinearRing>');
    }

    public function testPolygon()
    {
        $geom = $this->decoder->geomFromText('<Polygon><outerBoundaryIs><LinearRing><coordinates>10,10 10,20 20,20 20,15 10,10</coordinates></LinearRing></outerBoundaryIs></Polygon>');
        $this->assertEquals($geom->toKML(), '<Polygon><outerBoundaryIs><LinearRing><coordinates>10,10 10,20 20,20 20,15 10,10</coordinates></LinearRing></outerBoundaryIs></Polygon>');

        $geom = $this->decoder->geomFromText('<Polygon><outerBoundaryIs><LinearRing><coordinates>0,0 10,0 10,10 0,10 0,0</coordinates></LinearRing></outerBoundaryIs><innerBoundaryIs><LinearRing><coordinates>1,1 9,1 9,9 1,9 1,1</coordinates></LinearRing></innerBoundaryIs></Polygon>');
        $this->assertEquals($geom->toKML(), '<Polygon><outerBoundaryIs><LinearRing><coordinates>0,0 10,0 10,10 0,10 0,0</coordinates></LinearRing></outerBoundaryIs><innerBoundaryIs><LinearRing><coordinates>1,1 9,1 9,9 1,9 1,1</coordinates></LinearRing></innerBoundaryIs></Polygon>');
    }

    /**
     * @expectedException Symm\Gisconverter\Exceptions\InvalidText
     */
    public function testInvalidPolygon()
    {
        $geom = $this->decoder->geomFromText('<Polygon><innerBoundaryIs><LinearRing><coordinates>1,1 9,1 9,9 1,9 1,1</coordinates></LinearRing></innerBoundaryIs></Polygon>');
    }

    public function testMultiPoint()
    {
        $geom = $this->decoder->geomFromText('<MultiGeometry><Point><coordinates>3.5,5.6</coordinates></Point><Point><coordinates>4.8,10.5</coordinates></Point><Point><coordinates>10,10</coordinates></Point></MultiGeometry>');
        $this->assertEquals($geom->toKML(), '<MultiGeometry><Point><coordinates>3.5,5.6</coordinates></Point><Point><coordinates>4.8,10.5</coordinates></Point><Point><coordinates>10,10</coordinates></Point></MultiGeometry>');

    }

    public function testEmptyMultiGeometry()
    {
        $geom = $this->decoder->geomFromText('<MultiGeometry></MultiGeometry>');
        $this->assertEquals($geom->toKML(), '<MultiGeometry></MultiGeometry>');
    }

    public function testMultiLineString()
    {
        $geom = $this->decoder->geomFromText('<MultiGeometry><LineString><coordinates>3.5,5.6 4.8,10.5 10,10</coordinates></LineString></MultiGeometry>');
        $this->assertEquals($geom->toKML(), '<MultiGeometry><LineString><coordinates>3.5,5.6 4.8,10.5 10,10</coordinates></LineString></MultiGeometry>');

        $geom = $this->decoder->geomFromText('<MultiGeometry><LineString><coordinates>3.5,5.6 4.8,10.5 10,10</coordinates></LineString><LineString><coordinates>10,10 10,20 20,20 20,15</coordinates></LineString></MultiGeometry>');
        $this->assertEquals($geom->toKML(), '<MultiGeometry><LineString><coordinates>3.5,5.6 4.8,10.5 10,10</coordinates></LineString><LineString><coordinates>10,10 10,20 20,20 20,15</coordinates></LineString></MultiGeometry>');
    }

    public function testMultiPolygon()
    {
        $geom = $this->decoder->geomFromText('<MultiGeometry><Polygon><outerBoundaryIs><LinearRing><coordinates>10,10 10,20 20,20 20,15 10,10</coordinates></LinearRing></outerBoundaryIs></Polygon></MultiGeometry>');
        $this->assertEquals($geom->toKML(), '<MultiGeometry><Polygon><outerBoundaryIs><LinearRing><coordinates>10,10 10,20 20,20 20,15 10,10</coordinates></LinearRing></outerBoundaryIs></Polygon></MultiGeometry>');

        $geom = $this->decoder->geomFromText('<MultiGeometry><Polygon><outerBoundaryIs><LinearRing><coordinates>10,10 10,20 20,20 20,15 10,10</coordinates></LinearRing></outerBoundaryIs></Polygon><Polygon><outerBoundaryIs><LinearRing><coordinates>60,60 70,70 80,60 60,60</coordinates></LinearRing></outerBoundaryIs></Polygon></MultiGeometry>');
        $this->assertEquals($geom->toKML(), '<MultiGeometry><Polygon><outerBoundaryIs><LinearRing><coordinates>10,10 10,20 20,20 20,15 10,10</coordinates></LinearRing></outerBoundaryIs></Polygon><Polygon><outerBoundaryIs><LinearRing><coordinates>60,60 70,70 80,60 60,60</coordinates></LinearRing></outerBoundaryIs></Polygon></MultiGeometry>');
    }

    public function testGeometryCollection()
    {
        $geom = $this->decoder->geomFromText('<MultiGeometry><Point><coordinates>10,10</coordinates></Point><Point><coordinates>30,30</coordinates></Point><LineString><coordinates>15,15 20,20</coordinates></LineString></MultiGeometry>');
        $this->assertEquals($geom->toKML(), '<MultiGeometry><Point><coordinates>10,10</coordinates></Point><Point><coordinates>30,30</coordinates></Point><LineString><coordinates>15,15 20,20</coordinates></LineString></MultiGeometry>');
    }

    public function testOldMarkupGeometryCollection()
    {
        $geom = $this->decoder->geomFromText('<GeometryCollection><Point><coordinates>-96.987409470201,26.795078729458</coordinates></Point><LineString><coordinates>-122.29667,37.81689 -122.29568,37.81778 -122.29498,37.81854 -122.2925,37.82164 -122.29159,37.82307 -122.29085,37.82461 -122.29073,37.82497 -122.29064,37.82556 -122.29067,37.826 -122.29075,37.82637 -122.29098,37.82692 -122.29147,37.82759 -122.29242,37.82844 -122.29285,37.82898</coordinates></LineString></GeometryCollection>');
        $this->assertEquals($geom->toKML(), '<MultiGeometry><Point><coordinates>-96.987409470201,26.795078729458</coordinates></Point><LineString><coordinates>-122.29667,37.81689 -122.29568,37.81778 -122.29498,37.81854 -122.2925,37.82164 -122.29159,37.82307 -122.29085,37.82461 -122.29073,37.82497 -122.29064,37.82556 -122.29067,37.826 -122.29075,37.82637 -122.29098,37.82692 -122.29147,37.82759 -122.29242,37.82844 -122.29285,37.82898</coordinates></LineString></MultiGeometry>');
    }

    /**
     * @expectedException gisconverter\Unimplemented
     */
    public function invalidConversion1()
    {
        $decoder = new gisconverter\WKT();
        $geom = $decoder->geomFromText('POINT(10 10)');
        $geom->toGPX('rte');
    }

    /**
     * @expectedException gisconverter\Unimplemented
     */
    public function invalidConversion2()
    {
        $decoder = new gisconverter\WKT();
        $geom = $decoder->geomFromText('MULTIPOINT(3.5 5.6,4.8 10.5,10 10)');
        $geom->toGPX();
    }

    /**
     * @expectedException gisconverter\Unimplemented
     */
    public function invalidConversion3()
    {
        $decoder = new gisconverter\WKT();
        $geom = $decoder->geomFromText('LINESTRING(3.5 5.6,4.8 10.5,10 10)');
        $geom->toGPX('wpt');
    }

    public function testFullDoc()
    {
        $geom = $this->decoder->geomFromText('<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Placemark><Point><coordinates>10,10</coordinates></Point></Placemark></kml>'); // <?php <-- vim syntax goes crazy
        $this->assertEquals($geom->toKML(), '<Point><coordinates>10,10</coordinates></Point>');
        $geom = $this->decoder->geomFromText('<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Point><coordinates>10,10</coordinates></Point></kml>'); // <?php <-- vim syntax goes crazy
        $this->assertEquals($geom->toKML(), '<Point><coordinates>10,10</coordinates></Point>');
        $geom = $this->decoder->geomFromText('<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document><Placemark><Point><coordinates>10,10</coordinates></Point></Placemark></Document></kml>'); // <?php <-- vim syntax goes crazy
        $this->assertEquals($geom->toKML(), '<Point><coordinates>10,10</coordinates></Point>');
    }

    public function testItParsesKmlCustomDataAttributes()
    {
        $decoder = $this->decoder;
        $kmltext = file_get_contents(__DIR__ . '/files/kml-extended-data.kml');
        $point   = $decoder->geomFromText($kmltext);

        $attributes = $point->getAttributes();

        $this->assertInstanceOf('Symm\Gisconverter\Geometry\Point', $point);
        $this->assertInternalType('array', $attributes);

        $this->assertEquals('ALLGOOD', $attributes['abernant']);
        $this->assertEquals('Alabama', $attributes['alabama']);
        $this->assertEquals('0.889', $attributes['Geocode Score']);
        $this->assertEquals('zip', $attributes['Geocode Precision']);

        $this->assertEquals('POINT(-86.508321 33.92621)', $point->toWKT());
    }
}