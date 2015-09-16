<?php
/**
 * Last Modified: 2015-09-12 
 * This module was ported from my Joomla extension
 * See: http://joomla.unlikelysource.org
 * 
 * @Author: doug@unlikelysource.com
 * @Throws: Exception ('Unable to Build Email Form')
 * @TODO:
 * 1. Integrate with Zend\Form\Element
 * 2. Integrate with Zend\Validator
 * 3. Integrate with Zend\Mail\Message
 * 
 */
namespace UnsrcEmailForm\Service;

use UnsrcEmailForm\Model\SimpleEmailForm;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use Zend\Captcha;
use Zend\Form\Element;
use Zend\Mail\Message;

class EmailForm
{
	
    use ServiceLocatorAwareTrait;
    
	// Initialize vars
	public $_msg		  = '';
	public $_output 	  = '';
    public $_langDir      = '';
	public $_field		  = array();
	public $_badEmail	  = '';
	public $_fromField	  = 1;
	public $_subjectField = 2;
	public $_fileMsg	  = '';
	public $_lang		  = 'en-GB';
	public $_transLang	  = array();
	public $_params		  = array();
	public $_testInfo	  = array();
    public $_fieldPrefix  = 'field';
    public $_csrfField    = '';

    /**
     * Builds form service based on config
     * 
     * @param string $config 
     * @param int $instance
     */
	public function build($config = 'default', $instance = 1) 
	{
		
		// Get params
        $this->_params = $this->getServiceLocator()->get('unsrc-config')[$config];
        
		// error checking for all incoming params
		$this->_params['instance']		= $instance;
		$this->_params['redirectURL']   = trim($this->_params['redirectURL']);
		$this->_params['col2space']		= (int) $this->_params['col2space'];
		$this->_params['uploadActive'] 	= (int) $this->_params['uploadActive'];
		$this->_params['uploadAllowed'] = strtolower(trim($this->_params['uploadAllowed']));
		$this->_params['cssClass']		= strip_tags(trim($this->_params['cssClass']));
		$this->_params['cssClass']		= ($this->_params['cssClass']) ? $this->_params['cssClass'] : 'mod_sef';
		$this->_params['autoReset'] 	= ($this->_params['autoReset']    == 'Y') ? TRUE : FALSE; 
		$this->_params['emailCheck']	= ($this->_params['emailCheck']   == 'Y') ? TRUE : FALSE;
		$this->_params['addBrToEmail']	= ($this->_params['addBrToEmail'] == 'Y') ? TRUE : FALSE;

        // build hash field name
        $this->_csrfField   = $this->_fieldPrefix . '_oneTime_' . $this->_params['instance'];
        
		// label alignment
		switch (strtoupper($this->_labelAlign)) {
			case 'C' :
				$this->_params['labelAlign'] = 'center';
				break;
			case 'R' :
				$this->_params['labelAlign'] = 'right';
				break;
			default :
				$this->_params['labelAlign'] = 'left';
		}
        
		// 2011-12-03 DB: init CSS class properties (if set)
		$this->_params['tableClass']	= $this->_params['cssClass'] . "_table";
		$this->_params['trClass']		= $this->_params['cssClass'] . "_tr";
		$this->_params['thClass']		= $this->_params['cssClass'] . "_th";
		$this->_params['spaceClass']	= $this->_params['cssClass'] . "_space";
		$this->_params['tdClass']		= $this->_params['cssClass'] . "_td";
		$this->_params['inputClass']	= $this->_params['cssClass'] . "_input";
		$this->_params['captchaClass']  = $this->_params['cssClass'] . "_captcha";
        
		// Assign field params into array 
		$this->_field = array();
		for ($x = 1; $x <= $this->_params['maxFields']; $x++) {
			// build labels
			$key = $this->_fieldPrefix . $x;
			// 2010-12-12 DB: added check to see if any values + set defaults
			$a = trim($this->_params[$key]['active']);		// Yes / No / Required / Hidden
			$v = trim($this->_params[$key]['value']);
			$z = trim($this->_params[$key]['size']);
			$m = trim($this->_params[$key]['maxx']);
			$l = trim($this->_params[$key]['label']);
			$f = trim($this->_params[$key]['from']);		// From / Subject / Normal / textArea / Drop / Radio / Checkbox
			$s = trim($this->_params[$key]['ckRfmt']);      // separator for radio / checkbox only
			$p = trim($this->_params[$key]['ckRpos']);      // label for radio / checkbox before or after only
			// all fields
			$this->_field[$x]['value'] 	= (isset($v)) ? $v : '';		// gets overwritten for Dropdown select / Radio / Checkbox
			$this->_field[$x]['size'] 	= (isset($z)) ? (int) $z : 40;	// gets overwritten for textarea fields
			$this->_field[$x]['error'] 	= '';
			$this->_field[$x]['maxx']	= (isset($m)) ? (int) $m : 255;
			$this->_field[$x]['label'] 	= (isset($l)) ? $l : $x . ':';
			$this->_field[$x]['ckRfmt'] = (isset($s) && stripos('-HVC', $s)) ? strtoupper($s) : 'C';
			$this->_field[$x]['ckRpos'] = (isset($p) && stripos('-BA', $p)) ? strtoupper($p) : 'A';
			// active
			if (isset($a) && $a) {
				$a = trim(strtoupper($a));
				if (strpos('-RYNH', $a)) {
					$this->_field[$x]['active'] =  $a;
				} else {
					$this->_field[$x]['active'] =  'N';
				}
			} else {
				$this->_field[$x]['active'] =  'N';
			}
			// from field (also used to determine field type only for active fields
			if (isset($f) && $f && $this->_field[$x]['active'] !== 'N') {
				$f = trim(strtoupper($f));
				// From Subject Normal textArea Dropdown Radio Checkbox User
				if (strpos('-FSNADRCU', $f)) {
					$this->_field[$x]['from'] =  $f;
					// identify "from" & "subject" fields
					switch ($f) {
						case 'F' :	// From
							$this->_fromField = $x;
							break;
						case 'S' :	// Subject
							$this->_subjectField = $x;
							break;
						case 'A' :	// textArea
							$this->_field[$x]['size'] 	= (isset($z) && strpos($z, ',')) ? $z : '4,40';
							break;
						case 'D' :	// Dropdown select
						case 'R' :	// Radio
						case 'C' :	// Checkbox
							// opts used for select / radio / checkbox fields
							// overwrite 'value' with an array of value=visible key pairs
							if (isset($v) && $v) {
								if (strpos($v, ',')) {
									$vTmp = explode(',', $v);
								} else {
									$vTmp = array($v);
								}
								$newArray = array();
								foreach ($vTmp as $item) {
									if (strpos($item, '=')) {
										list($value, $visible) = explode('=', $item);
									} elseif ($item) {
										$value   = $item;
										$visible = $item;
									} else {
										$value   = '---';
										$visible = '---';
									}
									$newArray[trim($value)] = trim($visible);
								}
								$this->_field[$x]['value'] = $newArray;
							} else {
								$this->_field[$x]['value'] = array('---' => '---');
							}
							break;
						default :
							// nothing
					}
				} else {
					$this->_field[$x]['from'] =  'N';
				}
			} else {
				$this->_field[$x]['from'] =  'N';
			}
		}
			
		// Set email object params
		$this->_msg 		 	= new SimpleEmailForm();
		$this->_msg->dir 	 	= $this->_params['emailFile'];
		$this->_msg->subject 	= $this->_params['subjectline']; 
		$this->_msg->copyMe	 	=  0;	// NOTE: depends on what user selects
		$this->_msg->fromName 	= $this->_params['fromName'];
		$this->_msg->copyMeAuto = ($this->_params['copymeAuto'] == 'Y') ? 1 : 0;
		// TODO: check for multiple targets, and, if so, convert to array()
		$to  = trim($this->_params['emailTo']);
		$cc  = trim($this->_params['emailCC']);
		$bcc = trim($this->_params['emailBCC']);
		$this->_msg->to  = (preg_match('/[\s,]+/', $to))  ? preg_split('/[\s,]+/', $to)  : array($to);
		if ($cc)  { $this->_msg->cc  = (preg_match('/[\s,]+/', $cc))  ? preg_split('/[\s,]+/', $cc)  : array($cc); }
		if ($bcc) { $this->_msg->bcc = (preg_match('/[\s,]+/', $bcc)) ? preg_split('/[\s,]+/', $bcc) : array($bcc); }
		// 2012-2-7 DB: add optional Reply-To field
		$this->_msg->replyToActive 	= $this->_params['replytoActive'];
		if ($this->_msg->replyToActive == 'Y') {
			$this->_msg->replyTo 	= array($this->_params['emailReplyTo'], '');
		} else {
			$this->_msg->replyTo 	= '';
		}
		
		// params into testInfo
		if ($this->_params['testMode'] == 'Y') {
			$this->_testInfo[] = '<br />Params: ' . var_export($this->_params, TRUE) . PHP_EOL;
		}
        
		// Load language files
		// i.e. tr-TR.mod_simpleemailform.ini
		$this->_params['lang'] = $this->_params['defaultLang'];
		$langFile = dirname(__FILE__) 
                  . DIRECTORY_SEPARATOR 
                  . '..' 
				  . DIRECTORY_SEPARATOR 
                  . 'Languages' 
				  . DIRECTORY_SEPARATOR 
                  . $this->_lang . '.mod_simpleemailform.ini';
		if (file_exists($langFile)) {
			$this->_transLang = parse_ini_file($langFile);
		} else {
			$langFile = dirname(__FILE__) 
                      . DIRECTORY_SEPARATOR 
                      . '..' 
					  . DIRECTORY_SEPARATOR 
                      . 'Languages'
				      . DIRECTORY_SEPARATOR 
                      . 'en-GB.mod_simpleemailform.ini';
			$this->_transLang = parse_ini_file($langFile);
		}

	}

