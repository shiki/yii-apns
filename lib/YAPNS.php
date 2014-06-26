<?php

namespace YAPNS;

use \YAPNS\YAPNSLogger;

/**
 * Wraps ApnsPHP in a Yii extension.
 *
 * This takes care of setting up the autoloader for the library. This also connects to the server
 * when necessary so you only need to call addMessage() and send().
 *
 * @see http://code.google.com/p/apns-php/
 *
 * @package YAPNS
 * @author Shiki
 */
class YAPNS extends \CApplicationComponent
{
  const ENV_SANDBOX = 'sandbox';
  const ENV_PRODUCTION = 'production';

  /**
   * The path to the ApnsPHP library. If not provided, this component will try to load the library
   * that comes with extension package.
   * @var string
   */
  public $apnsPHPLibPath;

  /**
   *
   * @var string
   */
  public $environment = self::ENV_SANDBOX;

  /**
   * Push SSL Certificate file with key (Bundled PEM). You can get this by downloading the
   * certificate and key from Apple and converting them to a pem file. See here for more info:
   * http://code.google.com/p/apns-php/wiki/CertificateCreation
   *
   * @var string
   */
  public $providerCertificateFilePath;

  /**
   * From Apple's documentation: To establish a TLS session with APNs, an Entrust Secure CA root
   * certificate must be installed on the provider’s server. (http://bit.ly/hUEl4d). This might
   * already be on your server. If not, you can download it from Entrust's website. On the latest
   * Ubuntu server, this was on: /etc/ssl/certs/Entrust.net_Premium_2048_Secure_Server_CA.pem
   * See here for more info: http://code.google.com/p/apns-php/wiki/CertificateCreation
   *
   * @var string
   */
  public $rootCertificationAuthorityFilePath;

  /**
   *
   * @var ApnsPHP_Push
   */
  protected $_pushProvider;

  /**
   * Initializes the application component.
   * This method is required by {@link IApplicationComponent} and is invoked by application.
   */
  public function init()
  {
    if (empty($this->providerCertificateFilePath))
      throw new CException('Push SSL certificate is required.');
    if (!in_array($this->environment, array(self::ENV_PRODUCTION, self::ENV_SANDBOX)))
      throw new CException('Environment is invalid.');

    $this->initAutoloader();

    \Yii::app()->attachEventHandler('onEndRequest', array($this, 'onApplicationEndRequest'));

    parent::init();
  }

  /**
   * @return \ApnsPHP_Push
   */
  public function getPushProvider()
  {
    if ($this->_pushProvider)
      return $this->_pushProvider;

    $push = new \ApnsPHP_Push(
      $this->environment == self::ENV_PRODUCTION ? \ApnsPHP_Push::ENVIRONMENT_PRODUCTION : \ApnsPHP_Push::ENVIRONMENT_SANDBOX,
      $this->providerCertificateFilePath
    );
    if ($this->rootCertificationAuthorityFilePath)
      $push->setRootCertificationAuthority($this->rootCertificationAuthorityFilePath);

    $push->setLogger(new YAPNSLogger());
    $push->connect();

    $this->_pushProvider = $push;
    return $this->_pushProvider;
  }

  /**
   * Forward method calls to push provider (e.g. calling Yii::app()->apns->add($message)
   * will call Yii::app()->getPushProvider()->add($message)).
   *
   * @param string $name
   * @param array $parameters
   * @return mixed
   */
  public function __call($name, $parameters)
  {
    $push = $this->getPushProvider();
    if (method_exists($push, $name))
      return call_user_func_array(array($push, $name), $parameters);

    return parent::__call($name, $parameters);
  }

  /**
   * Disconnect push provider on app exit.
   * @param CEvent $event
   */
  public function onApplicationEndRequest(\CEvent $event)
  {
    //echo 'disconnecting'; die();
    if ($this->_pushProvider)
      $this->_pushProvider->disconnect();
  }

  /**
   *
   */
  public function initAutoloader()
  {
    defined('YAPNS_LIB_PATH')
      or define('YAPNS_LIB_PATH', $this->apnsPHPLibPath ? $this->apnsPHPLibPath : FF_SHARED_LIB_PATH . '/ApnsPHP');

    \Yii::registerAutoloader(array(__CLASS__, 'autoload'));
  }

  /**
   * A modified version of ApnsPHP_Autoload tailored to work with Yii. There's generally no
   * need to register or use this directly.
   *
   * This autoloader fails if initAutoloader is not called first or YAPNS_LIB_PATH is not defined.
   *
   * @param string $className
   */
  public static function autoload($className)
  {
    //if a long name separated by `\` (i.e. with namespace). get only last part.
    $temp = explode('\\', $className);
    $className = end($temp);

    if (!defined('YAPNS_LIB_PATH'))
      return;
    if (empty($className) || strpos($className, 'ApnsPHP_') !== 0)
      return;


    $filePath = sprintf('%s%s%s.php',
      YAPNS_LIB_PATH, DIRECTORY_SEPARATOR,
      str_replace('_', DIRECTORY_SEPARATOR, $className)
    );

    if (!is_file($filePath) || !is_readable($filePath))
      return; // let Yii handle this

    require_once($filePath);
  }
}
