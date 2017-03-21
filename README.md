WEBMAPP SERVER
==============

Script e classi per il server di WEBMAPP

Tutto il server si riduce alla chiamata di un unico script:

php src/webmapp-server.php CONFFILE.conf

CHe legge le informazioni dal file di configurazione ed esegue di conseguenza le operazioni necessarie. Il File di configurazione è un file json. Un esempio 
utilizzato per i test si trova della directory data.

Per gli sviluppatori:

I test possono essere lanciati come segue:
Tutti:
phpunit --color=always --bootstrap src/autoload.php tests/
Singola classe:
phpunit --color=always --bootstrap src/autoload.php tests/WebmappProjectStructureTest.php
Con code coverage:
phpunit --color=always --bootstrap src/autoload.php --coverage-text --whitelist src tests/

URL UTILI DEL MANUALE DI PHPUNIT
 * https://phpunit.de/manual/current/en/appendixes.assertions.html#appendixes.assertions.assertRegExp
 * https://phpunit.de/manual/current/en/textui.html




REMOVE THIS:
Paragraphs are separated by a blank line.

2nd paragraph. *Italic*, **bold**, and `monospace`. Itemized lists
look like:

  * this one
  * that one
  * the other one

> Block quotes example
> use this 

