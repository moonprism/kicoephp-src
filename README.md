# kicoephp-src

## Install

```
composer install kicoephp/src
```

## Dash

```php
<?php

require __DIR__ . './vendor/autoload.php';

use kicoe\core\Link;
use kicoe\core\Request;

$link = new Link();
$link->route('/hello/{id}', function (Request $request, int $id) {
    return [
       'id' => $id,
       'version' => $request->query('v')
    ];
});
$link->start();
```
