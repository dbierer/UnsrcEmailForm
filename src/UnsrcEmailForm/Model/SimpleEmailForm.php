<?php
namespace UnsrcEmailForm\Model;

class SimpleEmailForm {
	public $to 			= NULL;
	public $from 		= NULL;
	public $fromName	= NULL;
	public $cc 			= NULL;
	public $bcc 		= NULL;
	public $replyTo 	= NULL;
	public $replyToActive = FALSE;
	public $attachment 	= array();
	public $subject 	= '';
	public $body 		= '';
	public $dir 		= '';
	public $copyMe 		= '';
	public $copyMeAuto 	= '';
	public $error 		= '';
}