	/**
	 * Assumes $this->_transLang[] has been defined
	 */
	public function uploadAttachment($dir, $uploadAllowed, $errorTxtColor, $successTxtColor, &$message, $fieldNum = 1)
	{
		$message = '';
		$result  = '';
		$allowed = FALSE;
		$fieldLabel = 'upload_' . $fieldNum . '_' . $this->_params['instance'];
		// Capture filename
		$fn = (isset($_FILES[$fieldLabel]['name'])) 
			   ? basename(strip_tags($_FILES[$fieldLabel]['name'])) : '';
		// use regex to check for allowed filenames
		if ($fn) {
			// Get filename extension
			$pos = strrpos($fn, '.');		// last occurrence of '.'
			$ext = strtolower(substr($fn, $pos + 1));
			if ($uploadAllowed) {
				if (strpos($uploadAllowed, $ext)) {
					$allowed = TRUE;
				} else {
					$allowed = FALSE;
				}
			} else {
				$allowed = TRUE;
			}
			if ($allowed) {
				// Check to see if upload parameter specified
				if ( $_FILES[$fieldLabel]['error'] == UPLOAD_ERR_OK ) {
					// Check to make sure file uploaded by upload process
					if ( is_uploaded_file ($_FILES[$fieldLabel]['tmp_name'] ) ) {
						// Set filename to current directory
						$copyfile = $dir . DIRECTORY_SEPARATOR . $fn;
						// Copy file
						if ( move_uploaded_file ($_FILES[$fieldLabel]['tmp_name'], $copyfile) ) {
							// Save name of file
							$message .= $this->formatErrorMessage($successTxtColor, $this->_transLang['MOD_SIMPLEEMAILFORM_upload_success'], $fn);
							$result = $fn;
						} else {
							// Trap upload file handle errors
							$message .= $this->formatErrorMessage($errorTxtColor, $this->_transLang['MOD_SIMPLEEMAILFORM_upload_unable'], $fn);
						}
					} else {
						// Failed security check
						$message .= $this->formatErrorMessage($errorTxtColor, $this->_transLang['MOD_SIMPLEEMAILFORM_upload_failure'], $fn);
					}
				} else {
					// Failed security check
					$message .= $this->formatErrorMessage($errorTxtColor, $this->_transLang['MOD_SIMPLEEMAILFORM_upload_error'], $fn);
				}
			} else {
				// Failed regex
				$message .= $this->formatErrorMessage($errorTxtColor, $this->_transLang['MOD_SIMPLEEMAILFORM_disallowed_filename'], $fn);
			}
		}
		return $result;
	}

