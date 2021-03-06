<?php

namespace Symm\Gisconverter\Geometry;

use Symm\Gisconverter\Exceptions\InvalidFeature;
use Symm\Gisconverter\Exceptions\UnimplementedMethod;

class LineString extends MultiPoint
{
    const name = "LineString";

    public function __construct($components)
    {
        if (count($components) < 2) {
            throw new InvalidFeature(__CLASS__, "LineString must have at least 2 points");
        }

        parent::__construct($components);
    }

    public function toKML()
    {
        return "<" . static::name . ">" .
        "<coordinates>" .
        implode(
            " ",
            array_map(
                function ($comp) {
                    $result = "{$comp->lon},{$comp->lat}";
                    $ele = $comp->ele;
                    if (isset($ele) && is_numeric($ele)) {
                        $result .= ",{$ele}";
                    }
                    return $result;
                },
                $this->components
            )
        ) .
        "</coordinates>" .
        "</" . static::name . ">";
    }

    public function toGPX($mode = null)
    {
        if (!$mode) {
            $mode = "trkseg";
        }

        if ($mode != "trkseg" and $mode != "rte") {
            throw new UnimplementedMethod(__FUNCTION__, get_called_class());
        }

        if ($mode == "trkseg") {
            return '<trkseg>' .
            implode(
                "",
                array_map(
                    function ($comp) {
                        $res = "<trkpt lon=\"{$comp->lon}\" lat=\"{$comp->lat}\">";
                        $ele = $comp->ele;
                        if (isset($ele) && is_numeric($ele)) {
                            $res .= "<ele>{$ele}</ele>";
                        }
                        $res .= "</trkpt>";
                        return $res;
                    },
                    $this->components
                )
            ) .
                "</trkseg>";
        } else {
            return '<rte>' .
            implode(
                "",
                array_map(
                    function ($comp) {
                        $res = "<rtept lon=\"{$comp->lon}\" lat=\"{$comp->lat}\">";
                        $ele = $comp->ele;
                        if (isset($ele) && is_numeric($ele)) {
                            $res .= "<ele>{$ele}</ele>";
                        }
                        $res .= "</rtept>";
                        return $res;
                    },
                    $this->components
                )
            ) .
                "</rte>";
        }
    }
}
