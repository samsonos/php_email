<?php
/*
 * This file is part of the SamsonPHP\Email package.
 * (c) 2013 Vitaly Iegorov <egorov@samsonos.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace samson\email;

use samson\core\CompressableExternalModule;

/**
 * Creating\sending email with attachments support SamsonPHP
 * 
 * @package SamsonPHP
 * @author Vitaly Iegorov <vitalyiegorov@gmail.com> 
 */
class Email extends CompressableExternalModule
{
	public $id = 'email';
	
	/** From which address email is sent */
	public $from;
	
	/** Username who sends this email */
	public $from_name;
	
	/** Where to send email */
	public $to;
	
	/** Subject */
	public $subject;
	
	/** Copy */
	public $cc = array();
	
	/** Message body */
	public $message;
	
	/** Message headers */
	private $headers;
	
	/** Message unique boundary id */
	private $session_id;	
	
	/** Message attachments collection */
	private $attachments = array();	
	
	public function from( $from, $name = null ){ $this->from = $from; $this->from_name = $name; return $this; }	
	public function to( $to ){$this->to = $to;return $this;}
	public function subject( $subject ){ $this->subject = $subject; return $this; }
	public function cc( $cc ){ $this->cc = $cc; return $this; }
	public function message( $message ){ $this->message = $message; return $this; }
	public function attach( $file ){ $this->attachments[] = $file; return $this; }
	
	/** Correctly encode sting for email */
	private function _encode( $str, $charset ) { return '=?' . $charset . '?B?' . base64_encode($str) . '?='; }

	/** Send E-mail messages with defined parameters */
	public function send()
	{
		// Generate uniqid session id
		$this->session_id = md5(uniqid(time()));
		
		// Обработаем кирилицу в поле: От кого
		$from_user = $this->_encode( $this->from_name, 'UTF-8');
		
		// Обработаем кирилицу в поле: Тема письма
		$this->subject = $this->_encode( $this->subject, 'UTF-8' );
		
		// Set from header
		$this->headers .= 'From: '.$from_user.'<'.$this->from.'>'."\r\n";
		
		// Copy header
		if(sizeof($this->cc)) $this->headers .= 'Cc: '.implode(',',$this->cc)."\r\n";
		
		// Set content-type header
		$this->headers .= 'Content-Type: multipart/mixed; boundary="PHP-mixed-'.$this->session_id.'"'."\r\n";				
			
		// Create message body
		$body = '--PHP-mixed-'.$this->session_id."\r\n";
		$body .= 'Content-Type: multipart/alternative; boundary="PHP-alt-'.$this->session_id.'"'."\r\n"."\r\n";
		
		// Set body HTML message part
		$body .= '--PHP-alt-'.$this->session_id."\r\n";
		$body .= 'Content-type: text/html; charset=utf-8'."\r\n";
		$body .= 'Content-Transfer-Encoding: 7bit'."\r\n"."\r\n";
		
		// Make message as correct HTML
		$body .= '<html><head></head><body>'.$this->message.'</body></html>'."\r\n";
		
		// Iterate attached files
		foreach ( $this->attachments as $file )
		{				
			// If attached file exists
			if( file_exists($file) )
			{				
				// Generate attachment message part			
				$body .= '--PHP-alt-'.$this->session_id."\r\n";
				$body .= 'Content-Type: '.\samson\core\File::getMIME($file).'; name="'. basename($file).'"'."\r\n";
				$body .= 'Content-Transfer-Encoding: base64'."\r\n";
				$body .= 'Content-Disposition: attachment'."\r\n"."\r\n";
				// Read and encode file content
				$body .= chunk_split(base64_encode(file_get_contents($file)))."\r\n";	
			}		
		}		
		
		// Try to send mail
		return mail( $this->to, $this->subject, $body, $this->headers );
	}
}