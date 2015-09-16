<?php
return [
    'navigation' => [
        'default' => [
    	   ['label' => 'Email Form', 'route' => 'unsrc-email-form', 'resource' => 'email-form', 'order' => 2000],
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            // USAGE (in a view template):
            // echo $this->unsrcRender([$config, $instance, $template, $action, $messages])
            'unsrcRender' => 'UnsrcEmailForm\Helper\RenderForm',
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            // USAGE (in a controller action):
            // $viewModel = $this->unsrcProcess([$config, $instance, $template, $action, $messages])
            'unsrcProcess' => 'UnsrcEmailForm\Plugin\ProcessForm',
        ],
    ],
    'view_manager' => [
        'template_map' => include __DIR__ . '/../template_map.php',
    ],
];