	// uses $this->_labelAlign, $this->_col2space, $this->_errorTxtColor, $this->_field, $this->_maxFields
	public function formatRow()
	{
		$output = '';
		for ($x = 1; $x <= $this->_params['maxFields']; $x++) {
			if (stripos('-YR', $this->_field[$x]['active'])) {
				$name = $this->_fieldPrefix . $x . '_' . $this->_params['instance'];
				$value = (isset($_POST[$name])) ? $_POST[$name] : '';
                // 2015-04-23 DB: added htmlspecialchars()
				if (is_array($value)) {
					foreach ($value as $key => $item) {
						$value[$key] = htmlspecialchars($item);
					}
				} elseif (strpos($value, '@')) {
					// prevents Joomla from reformatting using javascript
					$value = str_replace(array('@','<','>',';'), array('&#64;','','',''), $value);
				} else {
                    $value = htmlspecialchars($value);
                }
				// 2011-12-03 DB: added CSS classes for input, table, row, th and td
				$row = '';
				$row .= '<tr class="' . $this->_params['trClass']   . '">';
				// labels
				$row .= sprintf("<th align='%s' style='text-align:%s;' class='%s'>%s</th>", 
								$this->_params['labelAlign'], 
								$this->_params['labelAlign'], 
								$this->_params['thClass'], 
								$this->_field[$x]['label']);
				// space between cols
				$row .= "<td class='" . $this->_params['spaceClass'] . "' width='" . $this->_params['col2space']  . "'>&nbsp;</td>";
				// input field
				$row .= "<td class='" . $this->_params['tdClass']    . "'>";
				// check field type
				switch ($this->_field[$x]['from']) {
					case 'A' :
						// if rows and cols values not set, establish defaults
						$inputClass = $this->_params['inputClass'] . '_textarea';
						list($numRows, $numCols) = explode(',', $this->_field[$x]['size']);
						$numRows = ($numRows) ? $numRows : 4;
						$numCols = ($numCols) ? $numCols : 40;
						$row .= sprintf('<textarea name="%s" id="%s" rows="%d" cols="%d" class="%s" placeholder="%s">%s</textarea>',
										$name, $name, $numRows, $numCols, $inputClass, $this->_field[$x]['value'], $value);
						break;
					case 'D' :
						// drop down select list
						$inputClass = $this->_params['inputClass'] . '_select';
						$row .= sprintf('<select name="%s" id="%s" class="%s">' . PHP_EOL, $name, $name, $inputClass);
						foreach ($this->_field[$x]['value'] as $key => $visible) {
                            $key     = htmlspecialchars($key);
                            $visible = htmlspecialchars($visible);
							$row .= "<option value='$key'>$visible</option>\n";
						}
						$row .= "</select>\n";
						break;
					case 'R' :
						// radio button list
						$value = (is_array($value)) ? $value : array();
						$row .= $this->buildCheckRadioField($this->_field[$x], $name, 'radio', $value);
						break;
					case 'C' :
						// checkbox list
						$value = (is_array($value)) ? $value : array();
						$row .= $this->buildCheckRadioField($this->_field[$x], $name, 'checkbox', $value);
						break;
					case 'F' :
						// 2013-11-07 DB: added HTML5 email type field if email checking is enabled
						// from field
						$type = ($this->_emailCheck) ? 'email' : 'text';
						$row .= sprintf('<input type="%s" name="%s" id="%s" size="%d" value="%s" maxlength="%d" class="%s"/>',
										$type, 
										$name, 
										$name, 
										$this->_field[$x]['size'], 
										$value, 
										$this->_field[$x]['maxx'], 
										$this->_params['inputClass']);
						break;
					// 2014-05-21 DB: added User Defined field ... for customization
					case 'U' :
						// 2014-05-21 DB: in this example we have an HTML5 "phone" field, which accepts only phone numbers
						$row .= sprintf('<input type="phone" name="%s" id="%s" size="%d" value="%s" maxlength="%d" class="%s" placeholder="%s"/>',
										$name, 
										$name, 
										$this->_field[$x]['size'], 
										$value, 
										$this->_field[$x]['maxx'], 
										$this->_params['inputClass'],
                                        // 2015-04-23 DB: added htmlspecialchars()
										htmlspecialchars($this->_field[$x]['value']));
					default :
						$row .= sprintf('<input type="text" name="%s" id="%s" size="%d" value="%s" maxlength="%d" class="%s" placeholder="%s"/>',
										$name, 
										$name, 
										$this->_field[$x]['size'], 
										$value, 
										$this->_field[$x]['maxx'], 
										$this->_params['inputClass'],
                                        // 2015-04-23 DB: added htmlspecialchars()
										htmlspecialchars($this->_field[$x]['value']));
				}
				$row .= ($this->_field[$x]['error']) 
					  ? $this->formatErrorMessage($this->_params['errorTxtColor'], $this->_field[$x]['error']) 
					  : '';
				$row .= "</td>";
				$row .= "</tr>\n";
				$output .= $row;
			}
		}
		return $output;
	}

