<?php
/**
 * @package    Molajo
 * @copyright  2012 Individual Molajo Contributors. All rights reserved.
 * @license    GNU GPL v 2, or later and MIT, see License folder
 */
namespace Molajo\Service\Services\Mail;

use Molajo\Service\Services;

defined('MOLAJO') or die;

/**
 * Mail
 *
 * Edits, filters input, and sends email
 *
 * Example usage:
 *
 * Services::Mail()->set('to', 'person@example.com,Fname Lname');
 * Services::Mail()->set('from', 'person@example.com,Fname Lname');
 * Services::Mail()->set('reply_to', 'person@example.com,FName LName');
 * Services::Mail()->set('cc', 'person@example.com,FName LName');
 * Services::Mail()->set('bcc', 'person@example.com,FName LName');
 * Services::Mail()->set('subject', 'Welcome to our Site');
 * Services::Mail()->set('body', '<h2>Stuff goes here</h2>') ;
 * Services::Mail()->set('mailer_html_or_text', 'html') ;
 * Services::Mail()->set('attachment', SITE_MEDIA_FOLDER.'/molajo.sql') ;
 * Services::Mail()->send();
 *
 * @package     Molajo
 * @subpackage  Service
 * @since       1.0
 */
Class MailService
{
	/**
	 * @var    object
	 * @since  1.0
	 */
	protected static $instance;

	/**
	 * Registry
	 *
	 * @var    object
	 * @since  1.0
	 */
	protected $registry;

	/**
	 * Mail Instance
	 *
	 * @var    object
	 * @since  1.0
	 */
	protected $mailInstance;

	/**
	 * Error Count
	 *
	 * @var   integer
	 * @since 1.0
	 */
	protected $error_count = 0;

	/**
	 * getInstance
	 *
	 * @static
	 * @return bool|object
	 * @since  1.0
	 */
	public static function getInstance()
	{
		if (empty(self::$instance)) {
			self::$instance = new MailService();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 *
	 * @return boolean
	 * @since  1.0
	 */
	public function __construct()
	{
		$this->registry = Services::Registry()->createRegistry('Mail');

		return $this;
	}

	/**
	 * Interface to Joomla Services, like logging, which use Mail Services
	 *
	 * @param string $name
	 * @param array  $arguments
	 */
	public function __call($name, $arguments)
	{
		$name = strtolower($name);

		if (substr($name, 0, 3) == 'set') {

			$rest = substr($name, 3, strlen($name) - 3);

			if (count($arguments) == 1) {
				if ($rest == 'sender') {
					$rest = 'from';
				}
				if ($rest == 'recipient') {
					$rest = 'to';
				}

				return $this->set($rest, $arguments[0]);
			}

		} else {
			if ($name == 'send') {
				return $this->send();
			}
		}
	}

	/**
	 * get
	 *
	 * Returns a set property or it's default for the mail object
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 * @since   1.0
	 */
	public function get($key, $default = null)
	{
		return $this->registry->get('Mail', $key, $default);
	}

	/**
	 * set
	 *
	 * Modifies a property of the mail object
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return mixed
	 *
	 * @since   1.0
	 */
	public function set($key, $value = null)
	{
		$this->registry->set('Mail', $key, $value);

		return $this;
	}

	/**
	 * send
	 *
	 * Checks permissions, validates data elements, and sends email
	 *
	 * @return boolean
	 *
	 * @since   1.0
	 */
	public function send()
	{
		/** Email disabled */
		if (Services::Registry()->get('Configuration', 'mailer_disable_sending', 0) == 1) {
			return true;
		}

		/** ACL Check */
		$results = $this->permission();
		if ($results == true) {
		} else {
			return $results;
		}

		/** For development only deliver to values */
		$mailer_only_deliver_to = Services::Registry()->get('Configuration', 'mailer_only_deliver_to', '');

		if (trim($mailer_only_deliver_to) == '') {
		} else {
			$this->set('reply_to', $mailer_only_deliver_to);
			$this->set('from', $mailer_only_deliver_to);
			$this->set('to', $mailer_only_deliver_to);
			$this->set('cc', '');
			$this->set('bcc', '');
		}

		/** Instantiate Mailer */
		$mailClass = 'phpmailer\\PHPMailer';
		$this->mailInstance = new $mailClass();

		/** Edit input */
		$this->processInput();

		/** Type of email */
		switch (Services::Registry()->get('Configuration', 'mailer_transport')) {

			case 'smtp':
				$this->mailInstance->mailer_smtpauth = Services::Registry()->get('Configuration', 'mailer_smtpauth');
				$this->mailInstance->smtphost = Services::Registry()->get('Configuration', 'smtphost');
				$this->mailInstance->mailer_smtpuser = Services::Registry()->get('Configuration', 'mailer_smtpuser');
				$this->mailInstance->mailer_mailer_smtphost = Services::Registry()->get('Configuration', 'mailer_mailer_smtphost');
				$this->mailInstance->smtpsecure = Services::Registry()->get('Configuration', 'smtpsecure');
				$this->mailInstance->smtpport = Services::Registry()->get('Configuration', 'smtpport');

				$this->mailInstance->IsSMTP();
				break;

			case 'sendmail':
				$this->mailInstance->mailer_smtpauth = Services::Registry()->get('Configuration', 'sendmail_path');

				$this->mailInstance->IsSendmail();
				break;

			default:
				$this->mailInstance->IsMail();
				break;
		}

		/** Send */
		$this->mailInstance->Send();

		return true;
	}

	/**
	 * permission
	 *
	 * Verify user and extension have permission to send email
	 *
	 * @return bool
	 * @since  1.0
	 */
	protected function permission()
	{
		$permission = true;

		/** Resource (authorises any user) */

		/** User */

		/** authorization event */
		//todo: what is the catalog id of a service?
		//$results = Services::Authorisation()->verifyTask('email', $catalog_id);
		return $permission;
	}

	/**
	 * Verify all data required is available and filter input for security
	 *
	 * @return bool|int
	 */
	protected function processInput()
	{
		$this->error_count = 0;

		/** Recipients */
		$this->processRecipient('reply_to');
		$this->processRecipient('from');
		$this->processRecipient('to');
		$this->processRecipient('cc');
		$this->processRecipient('bcc');

		/** Subject */
		$value = $this->get('subject', '');
		if ($value == '') {
			$value = Services::Registry()->get('Configuration', 'site_name', '');
		}
		$value = $this->filterInput('subject', $value, 'char');
		$this->mailInstance->set('Subject', $value);

		/** Body */
		if ($this->get('mailer_html_or_text', 'text') == 'html') {
			$mailer_html_or_text = 'text';
		} else {
			$mailer_html_or_text = 'char';
		}
		$value = $this->filterInput('body', $value = $this->get('body'), $mailer_html_or_text);
		$this->mailInstance->set('Body', $value);
		if ($mailer_html_or_text == 'html') {
			$this->mailInstance->IsHTML(true);
		}

		/** Attachment */
		$attachment = $this->get('attachment', '');
		if ($attachment == '') {
		} else {
			$attachment = $this->filterInput('attachment', $attachment, 'file');
		}
		if ($attachment === false || $attachment == '') {
		} else {
			$this->mailInstance->AddAttachment(
				$attachment,
				$name = 'Attachment',
				$encoding = 'base64',
				$type = 'application/octet-stream');
		}

		return true;
	}

	/**
	 *     Filter and edit email and name parameters, sending filtered values to phpMail
	 *
	 * @param string $parameter
	 *
	 * @return null
	 * @since  1.0
	 */
	protected function processRecipient($parameter)
	{
		/** extract all pairs of email addresses and names for this parameter */
		$x = explode(';', $this->get($parameter));

		if (is_array($x)) {
			$y = $x;
		} else {
			$y = array($x);
		}

		if (count($y) == 0) {
			return;
		}

		/** process each pair of email addresses and names */
		foreach ($y as $z) {

			/** split pair by comma */
			$extract = explode(',', $z);
			if (count($extract) == 0) {
				break;
			}

			/** email address */
			if ($z === false || $z == '') {
				break;
			}
			$z = $this->filterInput($parameter, $extract[0], 'email');
			if ($z === false || $z == '') {
				break;
			}
			$useEmail = $z;

			/** name */
			$useName = '';
			if (count($extract) > 1) {
				$z = $this->filterInput($parameter, $extract[1], 'char');
				if ($z === false || $z == '') {
				} else {
					$useName = $z;
				}
			}

			if ($parameter == 'reply_to') {
				$this->mailInstance->AddReplyTo($useEmail, $useName);

			} elseif ($parameter == 'from') {
				$this->mailInstance->SetFrom($useEmail, $useName);

			} elseif ($parameter == 'cc') {
				$this->mailInstance->AddCC($useEmail, $useName);

			} elseif ($parameter == 'bcc') {
				$this->mailInstance->AddBCC($useEmail, $useName);

			} else {
				$this->mailInstance->AddAddress($useEmail, $useName);
			}
		}
	}

	/**
	 * filterInput
	 *
	 * @param string $name        Name of input field
	 * @param string $field_value Value of input field
	 * @param string $dataType    Datatype of input field
	 * @param int    $null        0 or 1 - is null allowed
	 * @param string $default     Default value, optional
	 *
	 * @return mixed
	 * @since   1.0
	 */
	protected function filterInput(
		$name, $value, $dataType, $null = null, $default = null)
	{

		try {
			$value = Services::Filter()
				->filter(
				$value,
				$dataType,
				$null,
				$default
			);

		} catch (\Exception $e) {

			$this->error_count++;

			echo $e->getMessage() . ' ' . $name;
		}

		return $value;
	}
}
