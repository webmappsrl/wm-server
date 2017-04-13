
angular.module('webmapp').constant('GENERAL_CONFIG', {
    VERSION: '0.4', // TODO: add clear localStorage if VERSION !==

    OPTIONS: {
        title: 'TEST ALL',
        startUrl: '/',
        useLocalStorageCaching: false,
        advancedDebug: false,
        hideHowToReach: true,
        hideMenuButton: true,
        hideExpanderInDetails: true,
        hideFiltersInMap: false,
        hideDeactiveCentralPointer: true,
        hideShowInMapFromSearch: true,
        avoidModalInDetails: true,
        useAlmostOver: false,
        filterIcon: 'wm-icon-layers'
    },

    STYLE: {
        global: {
            background: '#F3F6E9',
            color: 'black',
            centralPointerActive: 'black',
            buttonsBackground: 'rgba(56, 126, 245, 0.78)'
        },
        details: {
            background: '#F3F6E9',
            buttons: 'rgba(56, 126, 245, 0.78)',
            color: '#929077'
        },
        subnav: {
            color: 'white',
            background: '#387EF5'
        },
        mainBar: {
            color: 'white',
            background: '#387EF5',
            overwrite: true
        },
        menu: {
            color: 'black',
            background: '#F3F6E9'
        },
        search: {
            color: '#387EF5'
        },
        images: {
            background: '#e6e8de'
        },
        line: {
            default: {
                color: 'red',
                weight: 5,
                opacity: 0.65
            },
            highlight: {
                color: '#00FFFF',
                weight: 6,
                opacity: 1
            },
        }
    },

    ADVANCED_DEBUG: false,

    COMMUNICATION: {
        // baseUrl: 'http://dev.be.webmapp.it/',
        // endpoint: 'wp-json/webmapp/v1/',
        // wordPressEndpoint: 'wp-json/wp/v2/'
        // singleFeatureUrl: 'http://www.turismovallecamonica.it/it/get-single-feature-geojson/'
    },

    SEARCH: {
        active: true,
        // indexFields: ['name', 'body', 'email', 'address'],
        indexFields: ['name'],
        showAllByDefault: true,
        stemming: true,
        removeStopWords: true,
        indexStrategy: 'AllSubstringsIndexStrategy', // AllSubstringsIndexStrategy || ExactWordIndexStrategy || PrefixIndexStrategy
        TFIDFRanking: true
    },

     
    // Da riempire solo con menu
    MENU: [],

    LOGIN: {
        useLogin: false,
        forceLogin: false
    },

    OFFLINE: {
        // url: 'http://api.webmapp.it/elba/tiles/map.zip',
        // tms: false,
        // lastRelease: '2016-12-01'
    },

    TRACKING: {
        active: false,
        appId: 'Webmapp@0.1',
        sync: true,
        syncUrl: 'https://data.netseven.it/map/track',
        syncIntervalInMinutes: 30,
        maxBatchItems: 50,
        backgroundFetch: true,
        logging: false,
        config: {
            // Geolocation config
            desiredAccuracy: 0,
            // stationaryRadius: 25,
            distanceFilter: 50,
            // disableElasticity: false, // <-- [iOS] Default is 'false'.  Set true to disable speed-based distanceFilter elasticity
            locationUpdateInterval: 180000, // milliseconds
            // fastestLocationUpdateInterval: 5000,
            // minimumActivityRecognitionConfidence: 80, // 0-100%.  Minimum activity-confidence for a state-change
            // activityRecognitionInterval: 10000,
            // stopDetectionDelay: 1, // [iOS] delay x minutes before entering stop-detection mode
            // stopTimeout: 2, // Stop-detection timeout minutes (wait x minutes to turn off tracking)
            // activityType: 'AutomotiveNavigation',
            // locationTimeout: 30,
            foregroundService: false, // <-- [Android] Running as a foreground-service makes the tracking-service much more inmmune to OS killing it due to memory/battery pressure

            // Application config
            debug: false, // <-- enable this hear sounds for background-geolocation life-cycle.
            // forceReloadOnLocationChange: false, // <-- [Android] If the user closes the app **while locatwm-icon-tracking is started** , reboot app when a new location is recorded (WARNING: possibly distruptive to user)
            // forceReloadOnMotionChange: false, // <-- [Android] If the user closes the app **while locatwm-icon-tracking is started** , reboot app when device changes stationary-state (stationary->moving or vice-versa) --WARNING: possibly distruptive to user)
            // forceReloadOnGeofence: false, // <-- [Android] If the user closes the app **while locatwm-icon-tracking is started** , reboot app when a geofence crossing occurs --WARNING: possibly distruptive to user)
            stopOnTerminate: false, // <-- Don't stop tracking when user closes app.
            startOnBoot: true, // <-- [Android] Auto start background-service in headless mode when device is powered-up.

            // HTTP / SQLite config
            // url: 'http://posttestserver.com/post.php?dir=cordova-background-geolocation',
            // method: 'POST',
            batchSync: true, // <-- [Default: false] Set true to sync locations to server in a single HTTP request.
            autoSync: false, // <-- [Default: true] Set true to sync each location to server as it arrives.
            maxDaysToPersist: 365, // <-- Maximum days to persist a location in plugin's SQLite database when HTTP fails
            // headers: {
            //     'X-FOO': 'bar'
            // },
            // params: {
            //     'auth_token': 'maybe_your_server_authenticates_via_token_YES?'
            // },
            // locationAuthorizationRequest: 'WhenInUse', // <-- [iOS] Always, WhenInUse
            preventSuspend: false,
            heartbeatInterval: 2700 // seconds
        }
    },

    MAP: {
         {
    maxZoom: 18,
    minZoom: 7,
    defZoom: 9,
    center: {
        lat: 43.719287828277004,
        lng: 10.39685368537899
    },
    bounds: {
        southWest: [
            43.34116005412307,
            9.385070800781252
        ],
        northEast: [
            44.09547572946637,
            11.4093017578125
        ]
    }
}
         ,  markerClustersOptions: {
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            maxClusterRadius: 60, // or 40?
            disableClusteringAtZoom: 17
        },
        showCoordinatesInMap: true,
        showScaleInMap: true,
        hideZoomControl: false,
        hideLocationControl: false,

        layers: [{
            label: 'Mappa',
            type: 'maptile',
            // TODO: mettere OSM
            tilesUrl: 'http://{s}.tile.osm.org/',
            default: true
        }, {
            label: 'Satellite',
            type: 'wms',
            tilesUrl: 'http://213.215.135.196/reflector/open/service?',
            layers: 'rv1',
            format: 'image/jpeg'
        }]
    },

    DETAIL_MAPPING: {
        default: {
            table: {
                phone: 'Telefono'
            },
            fields: {
                title: 'name',
                description: 'description',
                image: 'picture_url',
                email: 'mail',
                phone: 'telefono',
                address: 'via'
            }
        }
    },

    // Loop sui layers (da prendere nelle tracce)
    OVERLAY_LAYERS: [ {
            label: 'Tracks',
            type: 'line_geojson',
            icon: 'wm-icon-trail',
            geojsonUrl: 'XXX',
            showByDefault: true
        }, {
            label: 'POI',
            type: 'poi_geojson',
            geojsonUrl: 'XXX',
            color: '#FF3812'
        }
    ],

    PAGES: [],

});