	// uses $this->_inputClass and $this->_(tr|th|td|table)Class to build CSS class
	public function buildCheckRadioField($field, $name, $type, $value)
	{
		$output 	= '';
		$width 		= '';
		$inputClass = $this->_params['inputClass'] . '_' . $type;
		$tblClass 	= $this->_params['tableClass'] . '_' . $type;
		$trClass 	= $this->_params['trClass']    . '_' . $type;
		$thClass 	= $this->_params['thClass']    . '_' . $type;
		$tdClass 	= $this->_params['tdClass']    . '_' . $type;
		$cssLabel   = $this->_params['cssClass']   . '_' . $type . '_label';
		$cssInput   = $this->_params['cssClass']   . '_' . $type . '_input';
		if ($field['size']) {
			$width = 'style="width:' . $field['size'] . 'px;"';
		}
		$format = $field['ckRfmt'] . $field['ckRpos'];
		switch ($format) {
			case 'HB' :		// horizontal, label before
				$output .= "<table class='" . $tblClass . "'><tr class='" . $trClass . "'>\n";
				foreach ($field['value'] as $key => $visible) {
					$checked = (in_array($key, $value, TRUE)) ? 'checked' : '';
					$list = array($thClass, $width, $this->padVisible($visible), $tdClass, $type, $name, $name, $key, $key, $inputClass, $checked);
					$output .= vsprintf('<th class="%s" %s>%s</th><td class="%s"><input type="%s" name="%s[]" id="%s_%s" value="%s" class="%s" %s/></td>', $list);
				}
				$output .= "</tr></table>\n";
				break;
			case 'HA' :		// horizontal, label after
				$output .= "<table class='" . $tblClass . "'><tr class='" . $trClass . "'>\n";
				foreach ($field['value'] as $key => $visible) {
					$checked = (in_array($key, $value, TRUE)) ? 'checked' : '';
					$list = array($tdClass, $type, $name, $name, $key, $key, $inputClass, $checked, $thClass, $width, $this->padVisible($visible));
					$output .= vsprintf('<td class="%s"><input type="%s" name="%s[]" id="%s_%s" value="%s" class="%s" %s/></td><th class="%s" %s>%s</th>', $list);
				}
				$output .= "</tr></table>\n";
				break;
			case 'VB' :		// vertical, label before
				$output .= "<table class='" . $tblClass . "'>\n";
				foreach ($field['value'] as $key => $visible) {
					$checked = (in_array($key, $value, TRUE)) ? 'checked' : '';
					$list = array($trClass, $thClass, $width, $this->padVisible($visible), $tdClass, $type, $name, $name, $key, $key, $inputClass, $checked);
					$output .= vsprintf('<tr class="%s"><th class="%s" %s>%s</th><td class="%s"><input type="%s" name="%s[]" id="%s_%s" value="%s" class="%s" %s/></td></tr>' . PHP_EOL, $list);
				}
				$output .= "</table>\n";
				break;
			case 'VA' :		// vertical, label after
				$output .= "<table class='" . $tblClass . "'>\n";
				foreach ($field['value'] as $key => $visible) {
					$checked = (in_array($key, $value, TRUE)) ? 'checked' : '';
					$list = array($trClass, $tdClass, $type, $name, $name, $key, $key, $inputClass, $checked, $thClass, $width, $this->padVisible($visible));
					$output .= vsprintf('<tr class="%s"><td class="%s"><input type="%s" name="%s[]" id="%s_%s" value="%s" class="%s" %s/></td><th class="%s" %s>%s</th></tr>' . PHP_EOL, $list);
				}
				$output .= "</table>\n";
				break;
			case 'CB' :		// use CSS label before
				$output .= '<span class="' . $cssLabel . '">';
				foreach ($field['value'] as $key => $visible) {
					$checked = (in_array($key, $value, TRUE)) ? 'checked' : '';
					$list = array($this->padVisible($visible), $type, $name, $name, $key, $key, $cssInput, $checked);
					$output .= vsprintf('%s<input type="%s" name="%s[]" id="%s_%s" value="%s" class="%s" %s/>', $list);
				}
				$output .= '</span>' . PHP_EOL;
				break;
			case 'CA' :		// use CSS label after
				$output .= '<span class="' . $cssLabel . '">';
				foreach ($field['value'] as $key => $visible) {
					$checked = (in_array($key, $value, TRUE)) ? 'checked' : '';
					$list = array($type, $name, $name, $key, $key, $cssInput, $checked, $this->padVisible($visible));
					$output .= vsprintf('<input type="%s" name="%s[]" id="%s_%s" value="%s" class="%s" %s/>%s', $list);
				}
				$output .= '</span>' . PHP_EOL;
				break;
			default :		// nothing defined
				$output .= "<table>";
				$output .= "<tr><td>Undefined</td></tr>\n";
				$output .= "</table>\n";
		}
		return $output;
	}

