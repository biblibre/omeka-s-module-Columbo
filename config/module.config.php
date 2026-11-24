<?php

namespace Columbo;

return [
    'controllers' => [
        'factories' => [
            'Columbo\Controller\Admin\Index' => Service\Controller\Admin\IndexControllerFactory::class,
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Columb\'O',
                'route' => 'admin/columbo',
                'class' => 'o-icon- fa-user-secret',
                'pages' => [
                    [
                        // Keeps the menu entry highlighted on a tab URL.
                        'route' => 'admin/columbo/tab',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'columbo' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/columbo',
                            'defaults' => [
                                '__NAMESPACE__' => 'Columbo\Controller\Admin',
                                'controller' => 'index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            // Page opened on a tab; also serves the former
                            // per-page URLs (/columbo/sites, /columbo/disk…).
                            'tab' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:section',
                                    'constraints' => [
                                        'section' => implode('|', Controller\Admin\IndexController::SECTIONS),
                                    ],
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'content' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/content/:section',
                                    'constraints' => [
                                        'section' => implode('|', Controller\Admin\IndexController::SECTIONS),
                                    ],
                                    'defaults' => [
                                        'action' => 'content',
                                    ],
                                ],
                            ],
                            'size' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/size/:target',
                                    'constraints' => [
                                        'target' => '[a-z][a-z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'size',
                                    ],
                                ],
                            ],
                            'download' => [
                                'type' => \Laminas\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/download',
                                    'defaults' => [
                                        'action' => 'download',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'js_translate_strings' => [
        'Loading…', // @translate
        'Could not load this tab.', // @translate
        'Retry', // @translate
        'Refresh this tab', // @translate
        'Download CSV', // @translate
        'Could not calculate size.', // @translate
        'Back to top', // @translate
        'Session expired: reload the page.', // @translate
        'B', // @translate
        'KB', // @translate
        'MB', // @translate
        'GB', // @translate
        'TB', // @translate
        'bytes', // @translate
    ],
];
