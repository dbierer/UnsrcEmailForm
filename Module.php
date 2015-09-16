<?php
namespace UnsrcEmailForm;

use UnsrcEmailForm\Controller\IndexController;
use UnsrcEmailForm\Service\EmailForm;
use UnsrcEmailForm\Helper\RenderForm;

class Module
{
    
    const UNSRC_EMAILFORM_PREFIX = 'unsrcEmailForm_';
    
    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\ClassMapAutoloader' => [__DIR__ . '/autoload_classmap.php'],
            'Zend\Loader\StandardAutoloader' => ['namespaces' => [__NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__]],
        ];
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getServiceConfig()
    {
        return [
            'factories' => [
                'unsrc-email-form-transport' => function ($sm) { 
                    return new \Zend\Mail\Transport\Sendmail(); 
                },
                'unsrc-config' => function ($sm) { 
                    $config = $sm->get('Config');
                    return $config['unsrc-config'];
                },
            ],
            'abstract_factories' => [ 
                'UnsrcEmailForm\Factory\EmailFormFactory',
            ],
        ];
    }

}