	public function padVisible($visible)
	{
		return '&nbsp;&nbsp;' . $visible . '&nbsp;&nbsp;';
	}
	
	public function sendResults(SimpleEmailForm &$msg, $field)
	{
		// 2012-02-15 db: override unwanted error messages originating from JMail
		ob_start();
		// Build Body
		$msg->body =  '';
		for ($x = 1; $x <= $this->_params['maxFields']; $x++) {
			$label = $this->_fieldPrefix . $x . '_' . $this->_params['instance'];
			// if "active" = hidden, pull in value automatically
			if ($field[$x]['active'] == 'H') {
				$msg->body .= "\n" . $field[$x]['label'] . ': ' . $field[$x]['value'];
			// otherwise pull value from $_POST
			} else {
				// 2013-04-20 DB: added check for array -- to account for checkboxes / multi-select
				$value = '';
				if (isset($_POST[$label])) {
					if (is_array($_POST[$label])) {
						$value = implode(" / ", $_POST[$label]);
					} else {
						$value = $_POST[$label];
					}
				}
                $break = ($this->_params['addBrToEmail']) ? "\n<br>" : "\n";
				$msg->body .= ($value) ? $break . $field[$x]['label'] . ': ' . htmlspecialchars($value) : '';
			}
		}
		// Strip slashes
		$msg->body = stripslashes($msg->body);	
		// Filter for \n in subject - 2010-05-03 DB
		$msg->subject = str_replace("\n",'',$msg->subject);
		// Send mail
		$message = new Message();
		//echo $message->dumpLanguage(); exit;
		$message->addTo($msg->to);
		$message->setSender($msg->from);
		$message->setSubject($msg->subject);
		$message->setBody($msg->body);
		// 2012-02-03 DB: added reply to field (has to be array())
		if ($msg->cc) 		{ $message->addCc($msg->cc); }
		if ($msg->bcc) 		{ $message->addBcc($msg->bcc); }
		if ($msg->replyTo) 	{ $message->addReplyTo($msg->replyTo); }
		// @TODO: 2015-09-07 DB: implement attachments
        /*
		if (count($msg->attachment) > 0) { 
			// Formulate FN for attachment
			foreach ($msg->attachment as $fn) {
				$fullPath = $msg->dir . DIRECTORY_SEPARATOR . $fn;
				$message->addAttachment($fullPath);
			} 
		}
        */
		try {
            $transport = $this->getServiceLocator()->get('unsrc-email-form-transport');
			if (!$sent = $transport->send($message)) {
				throw new \Exception($this->_transLang['MOD_SIMPLEEMAILFORM_error']);
			}
			$msg->copyMe = (isset($_POST['copyMe_' . $this->_params['instance']])) 
							? (int) $_POST['copyMe_' . $this->_params['instance']] : 0;
			// 2011-08-12 DB: added option for copyMeAuto
			if ($msg->copyMe || $msg->copyMeAuto) { 
				$message->ClearAllRecipients();
				$message->addRecipient($msg->from, $msg->fromName);
				if (!$sent = $transport->send($message)) {
					throw new \Exception($this->_transLang['MOD_SIMPLEEMAILFORM_error']);
				}
			}
			$result = TRUE;
		} catch (\Exception $e) {
			$result = FALSE;
			$msg->error = $this->_transLang['MOD_SIMPLEEMAILFORM_error'] . ': Mail Server';
			$msg->error .= '<br />' . $this->_transLang['MOD_SIMPLEEMAILFORM_email_invalid'];
			if ($this->_params['testMode'] == 'Y') {
				$this->_testInfo[] = '<br />' . $e->getMessage() . "\n";
				$this->_testInfo[] = '<br />' . $e->getTraceAsString() . "\n";
			}
		}
		// 2012-02-15 db: override unwanted error messages originating from JMail
		// 2012-03-07 DB: added test mode
		if ($this->_params['testMode'] == 'Y') {
			$this->_testInfo[] = '<br />' . ob_get_contents() . "\n";
			$this->_testInfo[] .= 'Mail Object:' . '<br />';
			$this->_testInfo[] .= '<pre>';
			$this->_testInfo[] .= var_export($this->_msg, TRUE);
			$this->_testInfo[] .= '</pre>';
		}
		ob_end_clean();
		return $result;
		
	}

