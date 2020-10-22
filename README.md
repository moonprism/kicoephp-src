# kicoephp

kicoephp 是一个非常简单轻巧 (仅有9个核心类) 的 PHP web 框架.

初衷是用于开发个人博客, 后来因为想写一套快速的路由前缀树实现而顺便将其重写, 现在虽然 route 的实现是写好了, 但需真正发挥其效力要做的的整合 swoole 却没做... 才不是半成品，这叫高度可定制化啦

## Install

```
composer require kicoephp/src
```

## Dash

```php
<?php

require __DIR__ . './vendor/autoload.php';

use kicoe\core\Link;
use kicoe\core\Response;

$link = new Link();
$link->route('/hello/{word}', function (Response $response, string $word) {
    return $response->text("hello ".$word);
});

$link->start();
```

## Route

ArticleController.php
```php
<?php
namespace app\controller;

use kicoe\core\Response;
use kicoe\core\Cache;

class ArticleController
{
    /**
     * @route get /
     * @route get /article/page/{page}
     * @param Response $response
     * @param int $page
     * @return Response
     */
    public function list(Response $response, int $page = 1)
    {
        return $response->json(['page' => $page]);
    }

    /**
     * @route get /art/{art_id}/comments
     * @param Cache $cache
     * @param int $art_id
     * @return array
     */
    public function comments(Cache $cache, int $art_id)
    {
        return $cache->getArr('art:'.$art_id) ?? [];
    }

    public function listByTag(int $tag_id, int $page = 1)
    {
        return [
            'tag_id' => $tag_id, 
            'page' => $page
        ];
    }
}
```
index.php
```php
<?php
use kicoe\core\Link;
use kicoe\core\Route;

use app\controller\ArticleController;

$link = new Link();
// 自动解析类方法注释中的 @route
Route::parseAnnotation(ArticleController::class);

// 一般 routing
$link->route('/article/tag/{tag_id}/page/{page}/', [ArticleController::class, 'listByTag']);

// 闭包(闭包推荐用来测试，暂不支持缓存)
$link->route('/commen/up/{art_id}', function (Request $request, int $art_id) {
    $request->input('email');
    ...
}, 'post');

$link->start();
```

控制器中的 `Response` `Cache` 都是框架自动注入的，如果想自定义类：

```php
// 控制器方法对应类型的参数将自动注入该实例 index(self\Cache $cache, ...)
$link->bind(self/Cache::class, new self\Cache);
```
> 注意，response 和 request 不能绑定到 $link (也就是app), 因为这两个是和路由执行相关的
> 方便未来转换 swoole 请求

除了注入Class类型的参数，基础类型的参数也能自定义绑定

```php
// index(array $v, ...) 将会自动解析实际路由传过来的 $v 参数 'a,b,c' 为 ['a', 'b', 'c']
$link->bind('array', function (string $value):array {
    return explode(',', $value);
});

// 可以大胆点定义~:
$link->bind('bool', function (string $value):bool {
    if ($value === 'false') {
        return false;
    } else if ($value === 'true') {
        return true;
    ...
});
```

## Model

```php
<?php

namespace app\model;

use kicoe\core\Model;

class Article extends Model
{
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

继承 `Model` 类就可以作为模型使用, 其中所有为 public 的属性将作为查询的字段, 如果不想查询某个字段，只需要:

```php
    ...
    public function getList()
    {
        return $this->removeColumns('content')->get();
    }
```

Model 所有的查询与构造查询相关的方法都可以静态调用，返回自身对象或查询结果

```php
use app\model\Article;
...

// Article object
$art = Article::fetchById(1);

$art->title = 'new blog';
// int rowCount
$art->save();

// [Article object, ...]
$arts = Article::where('status', self::STATUS_PUBLISH)
    ->where('deleted_at is null')
    ->where('id in (?)', [1, 2, 3])
    ->orderBy('created_time', 'desc')
    ->limit(0, 10)
    ->get();
// int
$count = $arts->count();

// int rowCount
Article::update(['title' => 'xx', 'content' => 'xxx']);

// int rowCount
Article::insert(['title' =>  'xx'], ['title' => 'xx']);

$art1 = new Article();
$art1->title = 'xx';
$art2 = new \kicoe\core\Model();
$art2->title = 'xx';
// int rowCount
Article::insert($art1, $art2);

// Model obj 将返回一个原生 Model 对象，除了字段用法还是一样的
\kicoe\core\DB::table('article');

// [Model object, ...]
\kicoe\core\DB::select('select * from article where id in (?)', [1, 2, 3]);
```

可用的函数列表如下：

```php
/**
 * Class Model
 * @package kicoe\core
 *
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
 * @method array get(...$params)
 * @method self first()
 * @method self groupBy(string $segment)
 * @method int save()
 * @method int count()
 * @method int delete()
 * @method static int update(array $data)
 * @method static int insert(...$data)
 * @method static self fetchById($id)
 */
```

> 因为实现都很简单，具体用法可以看看 SQL 类源码

## Response

`Response.php`

## Request

`Request.php`