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
                'route' => 'admin/columbo/sites',
                'class' => 'o-icon- fa-user-secret',
                'pages' => [
                    [
                        'label' => 'Sites', // @translate
                        'route' => 'admin/columbo/sites',
                    ],
                    [
                        'label' => 'Resources', // @translate
                        'route' => 'admin/columbo/resources',
                    ],
                    [
                        'label' => 'Resource metadata', // @translate
                        'route' => 'admin/columbo/metadata',
                    ],
                    [
                        'label' => 'Users', // @translate
                        'route' => 'admin/columbo/users',
                    ],
                    [
                        'label' => 'Modules', // @translate
                        'route' => 'admin/columbo/modules',
                    ],
                    [
                        'label' => 'Disk usage', // @translate
                        'route' => 'admin/columbo/disk',
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
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/columbo',
                        ],
                        'may_terminate' => false,
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
                            'sites' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/sites',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Columbo\Controller\Admin',
                                        'controller' => 'index',
                                        'action' => 'sites',
                                    ],
                                ],
                            ],
                            'resources' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/resources',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Columbo\Controller\Admin',
                                        'controller' => 'index',
                                        'action' => 'resources',
                                    ],
                                ],
                            ],
                            'metadata' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/metadata',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Columbo\Controller\Admin',
                                        'controller' => 'index',
                                        'action' => 'metadata',
                                    ],
                                ],
                            ],
                            'users' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/users',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Columbo\Controller\Admin',
                                        'controller' => 'index',
                                        'action' => 'users',
                                    ],
                                ],
                            ],
                            'modules' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/modules',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Columbo\Controller\Admin',
                                        'controller' => 'index',
                                        'action' => 'modules',
                                    ],
                                ],
                            ],
                            'disk' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/disk',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Columbo\Controller\Admin',
                                        'controller' => 'index',
                                        'action' => 'disk',
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