	public function imageCaptcha()
	{
		$captcha        = new Element\Captcha('captcha');
		$captchaAdapter = new Captcha\Image($this->_params['captchaOptions']);
		$captcha->setCaptcha($captchaAdapter);
		return $captcha;
	}		

	public function textCaptcha()
	{
		$captcha        = new Element\Captcha('captcha');
		$captchaAdapter = new Captcha\Text($this->_params['captchaOptions']);
		$captcha->setCaptcha($captchaAdapter);
		return $captcha;
	}

	// render CAPTCHA
	public function buildCaptcha()
	{
		// Set CAPTCHA secret passphrase
		if ($this->_params['useCaptcha'] == 'I') {
			$captcha = $this->imageCaptcha(); 
		} else {
			$captcha = $this->textCaptcha();
		}
        $_SESSION['unsrc-captcha'] = $captcha->getCaptcha()->getWord();
		return $captcha;
	}

	// CAPTCHA match
	public function doesCaptchaMatch()
	{
		$match = FALSE;
		$userCaptchaField 	= $this->buildUserCaptchaField();
		if (isset($_POST[$userCaptchaField]) && isset($_SESSION['unsrc-captcha'])) {
			$match = ($_SESSION['unsrc-captcha'] === $_POST[$userCaptchaField]);
		}
		return $match;
	}

