#!/usr/bin/env node
let fs = require("fs"),
    ChartjsNode = require("chartjs-node");

if (process.argv.length < 3) {
    console.error("Missing file source parameter");
    return;
}
if (process.argv.length < 4) {
    console.error("Missing file destination parameter");
    return;
}

let args = process.argv.splice(2, process.argv.length - 2),
    url = args[0],
    dest = args[1],
    config = {
        width: 1200,
        height: 600,
        fontColor: "#000000",
        fontSize: 25,
        fontStyle: "bold",
        steps: 50,
        fillColor: "#ffffff",
        lineColor: "#000000",
        lineWidth: 2,
        pointBorderColor: "#000000",
        pointFillColor: "#000000",
        pointHoverBorderColor: "#000000",
        pointHoverFillColor: "#000000",
        pointRadius: 1,
        pointHoverRadius: 5,
        xMaxLines: 8,
        yMaxLines: undefined,
        showGrid: true
    };

if (args.length > 2) {
    let configUrl = args[2];
    try {
        let configJson = JSON.parse(fs.readFileSync(configUrl, "utf8"));
        if (configJson["elevation-chart"]) {
            for (let i in config) {
                if (!!configJson["elevation-chart"][i]) {
                    config[i] = configJson["elevation-chart"][i];
                }
            }
        }
    } catch (e) {
        console.warning("The specified config is not valid. Using default values");
    }
}

try {
    // TODO: Check if path is on filesystem, eventually request via HTTP
    fs.readFile(url, "utf8", (err, content) => {
        if (err) {
            console.error(err);
        } else processGeojson(content, config);
    });
} catch (e) {
    console.error("Unable to read the file from " + url);
    return;
}

function processGeojson(content, config) {
    try {
        let geojson = JSON.parse(content),
            chartNode = new ChartjsNode(config.width, config.height),
            chartJsOptions = getChartOptions(geojson, config);

        chartNode.on("beforeDraw", function (Chartjs) {
            Chartjs.defaults.global.defaultFontColor = config.fontColor;
            Chartjs.defaults.global.defaultFontSize = config.fontSize;
            Chartjs.defaults.global.defaultFontStyle = config.fontStyle;
        });

        chartNode
            .drawChart(chartJsOptions)
            .then(() => {
                return chartNode.getImageBuffer("image/png");
            })
            .then((buffer) => {
                Array.isArray(buffer);
                return chartNode.getImageStream("image/png");
            })
            .then(() => {
                return chartNode.writeImageToFile("image/png", dest);
            })
            .then(() => {
                chartNode.destroy();
            });
    } catch (e) {
        console.error(e);
    }
}

