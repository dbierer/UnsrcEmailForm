<?php
/**
 * Last Modified: 2015-09-12 
 * This module was ported from my Joomla extension
 * See: http://joomla.unlikelysource.org
 * 
 * @Author: doug@unlikelysource.com
 * @TODO:
 * 1. Integrate with Zend\Form\Element
 * 2. Integrate with Zend\Validator
 * 3. Integrate with Zend\Mail\Message
 * 
 */
namespace UnsrcEmailForm\Helper;

use UnsrcEmailForm\Module;
use Zend\View\Helper\AbstractHelper;
use Zend\View\Model\ViewModel;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class RenderForm extends AbstractHelper implements ServiceLocatorAwareInterface
{
    
    use ServiceLocatorAwareTrait;
    
    /**
     * Renders form using config block defined by $config
     * (located under the "Config" service key "unsrc-config")
     * see module/UnsrcEmailForm/config/unsrc.emailform.local.php.dist for more info
     * 
     * @param string $config
     * @param int $instance
     * @param string $template
     * @param string $action
     * @param string $messages
     * @return string HTML <form>xxxx</form>
     */
    public function __invoke($config = 'default', 
                             $instance = 1, 
                             $template = 'unsrc-email-form/index/index', 
                             $action = '', 
                             $messages = NULL)
    {
        session_start();
        $service = $this->getServiceLocator()->get(Module::UNSRC_EMAILFORM_PREFIX . $config);
        $service->build($config, $instance);
        $viewModel = new ViewModel(['service'  => $service,
                                    'instance' => $instance,
                                    'action'   => $action, 
                                    'message'  => $messages]);
        $viewModel->setTemplate($template);
        return $this->getView()->render($viewModel);        
    }
    
}
