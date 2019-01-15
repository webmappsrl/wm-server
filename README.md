# wm-server

The wm-server software performs all the operations for creating the application instances (APP, WEBAPP, API) WEBMAPP starting from the data contained in the instances of WORDPRESS in the content type of MAPPA

This software is developed and mantained by WEBMAPP TEAM (see authors). Please fill free to contact us (info@webmap.it) for any question.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

What things you need to install the software and how to install them

```
PHP > 5.6.25
```

## Running the tests

Tests are developed using (PhpUnit)[https://phpunit.de/] framework. Use the following command to run the test (from root directory):

```
phpunit --color=always --bootstrap src/autoload.php --coverage-text --whitelist src tests/
```

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/webmappsrl/wm-server/tags).

Please refers to the following MAJOR Version schema:

| # | Name        | Year |
|---|-------------|------|
| 1 | Elbrus      | 2019 |
| 2 | Aconcagua   | 2020 |
| 3 | Denali      | 2021 |
| 4 | Everest     | 2022 |
| 5 | Kilimanjaro | 2023 |
| 6 | Carstensz   | 2024 |
| 7 | Vinson      | 2025 |


## Authors

* **Alessio Piccioli** - *CTO* - [Webmapp](https://github.com/webmappsrl)
* **Davide Pizzato** - *Developer* - [Webmapp](https://github.com/webmappsrl)
* **Marco Barbieri** - *Map Maker and UX* - [Webmapp](https://github.com/webmappsrl)

See also the list of [contributors](https://github.com/webmappsrl/wm-server/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details

## Acknowledgments

* Hat tip to anyone whose code was used
* Inspiration
* etc