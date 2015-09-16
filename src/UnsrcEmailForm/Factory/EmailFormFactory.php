<?php
namespace UnsrcEmailForm\Factory;

use UnsrcEmailForm\Module;
use UnsrcEmailForm\Service\EmailForm;
use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Creates an instance of UnsrcEmailForm\Service\EmailForm using $config
 * 
 * @author db
 *
 */
class EmailFormFactory implements AbstractFactoryInterface
{
    public function canCreateServiceWithName(
                            ServiceLocatorInterface $sm, 
                            $name, 
                            $requestedName) 
    {
        $canCreate = FALSE;
        if (strpos($requestedName, Module::UNSRC_EMAILFORM_PREFIX) === 0) {
            if ($this->getConfig($requestedName, $sm)) $canCreate = TRUE;
        }
        return $canCreate;
    } 

    public function createServiceWithName(
                            ServiceLocatorInterface $sm, 
                            $name, 
                            $requestedName) 
    {
        $service = new EmailForm();
        $service->setServiceLocator($sm);
        return $service;
    }

    protected function getConfig($requestedName, $sm)
    {
        $key    = substr($requestedName, strlen(Module::UNSRC_EMAILFORM_PREFIX));
        $config = $sm->get('unsrc-config');
        return isset($config[$key]);
    }
    
}