function getChartOptions(geojson, config) {
    let labels = [],
        values = [],
        steps = config.steps,
        trackLength = 0,
        currentDistance = 0,
        previousLocation,
        currentLocation,
        _chartValues = [];

    steps = Math.max(5, Math.min(800, steps));

    labels.push("0");
    values.push(Math.round(geojson.geometry.coordinates[0][2]));
    currentLocation = {
        longitude: geojson.geometry.coordinates[0][0],
        latitude: geojson.geometry.coordinates[0][1],
    };
    _chartValues.push(currentLocation);

    for (let i = 1; i < geojson.geometry.coordinates.length; i++) {
        previousLocation = currentLocation;
        currentLocation = {
            longitude: geojson.geometry.coordinates[i][0],
            latitude: geojson.geometry.coordinates[i][1],
        };
        trackLength += _getDistanceBetweenPoints(previousLocation, currentLocation);
    }

    let step = 1;
    currentLocation = {
        longitude: geojson.geometry.coordinates[0][0],
        latitude: geojson.geometry.coordinates[0][1],
        altitude: geojson.geometry.coordinates[0][2],
    };

    for (
        let i = 1;
        i < geojson.geometry.coordinates.length && step < steps;
        i++
    ) {
        previousLocation = currentLocation;
        currentLocation = {
            longitude: geojson.geometry.coordinates[i][0],
            latitude: geojson.geometry.coordinates[i][1],
            altitude: geojson.geometry.coordinates[i][2],
        };
        let localDistance = _getDistanceBetweenPoints(
            previousLocation,
            currentLocation
        );
        currentDistance += localDistance;

        while (currentDistance > (trackLength / steps) * step) {
            let difference =
                localDistance - (currentDistance - (trackLength / steps) * step),
                deltaLongitude = currentLocation.longitude - previousLocation.longitude,
                deltaLatitude = currentLocation.latitude - previousLocation.latitude,
                deltaAltitude = currentLocation.altitude - previousLocation.altitude;

            _chartValues.push({
                longitude:
                    previousLocation.longitude +
                    (deltaLongitude * difference) / localDistance,
                latitude:
                    previousLocation.latitude +
                    (deltaLatitude * difference) / localDistance,
            });
            values.push(
                Math.round(
                    previousLocation.altitude +
                    (deltaAltitude * difference) / localDistance
                )
            );
            labels.push(((step * trackLength) / (steps * 1000)).toFixed(1));

            step++;
        }
    }

    _chartValues.push({
        longitude:
            geojson.geometry.coordinates[geojson.geometry.coordinates.length - 1][0],
        latitude:
            geojson.geometry.coordinates[geojson.geometry.coordinates.length - 1][1],
    });
    values.push(
        Math.round(
            geojson.geometry.coordinates[geojson.geometry.coordinates.length - 1][2]
        )
    );
    labels.push((trackLength / 1000).toFixed(1));

    let fillColor = config.fillColor,
        lineColor = config.lineColor,
        lineWidth = config.lineWidth,
        pointBorderColor = config.pointBorderColor,
        pointFillColor = config.pointFillColor,
        pointRadius = config.pointRadius,
        xMaxLines = config.xMaxLines,
        yMaxLines = config.yMaxLines,
        showGrid = config.showGrid;

    let options = {
        type: "line",
        data: {
            labels: labels,
            datasets: [
                {
                    label: "",
                    fill: true,
                    cubicInterpolationMode: "monotone",
                    lineTension: 0.3,
                    backgroundColor: fillColor,
                    borderColor: lineColor,
                    borderWidth: lineWidth,
                    borderCapStyle: "butt",
                    borderDash: [],
                    borderDashOffset: 0.0,
                    borderJoinStyle: "miter",
                    pointRadius: pointRadius,
                    pointBorderColor: pointBorderColor,
                    pointBorderWidth: 1,
                    pointBackgroundColor: pointFillColor,
                    pointHoverRadius: pointRadius,
                    pointHoverBorderColor: pointBorderColor,
                    pointHoverBorderWidth: 2,
                    pointHoverBackgroundColor: pointFillColor,
                    pointHitRadius: 10,
                    data: values,
                    spanGaps: false,
                },
            ],
        },
        options: {
            events: ["mousemove", "click", "touchstart", "touchmove"],
            maintainAspectRatio: false,
            legend: {
                display: false,
            },
            scales: {
                yAxes: [
                    {
                        ticks: {
                            beginAtZero: false,
                            suggestedMax: undefined,
                            suggestedMin: undefined,
                            maxTicksLimit: yMaxLines,
                        },
                        scaleLabel: {
                            display: true,
                            labelString: "m",
                            padding: {
                                top: 0,
                                left: 0,
                                right: 0,
                                bottom: -5,
                            },
                        },
                        gridLines: {
                            display: showGrid,
                        }
                    },
                ],
                xAxes: [
                    {
                        ticks: {
                            maxTicksLimit: xMaxLines,
                            maxRotation: 0,
                        },
                        scaleLabel: {
                            display: true,
                            labelString: "km",
                            padding: {
                                top: -5,
                                left: 0,
                                right: 0,
                                bottom: 0,
                            },
                        },
                        gridLines: {
                            display: showGrid,
                        },
                    },
                ],
            },
        },
    };

    return options;
}

/**
 * Return the distance in meters between two locations
 *
 * @param point1 the first location
 * @param point2 the second location
 */
function _getDistanceBetweenPoints(point1, point2) {
    let R = 6371e3,
        lat1 = (point1.latitude * Math.PI) / 180,
        lat2 = (point2.latitude * Math.PI) / 180,
        lon1 = (point1.longitude * Math.PI) / 180,
        lon2 = (point2.longitude * Math.PI) / 180,
        deltaLat = lat2 - lat1,
        deltaLon = lon2 - lon1;

    let a =
        Math.sin(deltaLat / 2) * Math.sin(deltaLat / 2) +
        Math.cos(lat1) * Math.cos(lat2) * Math.sin(deltaLon / 2) * Math.sin(deltaLon / 2);
    let c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c;
}
