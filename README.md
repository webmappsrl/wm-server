# WEBMAPP SERVER v0.1.7

## 1 WMCLI

Script e classi per il server di WEBMAPP

Tutto il server si riduce alla chiamata di un unico script:

> php src/webmapp-server.php CONFFILE.conf

Che legge le informazioni dal file di configurazione ed esegue di conseguenza le operazioni necessarie. Il File di
configurazione è un file json. Un esempio utilizzato per i test si trova della directory data.

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

All'interno del progetto ci sono anche una serie di utilities scollegate all'utilizzo del wmcli. Le utilities
rappresentano script che possono essere eseguiti singolarmente senza dipendenze esterne.

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

The only job that needs an installation process is the `generate_elevation_chart_image` job. Node and npm must be
available in the machine. Please follow the installation
section [here](https://github.com/Automattic/node-canvas#installation) and when done run `npm i` in the `src/node`
directory. TODO: Add the installation check/process at runtime

### 5.2 Server parameters

The server can be instantiated with some parameters:

| Parameter          | Description                                                                                                                                              |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `serverId`         | Set the server id used when communicating with HOQU                                                                                                      |
| `jobs`             | Set the accepted jobs (list of jobs at [5.3](#53-jobs)). To specify more jobs separate them with a comma `,`                                             |
| `acceptInstances`  | Set the accepted instances. To specify more instances separate them with a comma `,`                                                                     |
| `excludeInstances` | Set the instances that must not be worked from the server. To specify more instances separate them with a comma `,`. Prevails over the `acceptInstances` |
| `verbose`          | Force the verbose log in the console                                                                                                                     |

### 5.3 Jobs

| Job name                         | Params                             | Description                                                                                                                                       | Triggers                                           |
| -------------------------------- | ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------- |
| `update_poi`                     | `int(id)`                          | Update the poi geojson and the related taxonomies                                                                                                 | `generate_audio`                                   |
| `update_track`                   | `int(id)`, `bool(update_geometry)` | Update the track geojson and the related taxonomies. The `update_geometry` param force the geometry update when the `osmid` property is available | `generate_elevation_chart_image`, `generate_audio` |
| `update_route`                   | `int(id)`                          | Update the route geojson, route index, the related taxonomies and create the `routes/[id]` directory                                              | `generate_mbtiles`, `generate_audio`               |
| `update_event`                   | `int(id)`                          | Update the event geojson and the related taxonomies                                              | `generate_audio`                               |                                                    |
| `update_taxonomy`                | `int(id)`                          | Update the taxonomy definition                                                                                                                    |                                                    |
| `generate_mbtiles`               | `int(id)`                          | Create the mbtiles for the route                                                                                                                  |                                                    |
| `generate_elevation_chart_image` | `int(id)`                          | Create the elevation chart png of the track                                                                                                       |                                                    |
| `delete_poi`                     | `int(id)`                          | Delete the poi geojson and prune the id from the taxonomies                                                                                       |                                                    |
| `delete_track`                   | `int(id)`                          | Delete the track geojson and prune the id from the taxonomies                                                                                     |                                                    |
| `delete_route`                   | `int(id)`                          | Delete the route geojson and prune the id from the taxonomies                                                                                     |                                                    |
| `delete_event`                   | `int(id)`                          | Delete the event geojson and prune the id from the taxonomies                                                                                     |                                                    |
| `delete_taxonomy`                | `int(id)`                          | Delete the taxonomy json                                                                                                                          |                                                    |
| `generate_audio`                 | `int(id)`, `string(lang)`          | Generate the audio from the description in the given language of the given feature                                                                |                                                    |

### 5.4 Server.conf

Every instance can be configured using the file `/server/server.conf`.

#### 5.4.1 A Project

| Key               | Type   | Description                                                               |
| ----------------- | ------ | ------------------------------------------------------------------------- |
| `generate_audios` | `bool` | Make the server generate the audios of the description when not available |

##### 5.4.1.1 Elevation Chart Image

#### 5.4.2 K Project

##### 5.4.2.1 Multimap Project

To enable the multimap in the k project you need to set the `multimap` property to `true`. This will trigger the routes'
generation, mbtiles included. The routes can be filtered using the `filters` property which currently support two type
of filtering. If more than one filter is set then the results are unified (`OR` filters).

| Key               | Type    | Description                                                                           |
| ----------------- | ------- | ------------------------------------------------------------------------------------- |
| `routes_id`       | `int[]` | When set a route is generated if its id is present in the array                       |
| `routes_taxonomy` | `int[]` | When set a route is generated if at least one of its taxonomy is present in the array |
