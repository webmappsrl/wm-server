<?php // WebmappOSM

// Esempi di feature (API / XML)
//
// SUPERRELATION (SR): https://www.openstreetmap.org/api/0.6/relation/1021025
// <relation id="1021025" visible="true" version="57" changeset="61232947" timestamp="2018-07-31T14:58:22Z" user="Gianfranco2014" uid="1928626">
//
// RELATION (R): https://www.openstreetmap.org/api/0.6/relation/7454121 , https://www.openstreetmap.org/api/0.6/relation/7454121/full
// <relation id="7454121" visible="true" version="5" changeset="61232947" timestamp="2018-07-31T15:10:03Z" user="Gianfranco2014" uid="1928626">
//
// WAY (W): https://www.openstreetmap.org/api/0.6/way/167059866 , https://www.openstreetmap.org/api/0.6/way/167059866/full  
// <way id="167059866" visible="true" version="3" changeset="29333411" timestamp="2015-03-08T16:18:31Z" user="arcanma" uid="1211510">
//
// NODE (N): https://www.openstreetmap.org/api/0.6/node/1486233694 (senza TAGS) 
// <node id="1486233694" visible="true" version="2" changeset="14555749" timestamp="2013-01-06T21:03:25Z" user="Eraclitus" uid="196103" lat="43.1358956" lon="12.8297080"/>
//
// NODE (N): https://www.openstreetmap.org/api/0.6/node/1950330571 (con TAGS)
// <node id="1950330571" visible="true" version="4" changeset="20582530" timestamp="2014-02-15T18:27:12Z" user="dforsi" uid="24126" lat="40.0146760" lon="9.2313480">

abstract class WebmappOSMFeature {
    protected $id;
    protected $url;
    protected $xml;
    protected $feature;
    protected $base_url;
    protected $properties = array();
    protected $tags = array();
    protected $members = array();

    public function __construct($id) {
        declare(ticks = 1);
        $this->base_url = 'https://www.openstreetmap.org/api/0.6/';
        $this->id = $id;
        $this->init();
        // This lines prevents get_headers errors with ssl
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $h = get_headers($this->url, 0, $context);
        if (!is_array($h) || !preg_match('/200/', $h[0])) {
            throw new WebmappExceptionNoOSMFeature("Error: can't load " . $this->url, 1);
        }
        $this->xml = WebmappUtils::getXMLFromUrl($this->url);
        $this->setFeature();
        $this->extractProperties();
        $this->extractTags();
        $this->setMembers();
    }

    abstract protected function init();

    abstract protected function setFeature();

    // valid only for Relation and SuperRelation
    private function setMembers() {
        if (isset($this->feature->member)) {
            foreach ($this->feature->member as $member) {
                $ref = $member['ref']->__toString();
                $this->members[$ref] =
                    array(
                        "ref" => $ref,
                        "type" => $member['type'],
                        "role" => $member['role']
                    );
            }
        }
    }

    public function getUrl() {
        return $this->url;
    }

    public function getProperty($k) {
        return $this->properties[$k];
    }

    public function getProperties() {
        return $this->properties;
    }

    public function hasTag($k) {
        return array_key_exists($k, $this->tags);
    }

    public function getTag($k) {
        return $this->tags[$k];
    }

    public function getTags() {
        return $this->tags;
    }

    public function getMembers() {
        return $this->members;
    }

    private function extractProperties() {
        $this->properties['id'] = $this->feature['id']->__toString();
        $this->properties['visible'] = $this->feature['visible']->__toString();
        $this->properties['version'] = $this->feature['version']->__toString();
        $this->properties['changeset'] = $this->feature['changeset']->__toString();
        $this->properties['timestamp'] = $this->feature['timestamp']->__toString();
        $this->properties['user'] = $this->feature['user']->__toString();
        $this->properties['uid'] = $this->feature['uid']->__toString();
    }

    private function extractTags() {
        if (isset($this->feature->tag)) {
            foreach ($this->feature->tag as $tag) {
                $k = $tag['k']->__toString();
                $v = $tag['v']->__toString();
                $this->tags[$k] = $v;
            }
        }
    }
}

// TODO: spostare in singoli file quando si implementano

class WebmappOSMWay extends WebmappOSMFeature {
    protected function init() {
        $this->url = $this->base_url . 'way/' . $this->id;
    }

    protected function setFeature() {
        $this->feature = $this->xml->way;
    }
}

class WebmappOSMNode extends WebmappOSMFeature {
    protected function init() {
        $this->url = $this->base_url . 'node/' . $this->id;
    }

    protected function setFeature() {
        $this->feature = $this->xml->node;
    }
}
