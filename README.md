# kicoephp-src

## Install

```
composer install kicoephp/src
```

## Dash

`./public/index.php`

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use kicoe\core\Link;
use kicoe\core\Request;

$link = new Link();
$link->route('/hello/{id}', function (Request $request, $id) {
    return [
       'id' => $id,
       'version' => $request->query('v')
    ];
});
$link->start();
```
