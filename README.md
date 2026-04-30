# Automatisches Deployment mit GitHub für PHP-Projekte

[![Latest Stable Version](https://poser.pugx.org/muv/laravel-deployment/v/stable.svg)](https://packagist.org/packages/muv/laravel-deployment)
[![Latest Unstable Version](https://poser.pugx.org/muv/laravel-deployment/v/unstable.svg)](https://packagist.org/packages/muv/laravel-deployment)
[![License](https://poser.pugx.org/muv/laravel-deployment/license.svg)](https://packagist.org/packages/muv/laravel-deployment)

Mit diesem Package kann in einem PHP-Projekt automatisch ein Update bei Push-Events im GitHub Repository durchgeführt
werden.

## Installation

```sh
composer require muv/laravel-deployment
```

Danach das Deploy-Script und die Deployment-Anleitung publizieren:

```sh
./vendor/bin/publish-deploy
```

Dies erstellt eine `deploy.php` Datei im `public` Verzeichnis und eine `deployment.md` im Projekt-Root.

Die [deployment.md](deployment.md) enthält eine ausführliche Schritt-für-Schritt-Anleitung zur Einrichtung des
Deployments auf dem Server.

## Kontakt

Bei Fragen oder Anregungen: [muv.com/kontakt](https://muv.com/kontakt)

## Tests

```bash
composer test
```

## Lizenz

Das Package ist unter der [MIT-Lizenz](LICENSE) erhältlich.
