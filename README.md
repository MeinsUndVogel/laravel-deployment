# Automatisches Deployment mit GitHub

[![Latest Stable Version](http://poser.pugx.org/muv/laravel-deployment/v)](https://packagist.org/packages/muv/laravel-deployment)
[![Latest Unstable Version](http://poser.pugx.org/muv/laravel-deployment/v/unstable)](https://packagist.org/packages/muv/laravel-deployment)
[![License](http://poser.pugx.org/muv/laravel-deployment/license)](https://packagist.org/packages/muv/laravel-deployment)
[![PHP Version Require](http://poser.pugx.org/muv/laravel-deployment/require/php)](https://packagist.org/packages/muv/laravel-deployment)

Mit diesem Package kann in einem Laravel Projekt automatisch ein Update bei Push-Events im GitHub Repository
durchgeführt werden.

## Installation

Einbindung mit Composer

```bash
composer require muv/laravel-deployment
```

Danach das Configfile und Deployment Script publizieren:

```bash
php artisan laravel-deployment:install
```

Eine Anleitung zur Konfiguration des Webservers und GitHub befindet sich in [deployment.md](deployment.md).

## Kontakt

Bei Fragen oder Anregungen: [muv.com/kontakt](https://muv.com/kontakt)

## Lizenz

Das Package ist unter der [MIT-Lizenz](LICENSE) erhältlich.
