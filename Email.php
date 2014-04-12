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
	public $message = '';
	
	/** Message headers */
	private $headers = '';
	
	/** Message unique boundary id */
	private $session_id = '';
	
	/** Message attachments collection */
	private $attachments = array();

    /** Flag if message is prepared for sending */
    public $ready = false;

    /** Message body */
    private $body = '';

    /** Unsubscribe URL */
    private $unsubscribe = '';

    /** Failed emails return address */
    private $returnTo = '';

    /**
     * Set unsubscribe link
     * @param $url Unsubscribe link
     *
     * @return $this Chaining
     */
    public function unsubscribe($url)
    {
        $this->unsubscribe = $url;
        return $this;
    }

	public function from($from, $name = null)
    {
        $this->from = $from;
        $this->from_name = $name;

        return $this;
    }

	public function to($to)
    {
        $this->to = $to;
        return $this;
    }

	public function subject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

	public function cc($cc)
    {
        $this->cc = $cc;
        return $this;
    }

	public function message($message)
    {
        $this->message = $message;

        return $this;
    }

	public function attach( $file ){ $this->attachments[] = $file; return $this; }
	
	/** Correctly encode sting for email */
	private function _encode( $str, $charset ) { return '=?' . $charset . '?B?' . base64_encode($str) . '?='; }

    /**
     * Set return to email for gathering bulk emails
     * @param string $returnTo email to recieved failed emails
     *
     * @return $this
     */
    public function returnTo($returnTo)
    {
        $this->returnTo = $returnTo;

        return $this;
    }

    /**
     * Get|Set message headers
     * @param string $headers Message headers string for setting
     *
     * @return $this|string Current message headers
     */
    public function headers($headers = null)
    {
        if (func_num_args()) {
            $this->headers = $headers;
            return $this;
        } else {
            return $this->headers;
        }
    }

    /**
     * Get|Set message body
     * @param string $body Message body string for setting
     *
     * @return $this|string Current message body
     */
    public function body($body = null)
    {
        if (func_num_args()) {
            $this->body = $body;
            return $this;
        } else {
            return $this->body;
        }

        return $this->body;
    }


    /**
     * Prepare message data for sending
     * @return $this Chaining
     */
    public function prepareEmail()
    {
        // Generate uniqid session id
        $this->session_id = md5(uniqid(time()));

        // Обработаем кирилицу в поле: От кого
        $from_user = $this->_encode( $this->from_name, 'UTF-8');

        // Set MIME type
        $this->headers  = '';//'MIME-Version: 1.0' . "\r\n";

        /*// Set subject header
        if (isset($this->subject{0})) {
            $this->headers = 'Subject: '.$this->encode($this->subject, 'UTF-8')."\r\n";
        }*/

        // Set from header
        $this->headers .= 'From: '.$from_user.'<'.$this->from.'>'."\r\n";

        // Copy header
        if(sizeof($this->cc)) $this->headers .= 'Cc: '.implode(',',$this->cc)."\r\n";

        // Set unsubscribe link if present
        $this->headers .= isset($this->unsubscribe{0})?'List-Unsubscribe: <'.$this->unsubscribe.'>'."\r\n":'';

        // Set return path link if present
        $this->headers .= isset($this->returnTo{0})?'Return-Path: '.$this->returnTo."\r\n":'';

        $body = '';

        // If we have attachments
        if (sizeof($this->attachments)) {
            // Set mixed boundary header
            $this->headers .= 'Content-Type: multipart/mixed; boundary="mixed-'.$this->session_id.'"'."\r\n"."\r\n";

            // Start text boundary in message body
            $body .= '--mixed-'.$this->session_id."\r\n";
            $body .= 'Content-type: text/html; charset="UTF-8"'."\r\n";
            $body .= 'Content-Transfer-Encoding: 7bit'."\r\n"."\r\n";

        } else { // Simple HTML message - no boundary
            $this->headers .= 'Content-type: text/html; charset="UTF-8"'."\r\n";
            $this->headers .= 'Content-Transfer-Encoding: 7bit'."\r\n"."\r\n";
        }

        // Make message as correct HTML
        $body .= $this->message."\r\n";

        if (sizeof($this->attachments)) {
            $body .= "\r\n";
            // Iterate attached files
            foreach ($this->attachments as $file) {
                // If attached file exists
                if (file_exists($file)) {
                    // Generate attachment message part
                    $body .= '--mixed-'.$this->session_id."\r\n";
                    $body .= 'Content-Type: '.\samson\core\File::getMIME($file).'; name="'. basename($file).'"'."\r\n";
                    $body .= 'Content-Transfer-Encoding: base64'."\r\n";
                    $body .= 'Content-Disposition: attachment'."\r\n"."\r\n";
                    // Read and encode file content
                    $body .= chunk_split(base64_encode(file_get_contents($file)))."\r\n";
                    $body .= "\r\n".'--mixed'.$this->session_id.'--'."\r\n";
                }
            }
        }

        $this->body = $body;

        $this->ready = true;

        return $this;
    }

	/** Send E-mail messages with defined parameters */
	public function send()
	{
        // If message has not been prepared
        if (!$this->ready) {
            $this->prepareEmail();
        }

        // Обработаем кирилицу в поле: Тема письма
        $this->subject = $this->_encode( $this->subject, 'UTF-8' );

		// Try to send mail
		return mail( $this->to, $this->subject, $this->body, $this->headers );
	}
}