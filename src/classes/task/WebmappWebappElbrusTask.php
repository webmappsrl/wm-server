<?php
class WebmappWebappElbrusTask extends WebmappAbstractTask
{
    private $codebase_url = 'wm-webapp/';

    public function check()
    {
        if (!file_exists($this->project_structure->getRoot() . 'core.zip')) {
            throw new Exception("ERRORE: Core mancante in {$this->project_structure->getRoot()} . 'core.zip'", 1);
        }

        if (!array_key_exists('codes', $this->options)) {
            throw new Exception("'codes' option is mandatory", 1);
        }

        return true;
    }

    public function process()
    {
        return true;
    }
}
