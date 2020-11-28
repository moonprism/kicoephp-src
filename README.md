# kicoephp

一个非常简单小巧 (仅由9个核心类组成) 的 php web 框架.

## Install

```
composer require kicoephp/src
```

## Dash

nginx 配置:
```
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```
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

## Route

新版本框架源于自己一次写的快速路由前缀树实现, 总之就是非常快！[:zap:swoole版本](https://github.com/moonprism/kicoephp-src/tree/swoole)

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
    if ($value === 'false') {
        return false;
    } else if ($value === 'true') {
        return true;
    }
    return false; // ??
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

## DB

使用 `DB` 前必须在 config 中配置 `mysql`

```php
$link = new Link([
    'mysql' => [
        'db' => 'test',
        'host' => 'mysql',
        'port' => 3306,
        'user' => 'root',
        'passwd' => '123456',
        'charset' => 'utf8mb4',
    ]
]);

// 可以直接执行 sql
DB::select('select id from article where id in (?) and status = ?', [1, 2, 3], 2);
DB::insert('insert into article(title, status) values (?,?),(?,?)', 'first', 1, 'sec**', 3);
...
```

`\kicoe\core\DB::table('xx')` 返回的是一个 `Model` 对象，该对象可以通过静态/非静态的方式调用下列方法，并返回自身对象或查询结果。

```php
/**
 * Class Model
 * @package kicoe\core
 * @method array select(...$columns)
 * @method self where(string $segment, ...$params)
 * @method self orWhere(string $segment, ...$params)
 * @method self orderBy(...$params)
 * @method self limit(...$params)
 * @method self join(...$params)
 * @method self leftJoin(...$params)
 * @method self rightJoin(...$params)
 * @method self having(...$params)
 * @method self columns(...$params)
 * @method self addColumns(...$params)
 * @method self removeColumns(...$params)
 * @method self from(string $table)
 * @method array get()
 * @method self first()
 * @method self groupBy(string $segment)
 * @method int save()
 * @method int count()
 * @method int delete()
 * @method int update(array $data)
 * @method static int insert(...$data)
 * @method static static fetchById($id)
 */
class Model
```

### Select

```php
DB::table('tag')->where('id in (?)', [1, 2])->selete('id', 'name');
```

```php
DB::table('tag')->where('color', 'aqua')
    ->where('deleted_at is null')
    ->orderBy('id', 'desc')
    ->limit(0, 10)
    ->get();
```

查询结果都为单个 `Model` 对象或 `Model` 对象的数组。

### Insert

```php
DB::table('tag')->insert(['name' => 'php', ...], ['name' => 'golang', ...]);
```

### Update

```php
DB::table('tag')->where('id', 12)->update(['name' => 'php', ...]);
```

### Delete

```php
DB::table('tag')->where('id', 12)->delete();
```

### Transaction

```php
$title = '123';
$tag_id = 10;
DB::transaction(function () use ($title, $tag_id) {
    $article = new Article();
    $article->title = $title;
    $article->save();
    DB::table('article_tag')->insert([
        'art_id' => $article->id,
        'tag_id' => $tag_id,
    ]);
    // throw any Exception tigger DB::rollBack()
});
```

## Model

```php
<?php

namespace app\model;

use kicoe\core\Model;

class Article extends Model
{
    // 默认类名小写
    const TABLE = 'article';
    // 默认'id'
    const PRIMARY_KEY = 'id';

    public int $id;
    public string $title;
    public int $status;
    public string $image;
    public string $summary;
    public string $content;
    public string $updated_time;
    public string $created_time;
    // public ?string $deleted_at;

    protected array $tags;

    const STATUS_DRAFT = 1;
    const STATUS_PUBLISH = 2;
    ...
}
```

继承了 `Model` 的类用法和以上　`DB::table('_table_name')` 一样，并且会自动将其中定义所有的 public 属性作为查询字段。

```php
use app\model\Article;

// Article 对象
$art = Article::fetchById(1);

$art = new Article();
$art->title = 'new blog';
// int rowCount
$art->save();
// int
echo $art->id;

$arts = Article::where('status', Article::STATUS_PUBLISH)
    ->where('deleted_at is null')
    ->where('id in (?)', [1, 2, 3])
    ->orderBy('created_time', 'desc')
    ->limit(0, 10);

// int where 条件下的总数
$count = $arts->count();

// array Article[]
$article_list = $arts->get();
```

增删改等操作也等同于 `DB::table('table_name')`，但要注意对象的 `save()` 

```php
$articles = Article::get();
foreach ($articles as $article) {
    $article->title = '12';
    $article->save();
}
```

以上代码执行 sql 过多是一个问题，更重要的是框架中用来判断 `Model` 是否更新的原字段信息存在一个不是用构造函数初始化的属性中(所谓延迟)，单纯的 `fetchAll()` 无法初始化这个属性，导致更新 sql 语句里会带上所有不为 uninitialized 的字段。

虽然可能是设计缺陷，最好还是转成以下更常规的更新方式:

```php
Article::update(['title' => '12']);
```

当然不是数组的查询结果完全没问题:

```php
$article = Article::first();
$article->title = '12';
// update article set title = ? where id = ?  limit ? ["12", 2, 1]
$article->save();
// 再 save 一遍不会执行任何语句
$article->save();
```

---

更多用法可以参照 [blog](https://github.com/moonprism/blog/tree/master/read)
