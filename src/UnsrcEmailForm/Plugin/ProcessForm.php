<?php
namespace UnsrcEmailForm\Plugin;

/**
 * Processes form created by UnsrcEmailForm module
 * - Performs limited validation
 * 
 * @TODO: enable file uploads
 * @TODO: incorporate Zend\Validator
 * @TODO: replace this with a more appropriate ZF2 Response: header('Location: ' . $service->_redirectURL); exit;
 */
 
use UnsrcEmailForm\Module;
use Zend\View\Model\ViewModel;
use Zend\Validator\EmailAddress;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class ProcessForm extends AbstractPlugin
{
    
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
     * @return Zend\View\Model\ViewModel $viewModel
     */
    public function __invoke($config = 'default', 
                             $instance = 1, 
                             $action = '', 
                             $messages = NULL)
    {
        // 2015-04-24 DB: test CSRF hash & see if submit button pressed
        session_start();
        $message   = '';
        $service   = $this->getController()->getServiceLocator()->get(Module::UNSRC_EMAILFORM_PREFIX . $config);
        $service->build($config, $instance);
        $request   = $this->getController()->getRequest();
        $viewModel = new ViewModel(['service'  => $service,
                                    'instance' => $instance,
                                    'action'   => $action, 
                                    'message'  => $messages]);
        if (!$request->isPost()) {
            return $viewModel;
        }
        if ($request->fromPost('submit_' . $instance) && $service->compareCsrfHash()) {

            // upload attachment (if active) and add to msg object
            // @TODO: enable file uploads
            /*
            if ($service->_uploadActive) {
                // 2013-02-15 DB: added ability to have > 1 upload field
                for ($x = 1; $x <= $service->_uploadActive; $x++) {
                    $service->_msg->attachment[] = $service->uploadAttachment($service->_msg->dir, 
                                                                        $service->_uploadAllowed, 
                                                                        $service->_errorTxtColor, 
                                                                        $service->_successTxtColor, 
                                                                        $service->_fileMsg, 
                                                                        $x						// file upload field #
                    );
                }
            }
            */
            
            // Check to see if "from" email address has been posted
            $fieldLabel = $service->_fieldPrefix . $service->_fromField . '_' . $instance;
            if ($request->fromPost($fieldLabel) 
                && $service->_field[$service->_fromField]['active'] != 'H') {
                $service->_msg->from = strip_tags($_POST[$fieldLabel]);
            } else {
                // Set default "from" field value
                $service->_msg->from = $service->_field[$service->_fromField]['value'];
            }
            // Check subject
            $fieldLabel = $service->_fieldPrefix 
                        . $service->_subjectField . '_' . $instance;
            if ($request->fromPost($fieldLabel) 
                && $service->_field[$service->_subjectField]['active'] != 'H') {
                $service->_msg->subject = strip_tags($_POST[$fieldLabel]);
            } else {
                $service->_msg->subject = $service->_field[$service->_subjectField]['value'];
            }
            
            // Check to see if "from" email address contains "&#64;"
            $service->_msg->from = (isset($service->_msg->from)) 
                                       ? str_ireplace('&#64;', '@', $service->_msg->from) : '';
            
            // validate email only if "Email Check" is set to "yes"
            $requiredCheck = TRUE;
            if ($service->_emailCheck) {
                $emailValidator = new EmailAddress();
                $email_ok = isset($service->_msg->from) 
                                  && $service->_msg->from 
                                  && $emailValidator->isValid($service->_msg->from);
                if (!$email_ok) {
                    $requiredCheck = FALSE;
                    $service->_field[$service->_fromField]['error'] = $service->_transLang['MOD_SIMPLEEMAILFORM_email_invalid'] 
                                                              . ' ' 
                                                              . $service->_field[$service->_fromField]['label'];
                }
            }
            // Check required fields
            for ($x = 1; $x <= $service->_maxFields; $x++) {
                $fieldLabel = $service->_fieldPrefix . $x . '_' . $instance;
                if ($service->_field[$x]['active'] == 'R') {
                    if (!$request->fromPost($fieldLabel)) {
                        $requiredCheck = FALSE;
                        $service->_field[$x]['error'] = $service->_transLang['MOD_SIMPLEEMAILFORM_required_field'] . ' ' . $service->_field[$x]['label'];
                    }
                }
            }
            // proceed only if required fields all check out
            if ($requiredCheck) {
                $sendResultsFlag = TRUE;
                // Validate captcha if active
                if ($service->_useCaptcha != 'N') {
                    // test mode
                    if ($service->_testMode == 'Y') {
                        $service->_testInfo[] = '<br />CAPTCHA components: ' . $service->buildCaptchaHashComponents() . PHP_EOL;
                    }
                    // does form CAPTCHA fields hash match full hash?
                    if (!$service->doesCaptchaMatch()) {
                        $sendResultsFlag = FALSE;
                        $message .=  $service->formatErrorMessage($service->_errorTxtColor, $service->_transLang['MOD_SIMPLEEMAILFORM_form_reenter']);
                    }
                }
                // send results if OK
                if ($sendResultsFlag) {
                    if ($service->sendResults($service->_msg, $service->_field)) {
                        $message .=  $service->formatErrorMessage($service->_successTxtColor, $service->_transLang['MOD_SIMPLEEMAILFORM_form_success']);
                        if ($service->_redirectURL !== '') { 
                            // @TODO: replace this with a more appropriate ZF2 Response
                            header('Location: ' . $service->_redirectURL);
                            exit;
                        }
                        if ($service->_autoReset) { 
                            $service->autoResetForm(); 
                        }
                    } else {
                        $message .=  $service->formatErrorMessage($service->_errorTxtColor, $service->_transLang['MOD_SIMPLEEMAILFORM_form_unable']);
                    }
                }
                // Add any mail server error messages (is blank if none)
                $message .= $service->_msg->error;
            }
        // actions if submit button has not been clicked
        } else {
            // reset form
            if ($request->fromPost('reset_' . $instance)) {
                $service->autoResetForm();
            }	
            // Check "from" email address & subject
            for ($x = 1; $x <= $service->_maxFields; $x++) {
                if ($service->_field[$x]['from'] == 'F') {
                    $service->_msg->from = $service->_field[$x]['value'];
                } elseif ($service->_field[$x]['from'] == 'S') {
                    $service->_msg->subject = $service->_field[$x]['value'];
                }
            }			
        }

        return $viewModel;
        
    }
    
}
