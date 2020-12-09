# kicoephp

<a href="https://github.com/moonprism/kicoephp-src/actions"><img src="https://github.com/moonprism/kicoephp-src/workflows/tests/badge.svg" alt="Build Status"></a>

一个非常简单小巧的 php web 框架（swoole）.

## Install

```
composer require kicoephp/src:dev-swoole
```

## Dash

`index.php`
```php
<?php

require __DIR__ . './vendor/autoload.php';

use kicoe\core\Link;

$link = new Link();
$link->route('/hello/{word}', function (string $word) {
    return "hello ".$word;
});

$link->start();
```

```sh
# default 0.0.0.0:80
php index.php
```

## Config

```php
$link = new Link([
    'swoole' => [
        'host' => '0.0.0.0',
        'port' => 9400,
        'set' => [
            // swoole 配置
            'daemonize' => false,
        ],
    ],
    'debug' => true,
]);
```

## Route

```php
Route::get('/art/{page}', 'Article@list');
Route::get('/art/{id}/comments', 'Comment@list');
Route::get('/art/detail/{id}', 'Article@detail');
```

上面的 route 定义将被解析成以下结构:

```json
{
    "GET": {
        "path": "/",
        "handler": [],
        "children": {
            "a": {
                "path": "art/",
                "handler": [],
                "children": {
                    "$": {
                        "path": "page/id",
                        "handler": ["Article", "list"],
                        "children": {
                            "c": {
                                "path": "comments",
                                "handler": ["Comment", "list"],
                                "children": []
                            }
                        }
                    },
                    "d": {
                        "path": "detail/",
                        "handler": [],
                        "children": {
                            "$": {
                                "path": "id",
                                "handler": ["Article", "detail"],
                                "children": []
                            }
                        }
                    }
                }
            }
        }
    }
}
```

### Routing

```php
<?php
use kicoe\core\Link;
use kicoe\core\Route;

use app\controller\ArticleController;

$link = new Link();

// 自动解析类方法注释 eg: @route get /index/{id}
Route::parseAnnotation(ArticleController::class);

// 一般 routing
$link->route('/article/tag/{tag_id}/page/{page}/', [ArticleController::class, 'listByTag']);

// 闭包
$link->route('/comment/up/{art_id}', function (Request $request, int $art_id) {
    $email = $request->input('email');
    ...
}, 'post');

$link->start();
```

#### 自定义基础类型

在路由参数映射到控制器方法参数时，将会自动将 string 转换成定义的类型，同时允许自定义:

```php
<?php
$link = new Link();

$link->bind('array', function (string $value):array {
    return explode(',', $value);
});

$link->bind('bool', function (string $value):bool {
    return $value === 'true';
});

// 访问 /1,2,3/true 将返回　['ids' => ['1', '2', '3'], 'is_update' => true]
$link->route('/{ids}/{is_update}', function (array $ids, bool $is_update) {
    return [
        'ids' => $ids,
        'is_update' => $is_update
    ];
});

$link->start();
```

## Request

在定义控制器方法参数类型为 Request 时，系统将会自动构造实例注入

```php
<?php
$link = new \kicoe\core\Link();

$link->route('/tag/{tag_id}', function (\kicoe\core\Request $request) {
    $request->input('name');
    ...
}, 'put');

$link->start();
```

## Response

同上，系统也将自动构造注入 Response 类:

```php
<?php
$link = new \kicoe\core\Link();

$link->route('/tag/{tag_id}', function (\kicoe\core\Response $response, int $tag_id) {
    if (!$tag = search($tag_id)) {
        return $response->status(404);
    }
    ...
    return $response->json($tag);
}, 'get');

$link->start();
```

#### 自定义请求与返回类

继承系统 Request 和 Response 类中定义的所有公共属性将自动解析成相应实现

> 类似于 java 开发中的 `DTO` 与 `VO`

```php
<?php
$link = new \kicoe\core\Link();

class CommentRequest extends \kicoe\core\Request
{
    // 没有默认值的属性将作为必要参数
    public int $to_id = 0;
    public string $name;
    public string $email;
    public string $link = '';
    public string $content;

    /**
     * @return string error 错误信息
     */
    public function filter():string
    {
        $this->name = htmlspecialchars($this->name);
        $this->email = htmlspecialchars($this->email);
        $this->link = htmlspecialchars($this->link);
        $this->content = htmlspecialchars($this->content);
        if (!preg_match(
            '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/',
            $this->email
        )) {
            return 'email 格式错误';
        }
        return '';
    }
}

class ApiResponse extends \kicoe\core\Response
{
    public int $code = 200;
    public string $message = '';
    /** @var Comment[] */
    public array $data = [];

    public function setBodyStatus(int $code, string $message):self
    {
        $this->code = $code;
        $this->message = $message;
        return $this;
    }
}

$link->route('/{aid}', function (CommentRequest $request, ApiResponse $response, int $aid) {
    if ($err = $request->filter()) {
        return $response->setBodyStatus(422, 'ValidationError: '.$err);
    }
    Comment::insert([
        'art_id' => $aid,
        ... // merge request data
    ]);
    $response->data = Comment::where('art_id', $aid)->get();
    return $response;
}, 'post');

$link->start();
```
