# kicoephp-src

## Dash

`./public/index.php`
```php
<?php
// app path
define('APP_PATH', __DIR__ . '/../app/');
// autoload
require __DIR__ . '/../vendor/autoload.php';
// link-start 
kicoe\core\Load::link_start();

```

`./app/config.php`
```php
<?php
return [
    'db' => [
        'hostname'    => 'mysql',
        'database'    => 'blog',
        'username'    => 'root',
        'password'    => '123456'
    ],
    'route' => [
        'article/delete' => 'auth|admin/article@delete',
    ],
    'test'  => true,
    'middleware' => 'app\Mid'
];

```

`./app/Mid.php`
```php
<?php

namespace app;

use kicoe\core\Session;
use kicoe\core\Response;

class Mid
{
    public function auth()
    {
        if (!Session::has('name')) {
            Response::getInstance()->redirect('/page/admin.html');
            return false;
        }
        return true;
    }
}
```

`mkdir -m 755 ./storage`