<?php

namespace kicoe\core;

use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    /**
     * 用于执行私有方法
     * @param string $class
     * @param string $method
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    protected static function getMethod(string $class, string $method)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($method);
        $method->setAccessible(true);
        return $method;
    }

    public function testParseTreeNode()
    {
        $parse_tree_node = self::getMethod(Route::class, 'parseTreeNode');
        $result = $parse_tree_node->invokeArgs(null, ['article/$id', ['article', 'id']]);
        $expect = ['a', (object)[
            'path' => 'article/',
            'handler' => [],
            'children' => [
                '$' => (object)[
                    'path' => 'id',
                    'handler' => ['article', 'id'],
                    'children' => [],
                ]
            ]
        ]];
        $this->assertEquals($expect, $result);

        $result = $parse_tree_node->invokeArgs(null, ['article/$page/$id', ['article', 'id']]);
        $expect = ['a', (object)[
            'path' => 'article/',
            'handler' => [],
            'children' => [
                '$' => (object)[
                    'path' => 'page',
                    'handler' => [],
                    'children' => [
                        '/' => (object)[
                            'path' => '/',
                            'handler' => [],
                            'children' => [
                                '$' => (object)[
                                    'path' => 'id',
                                    'handler' => ['article', 'id'],
                                    'children' => []
                                ]
                            ]
                        ]
                    ],
                ]
            ]
        ]];
        $this->assertEquals($expect, $result);

        $result = $parse_tree_node->invokeArgs(null, ['$id/page_$page', ['id', 'page']]);
        $expect = ['$', (object)[
            'path' => 'id',
            'handler' => [],
            'children' => [
                '/' => (object)[
                    'path' => '/page_',
                    'handler' => [],
                    'children' => [
                        '$' => (object)[
                            'path' => 'page',
                            'handler' => ['id', 'page'],
                            'children' => []
                        ]
                    ],
                ]
            ]
        ]];
        $this->assertEquals($expect, $result);

        $result = $parse_tree_node->invokeArgs(null, ['$id', ['index', 'id']]);
        $expect = ['$', (object)[
            'path' => 'id',
            'handler' => ['index', 'id'],
            'children' => []
        ]];
        $this->assertEquals($expect, $result);
    }

    public function testAddRoute()
    {
        $parse_tree_node = self::getMethod(Route::class, 'addRoute');
        $parse_tree_node->invokeArgs(null, ['GET', 'article/{id}', ['article', 'id']]);
        $expect = (object)[
            'path' => '/',
            'handler' => [],
            'children' => [
                'a' => (object)[
                    'path' => 'article/',
                    'handler' => [],
                    'children' => [
                        '$' => (object)[
                            'path' => 'id',
                            'handler' => ['article', 'id'],
                            'children' => []
                        ]
                    ],
                ]
            ]
        ];
        $this->assertEquals($expect, Route::$tree['GET']);

        $parse_tree_node->invokeArgs(null, ['GET', 'article/{page}/list', ['article', 'list']]);
        $expect = (object)[
            'path' => '/',
            'handler' => [],
            'children' => [
                'a' => (object)[
                    'path' => 'article/',
                    'handler' => [],
                    'children' => [
                        '$' => (object)[
                            'path' => 'id|page',
                            'handler' => ['article', 'id'],
                            'children' => [
                                '/' => (object)[
                                    'path' => '/list',
                                    'handler' => ['article', 'list'],
                                    'children' => [ ]
                                ]
                            ]
                        ]
                    ],
                ]
            ]
        ];
        $this->assertEquals($expect, Route::$tree['GET']);

        $parse_tree_node->invokeArgs(null, ['GET', 'art/{id}', ['article', 'id']]);
        $expect = (object)[
            'path' => '/',
            'handler' => [],
            'children' => [
                'a' => (object)[
                    'path' => 'art',
                    'handler' => [],
                    'children' => [
                        '/' => (object)[
                            'path' => '/',
                            'handler' => [],
                            'children' => [
                                '$' => (object)[
                                    'path' => 'id',
                                    'handler' => ['article', 'id'],
                                    'children' => [],
                                ]
                            ]
                        ],
                        'i' => (object)[
                            'path' => 'icle/',
                            'handler' => [],
                            'children' => [
                                '$' => (object)[
                                    'path' => 'id|page',
                                    'handler' => ['article', 'id'],
                                    'children' => [
                                        '/' => (object)[
                                            'path' => '/list',
                                            'handler' => ['article', 'list'],
                                            'children' => []
                                        ]
                                    ]
                                ]
                            ]
                        ],
                    ],
                ]
            ]
        ];
        $this->assertEquals($expect, Route::$tree['GET']);

        $parse_tree_node->invokeArgs(null, ['GET', 'art', ['article', 'list']]);
        $expect = (object)[
            'path' => '/',
            'handler' => [],
            'children' => [
                'a' => (object)[
                    'path' => 'art',
                    'handler' => ['article', 'list'],
                    'children' => [
                        '/' => (object)[
                            'path' => '/',
                            'handler' => [],
                            'children' => [
                                '$' => (object)[
                                    'path' => 'id',
                                    'handler' => ['article', 'id'],
                                    'children' => [],
                                ]
                            ]
                        ],
                        'i' => (object)[
                            'path' => 'icle/',
                            'handler' => [],
                            'children' => [
                                '$' => (object)[
                                    'path' => 'id|page',
                                    'handler' => ['article', 'id'],
                                    'children' => [
                                        '/' => (object)[
                                            'path' => '/list',
                                            'handler' => ['article', 'list'],
                                            'children' => []
                                        ]
                                    ]
                                ]
                            ]
                        ],
                    ],
                ]
            ]
        ];

        $parse_tree_node->invokeArgs(null, ['GET', 'comment/{page}', ['comment', 'list']]);
        $expect->children['c'] = (object)[
            'path' => 'comment/',
            'handler' => [],
            'children' => [
                '$' => (object)[
                    'path' => 'page',
                    'handler' => ['comment', 'list'],
                    'children' => []
                ]
            ]
        ];
        $this->assertEquals($expect, Route::$tree['GET']);

        $parse_tree_node->invokeArgs(null, ['GET', 'coco/{id}', ['coco', 'id']]);
        $expect->children['c'] = (object)[
            'path' => 'co',
            'handler' => [],
            'children' => [
                'm' => (object)[
                    'path' => 'mment/',
                    'handler' => [],
                    'children' => [
                        '$' => (object)[
                            'path' => 'page',
                            'handler' => ['comment', 'list'],
                            'children' => []
                        ]
                    ]
                ],
                'c' => (object)[
                    'path' => 'co/',
                    'handler' => [],
                    'children' => [
                        '$' => (object)[
                            'path' => 'id',
                            'handler' => ['coco', 'id'],
                            'children' => []
                        ]
                    ]
                ]
            ]
        ];
        $this->assertEquals($expect, Route::$tree['GET']);

        $parse_tree_node->invokeArgs(null, ['POST', '{article_id}/update', ['article', 'update']]);
        $expect = (object)[
            'path' => '/',
            'handler' => [],
            'children' => [
                '$' => (object)[
                    'path' => 'article_id',
                    'handler' => [],
                    'children' => [
                        '/' => (object)[
                            'path' => '/update',
                            'handler' => ['article', 'update'],
                            'children' => []
                        ],
                    ]
                ]
            ]
        ];
        $this->assertEquals($expect, Route::$tree['POST']);
    }

    /**
     * @depends testAddRoute
     */
    public function testSearch()
    {
        $result = Route::search('/article/10');
        $this->assertEquals(['article', 'id'], $result[0]);
        $this->assertArrayHasKey('id', $result[1]);

        $result = Route::search('/article/10/list');
        $this->assertEquals(['article', 'list'], $result[0]);
        $this->assertArrayHasKey('page', $result[1]);

        $result = Route::search('/article/10/20');
        $this->assertEquals(['article', 'id'], $result[0]);
        $this->assertArrayHasKey('id', $result[1]);

        $result = Route::search('/coco/0');
        $this->assertEquals(['coco', 'id'], $result[0]);
        $this->assertArrayHasKey('id', $result[1]);

        $result = Route::search('/123/update', 'post');
        $this->assertEquals(['article', 'update'], $result[0]);
        $this->assertArrayHasKey('article_id', $result[1]);

        $this->assertNull(Route::search('/cc')[0]);
    }
}
