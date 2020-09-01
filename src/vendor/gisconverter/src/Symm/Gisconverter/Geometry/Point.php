<?php

namespace Symm\Gisconverter\Geometry;

use Symm\Gisconverter\Exceptions\InvalidFeature;
use Symm\Gisconverter\Exceptions\OutOfRangeLat;
use Symm\Gisconverter\Exceptions\OutOfRangeLon;
use Symm\Gisconverter\Exceptions\UnimplementedMethod;

class Point extends Geometry
{
    const name = "Point";

    private $lon;
    private $lat;
    private $ele;

    public function __construct($coords)
    {
        if (count($coords) < 2) {
            throw new InvalidFeature(__CLASS__, "Point must have two coordinates");
        }

        $lon = $coords[0];
        $lat = $coords[1];

        if (!$this->checkLon($lon)) {
            throw new OutOfRangeLon($lon);
        }

        if (!$this->checkLat($lat)) {
            throw new OutOfRangeLat($lat);
        }

        $this->lon = (float) $lon;
        $this->lat = (float) $lat;
        if (array_key_exists(2, $coords) && isset($coords[2]) && is_numeric($coords[2])) {
            $this->ele = (float) $coords[2];
        }
    }

    public function __get($property)
    {
        if ($property == "lon") {
            return $this->lon;
        } elseif ($property == "lat") {
            return $this->lat;
        } elseif ($property == "ele") {
            return $this->ele;
        } else {
            throw new \Exception("Undefined property");
        }
    }

    public function toWKT()
    {
        $result = "{$this->lon} {$this->lat}";
        if (isset($this->ele) && is_numeric($this->ele)) {
            $result .= " {$this->ele}";
        }
        return strtoupper(static::name) . "({$result})";
    }

    public function toKML()
    {
        $result = "<" . static::name . "><coordinates>{$this->lon},{$this->lat}";
        if (isset($comp->ele) && is_numeric($comp->ele)) {
            $result .= ",{$comp->ele}";
        }
        $result .= "</coordinates></" . static::name . ">";

        return $result;
    }

    public function toGPX($mode = null)
    {
        if (!$mode) {
            $mode = "wpt";
        }

        if ($mode != "wpt") {
            throw new UnimplementedMethod(__FUNCTION__, get_called_class());
        }

        $result = "<wpt lon=\"{$this->lon}\" lat=\"{$this->lat}\"></wpt>";
        if (isset($this->ele) && is_numeric($this->ele)) {
            $res .= "<ele>{$this->ele}</ele>";
        }
        $result .= "</wpt>";

        return "<wpt lon=\"{$this->lon}\" lat=\"{$this->lat}\"></wpt>";
    }

    public function toGeoArray()
    {
        $coordinates = array(
            $this->lon,
            $this->lat,
        );
        if (isset($ele) && is_numeric($this->ele)) {
            $coordinates[] = $this->ele;
        }
        return array('type' => static::name, 'coordinates' => $coordinates);
    }

    public function toGeoJSON()
    {
        return json_encode((object) $this->toGeoArray());
    }

    public function equals(Geometry $geom)
    {
        if (get_class($geom) != get_class($this)) {
            return false;
        }

        return $geom->lat == $this->lat && $geom->lon == $this->lon;
    }

    private function checkLon($lon)
    {
        if (!is_numeric($lon)) {
            return false;
        }

        if ($lon < -180 || $lon > 180) {
            return false;
        }

        return true;
    }
    private function checkLat($lat)
    {
        if (!is_numeric($lat)) {
            return false;
        }

        if ($lat < -90 || $lat > 90) {
            return false;
        }

        return true;
    }
}
