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
                'resource' => 'Columbo\Controller\Admin\Index',
                'privilege' => 'index',
                'class' => 'o-icon- fa-user-secret',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'columbo' => [
                        'type' => \Laminas\Router\Http\Segment::class,
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
                            'download' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/download',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Columbo\Controller\Admin',
                                        'controller' => 'index',
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
];
