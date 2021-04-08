<?php

use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;

define("VOICES", [
    'de' => [
        'languageCode' => 'de-DE',
        'ssmlGender' => 'FEMALE',
        'name' => 'de-DE-Wavenet-C'
    ],
    'en' => [
        'languageCode' => 'en-GB',
        'ssmlGender' => 'FEMALE',
        'name' => 'en-GB-Wavenet-C'
    ],
    'es' => [
        'languageCode' => 'es-ES',
        'ssmlGender' => 'MALE',
        'name' => 'es-ES-Wavenet-B'
    ],
    'fr' => [
        'languageCode' => 'fr-FR',
        'ssmlGender' => 'FEMALE',
        'name' => 'fr-FR-Wavenet-C'
    ],
    'it' => [
        'languageCode' => 'it-IT',
        'ssmlGender' => 'MALE',
        'name' => 'it-IT-Wavenet-C'
    ],
    'nb' => [
        'languageCode' => 'nb-NO',
        'ssmlGender' => 'FEMALE',
        'name' => 'nb-NO-Wavenet-C'
    ],
    'nl' => [
        'languageCode' => 'nl-NL',
        'ssmlGender' => 'MALE',
        'name' => 'nl-NL-Wavenet-B'
    ]
]);

class WebmappGenerateAudioJob extends WebmappAbstractJob {
    private string $ttsKey;

    /**
     * WebmappGenerateMbtilesJob constructor.
     *
     * @param string $instanceUrl containing the instance url
     * @param string $params      containing an encoded JSON with the poi ID
     * @param bool   $verbose
     *
     * @throws WebmappExceptionNoDirectory
     * @throws WebmappExceptionParameterError
     * @throws WebmappExceptionParameterMandatory
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false) {
        parent::__construct("generate_audio", $instanceUrl, $params, $verbose);
        global $wm_config;
        if (isset($wm_config["google_tts_key"]))
            $this->ttsKey = $wm_config["google_tts_key"];
        else
            throw new WebmappExceptionParameterMandatory("Undefined config property 'google_tts_key'. Audio file can not be generated");
    }

    /**
     * @throws ApiException
     * @throws ValidationException
     * @throws WebmappExceptionNoFile
     * @throws WebmappExceptionParameterError
     */
    protected function process() {
        $lang = isset($this->params['lang']) ? substr($this->params['lang'], 0, 2) : null;
        $geojsonUrl = "{$this->aProject->getRoot()}/geojson/{$this->id}.geojson";
        if (file_exists($geojsonUrl)) {
            $this->_verbose("Getting the audio text from {$geojsonUrl}");
            $this->_lockFile($geojsonUrl);
            $geojson = file_get_contents($geojsonUrl);
            $geojson = json_decode($geojson, true);
            $text = null;
            if (is_null($lang)) {
                $this->_verbose("Language parameter not defined. Using default description");
                $text = isset($geojson['properties']['description']) ? $geojson['properties']['description'] : null;
                if (isset($geojson['properties']['locale']) &&
                    isset(VOICES[substr($geojson['properties']['locale'], 0, 2)])) {
                    $lang = substr($geojson['properties']['locale'], 0, 2);
                    $this->_verbose("Using language {$lang}");
                } else
                    throw new WebmappExceptionParameterError("The language could not be determined. Aborting audio generation");
            } else {
                $this->_verbose("Language set to {$lang}");
                if (isset($geojson['properties']['locale']) &&
                    substr($geojson['properties']['locale'], 0, 2) === $lang &&
                    isset($geojson['properties']['description']))
                    $text = $geojson['properties']['description'];

                if (is_null($text) && isset($geojson['properties']['translations'][$lang]['description']))
                    $text = $geojson['properties']['translations'][$lang]['description'];
            }

            if (is_null($text)) {
                $this->_unlockFile($geojsonUrl);
                $this->_verbose('The audio does not need to be generated since there is no description to be read');
            } else {
                $this->_verbose('Stripping html tags from text');
                $text = strip_tags($text);
                $audioUrl = $this->_generateAudioFile($text, $lang);

                if (isset($geojson['properties']['translations'][$lang]))
                    $geojson['properties']['translations'][$lang]['audio'] = $audioUrl;
                if (isset($geojson['properties']['locale']) &&
                    substr($geojson['properties']['locale'], 0, 2) === $lang)
                    $geojson['properties']['audio'] = $audioUrl;

                $this->_verbose("Adding audio property to the geojson file");
                file_put_contents($geojsonUrl, json_encode($geojson));

                $this->_unlockFile($geojsonUrl);
            }
        } else throw new WebmappExceptionNoFile("The audio for the feature {$this->id} can not be generated since the geojson file does not exists");
    }

    /**
     * Generate a mp3 file containing the given text with a given lang. Return the url of the created file
     *
     * @param $text
     * @param $lang
     *
     * @return string
     *
     * @throws ApiException
     * @throws ValidationException
     */
    private function _generateAudioFile($text, $lang): string {
        $this->_verbose("Generating audio file using Google TTS API");
        $textToSpeechClient = new TextToSpeechClient([
            'projectId' => 'concise-torus-297209',
            'credentials' => json_decode(file_get_contents($this->ttsKey), true)
        ]);

        $input = new SynthesisInput();
        $input->setText($text);
        $voice = new VoiceSelectionParams();
        $voice->setLanguageCode(VOICES[$lang]['languageCode']);
        $voice->setSsmlGender(constant('\Google\Cloud\TextToSpeech\V1\SsmlVoiceGender::' . VOICES[$lang]['ssmlGender']));
        $voice->setName(VOICES[$lang]['name']);
        $audioConfig = new AudioConfig();
        $audioConfig->setAudioEncoding(AudioEncoding::MP3);
        $audioConfig->setSpeakingRate(0.8);
        $audioConfig->setPitch(0);

        $resp = $textToSpeechClient->synthesizeSpeech($input, $voice, $audioConfig);

        if (!file_exists($this->aProject->getRoot() . "/media/audios/"))
            mkdir($this->aProject->getRoot() . "/media/audios/", 0777, true);

        $audioUrl = $this->aProject->getRoot() . "/media/audios/{$this->id}_{$lang}.mp3";
        file_put_contents($audioUrl, $resp->getAudioContent());

        $this->_verbose("Audio file generated successfully in {$audioUrl}");

        return $audioUrl;
    }
}
