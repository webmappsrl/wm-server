<?php
class WebmappCovidRTTask extends WebmappAbstractTask {

    /**
     * @var array codici ISTAT delle province della Toscana
     */
    private $province_toscana=array(
        '045',
        '046',
        '047',
        '048',
        '049',
        '050',
        '051',
        '052',
        '053',
        '100'
    );
    /**
     * @var string Input data (consegnato da RT)
     */
    private $rtdata='/resources/rtdata.xls';
	public function check() {

	    // Check data file
        if(!file_exists($this->rtdata)) {
            $msg = "No file data in {$this->rtdata}\n";
            throw new Exception($msg);
        }

		return TRUE;
	}

    /**
     * @return bool
     */
    public function process()
    {
        return TRUE;
    }
}
