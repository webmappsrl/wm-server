# WEBMAPP SERVER v0.1.7

## 1 WMCLI

Script e classi per il server di WEBMAPP

Tutto il server si riduce alla chiamata di un unico script:

> php src/webmapp-server.php CONFFILE.conf

Che legge le informazioni dal file di configurazione ed esegue di conseguenza le operazioni necessarie. Il File di configurazione è un file json. Un esempio
utilizzato per i test si trova della directory data.

## 2 TEST

Per gli sviluppatori:

I test possono essere lanciati come segue:

- Tutti:
  > phpunit --color=always --bootstrap src/autoload.php tests/
- Singola classe:
  > phpunit --color=always --bootstrap src/autoload.php tests/WebmappProjectStructureTest.php
- Singolo test
  > phpunit --color=always --bootstrap src/autoload.php --filter testFunctionName tests/WebmappProjectStructureTest.php
- Con code coverage:
  > phpunit --color=always --bootstrap src/autoload.php --coverage-text --whitelist src tests/
- Con Code covrage e HTML output
  > phpunit --color=always --bootstrap src/autoload.php --coverage-text --coverage-html test-log --whitelist src tests/

### 2.1 URL UTILI DEL MANUALE DI PHPUNIT

- https://phpunit.de/manual/current/en/appendixes.assertions.html#appendixes.assertions.assertRegExp
- https://phpunit.de/manual/current/en/textui.html

## 3 Utilities

All'interno del progetto ci sono anche una serie di utilities scollegate all'utilizzo del wmcli. Le utilities rappresentano script che possono essere eseguiti singolarmente senza dipendenze esterne.

Nel progetto sono presenti le seguenti utilities:

- `create_map.php`: script che genera l'mbtiles di una mappa relativa ad una route

## 4 Manuale README

REMOVE THIS:
Paragraphs are separated by a blank line.

2nd paragraph. _Italic_, **bold**, and `monospace`. Itemized lists look like:

- this one
- that one
- the other one

> Block quotes example
> use this

## 5 Server

The server command handle all the Webmapp Jobs.

### 5.1 Installation

The only job that needs an installation process is the `generate_elevation_chart_image` job. Node and npm must be available in the machine. Please follow the installation section [here](https://github.com/Automattic/node-canvas#installation) and when done run `npm i` in the `src/node` directory

### 5.2 Jobs

| Job name                         | Description                                                                                          | Triggers                         |
| -------------------------------- | ---------------------------------------------------------------------------------------------------- | -------------------------------- |
| `update_poi`                     | Update the poi geojson and the related taxonomies                                                    |                                  |
| `update_track`                   | Update the track geojson and the related taxonomies                                                  | `generate_elevation_chart_image` |
| `update_track_metadata`          | Update the track metadata in the track geojson and the related taxonomies                            |                                  |
| `update_track_geometry`          | Update the track geometry in the track geojson                                                       | `generate_elevation_chart_image` |
| `update_route`                   | Update the route geojson, route index, the related taxonomies and create the `routes/[id]` directory | `generate_mbtiles`               |
| `generate_mbtiles`               | Create the mbtiles for the route                                                                     |                                  |
| `generate_elevation_chart_image` | Create the elevation chart png of the track                                                          |
