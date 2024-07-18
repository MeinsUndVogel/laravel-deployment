# laravel-deployment

#### Automatisches Deployment über GitHub bei Shared-Hosting

Das Package kann über Composer eingebunden werden:

```bash
composer require muv/laravel-deployment
```

Es müssen das Configfile und das Deployment Script publiziert werden:

```bash
php artisan laravel-deployment:install
```

Anschließend den Webserver und GitHub [konfigurieren](./DEPLOYMENT-EINRICHTEN.md).