	public function formatErrorMessage($color, $message, $fn = '')
	{
		if ($fn) {
			$message = "<p><b><span style='color:$color;'>$message ($fn)</span></b></p>\n";
		} else {
			$message = "<p><b><span style='color:$color;'>$message</span></b></p>\n";
		}
		return $message;
	}

	public function autoResetForm()
	{
		foreach ($_POST as $key => $value) {
			$_POST[$key] = '';
		}
	}

	// Render file upload field
	public function buildFileUploadField($fieldNum = 1)
	{
		$inputClass = $this->_params['inputClass'] . '_upload';
		return "<br />"
			   . "<input "
					. "type=file "
					. "name='upload_" . $fieldNum . '_' . $this->_params['instance'] . "' "
					. "id='upload_" . $fieldNum . '_' . $this->_params['instance'] . "' "
					. "enctype='multipart/form-data' "
					. "class='" . $inputClass . "'" 
					. " />";
	}

    // 2015-04-23 DB: builds 1 time hash + stores in $_SESSION
    public function buildCsrfHash()
    {
        $server = (isset($_SERVER['REMOTE_ADDR']))     ? $_SERVER['REMOTE_ADDR']     : microtime();
        $uagent = (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : microtime();
        $hash = md5($server . microtime()) . sha1($uagent . microtime() . $this->_params['hashKey']);
        $_SESSION[$this->_csrfField] = $hash;
        return $hash;
    }

    // 2015-04-23 DB: accepts hash from form and compares to session
    public function compareCsrfHash()
    {
        $hashFromForm = (isset($_POST[$this->_csrfField])) ? $_POST[$this->_csrfField] : '';
        $result = ($_SESSION[$this->_csrfField] == $hashFromForm);
        $_SESSION[$this->_csrfField] = sha1(date('Y-m-d-H-i-s'));
        return $result;
    }

    // magic "getter" for _params[] values
    public function __get($key)
    {
        if (strpos($key, '_') === 0) $key = substr($key, 1);
        return (isset($this->_params[$key])) ? $this->_params[$key] : NULL;
    }
    
    public function __set($key, $value)
    {
        $this->_params[$key] = $value;
    }
    
}
