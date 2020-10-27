# WEBMAPP SERVER v0.1.11

# 1 WMCLI

Script e classi per il server di WEBMAPP

Tutto il server si riduce alla chiamata di un unico script:

>php src/webmapp-server.php CONFFILE.conf

Che legge le informazioni dal file di configurazione ed esegue di conseguenza le operazioni necessarie. Il File di configurazione è un file json. Un esempio
utilizzato per i test si trova della directory data.

#2 TEST

Per gli sviluppatori:

I test possono essere lanciati come segue:
 - Tutti:
>phpunit --color=always --bootstrap src/autoload.php tests/
 - Singola classe:
>phpunit --color=always --bootstrap src/autoload.php tests/WebmappProjectStructureTest.php
 - Singolo test
>phpunit --color=always --bootstrap src/autoload.php --filter testFunctionName tests/WebmappProjectStructureTest.php
 - Con code coverage:
>phpunit --color=always --bootstrap src/autoload.php --coverage-text --whitelist src tests/
 - Con Code covrage e HTML output
>phpunit --color=always --bootstrap src/autoload.php --coverage-text --coverage-html test-log --whitelist src tests/

####2.1 URL UTILI DEL MANUALE DI PHPUNIT

- https://phpunit.de/manual/current/en/appendixes.assertions.html#appendixes.assertions.assertRegExp
- https://phpunit.de/manual/current/en/textui.html

# 3 Utilities

All'interno del progetto ci sono anche una serie di utilities scollegate all'utilizzo del wmcli. Le utilities rappresentano script che possono essere eseguiti singolarmente senza dipendenze esterne.

Nel progetto sono presenti le seguenti utilities:

- `create_map.php`: script che genera l'mbtiles di una mappa relativa ad una route

# 4 Manuale README
REMOVE THIS:
Paragraphs are separated by a blank line.

2nd paragraph. _Italic_, **bold**, and `monospace`. Itemized lists look like:

- this one
- that one
- the other one

> Block quotes example
> use this
