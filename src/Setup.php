<?php
namespace Civi;

use Civi\Setup\Event\CheckAuthorizedEvent;
use Civi\Setup\Event\CheckRequirementsEvent;
use Civi\Setup\Event\CheckInstalledEvent;
use Civi\Setup\Event\CreateFormEvent;
use Civi\Setup\Event\InitEvent;
use Civi\Setup\Event\InstallSchemaEvent;
use Civi\Setup\Event\InstallSettingsEvent;
use Civi\Setup\Event\RemoveSchemaEvent;
use Civi\Setup\Event\RemoveSettingsEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Setup {

  const PRIORITY_START = 2000;
  const PRIORITY_PREPARE = 1000;
  const PRIORITY_MAIN = 0;
  const PRIORITY_LATE = -1000;
  const PRIORITY_END = -2000;

  private static $instance;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * @var \Civi\Setup\Model
   */
  protected $model;

  // ----- Static initialization -----

  /**
   * The initialization process loads any `*.civi-setup.php` files and
   * fires the `civi.setup.init` event.
   *
   * @param array $modelValues
   *   List of default configuration options.
   *   Recommended fields: 'srcPath', 'cms'
   * @param callable $pluginCallback
   *   Function which manipulates the list of plugin files.
   *   Use this to add, remove, or re-order callbacks.
   *   function(array $files) => array
   *   Ex: ['hello' => '/var/www/plugins/hello.civi-setup.php']
   */
  public static function init($modelValues = array(), $pluginCallback = NULL) {
    if (!defined('CIVI_SETUP')) {
      define('CIVI_SETUP', 1);
    }

    self::$instance = new Setup();
    self::$instance->model = new \Civi\Setup\Model();
    self::$instance->model->setValues($modelValues);
    self::$instance->dispatcher = new EventDispatcher();

    $pluginDir = dirname(__DIR__) . '/plugins';
    $pluginFiles = array();
    foreach ((array) glob("$pluginDir/*.civi-setup.php") as $file) {
      $key = preg_replace('/\.civi-setup\.php$/', '', basename($file));
      $pluginFiles[$key] = $file;
    }
    uksort($pluginFiles, function ($a, $b) {
      return strcmp(basename($a), basename($b));
    });

    if ($pluginCallback) {
      $pluginFiles = $pluginCallback($pluginFiles);
    }

    foreach ($pluginFiles as $pluginFile) {
      require $pluginFile;
    }

    $event = new InitEvent(self::$instance->getModel());
    self::$instance->getDispatcher()->dispatch('civi.setup.init', $event);
    // return $event; ...or... return self::$instance;
  }

  /**
   * @return Setup
   */
  public static function instance() {
    return self::$instance;
  }

  // ----- Logic ----

  /**
   * Determine whether the current CMS user is authorized to perform
   * installation.
   *
   * @return \Civi\Setup\Event\CheckAuthorizedEvent
   */
  public function checkAuthorized() {
    $event = new CheckAuthorizedEvent($this->getModel());
    return $this->getDispatcher()->dispatch('civi.setup.checkAuthorized', $event);
  }

  /**
   * Determine whether the local environment meets system requirements.
   *
   * @return \Civi\Setup\Event\CheckRequirementsEvent
   */
  public function checkRequirements() {
    $event = new CheckRequirementsEvent($this->getModel());
    return $this->getDispatcher()->dispatch('civi.setup.checkRequirements', $event);
  }

  /**
   * Determine whether the setting and/or schema are already installed.
   *
   * @return \Civi\Setup\Event\CheckInstalledEvent
   */
  public function checkInstalled() {
    $event = new CheckInstalledEvent($this->getModel());
    return $this->getDispatcher()->dispatch('civi.setup.checkInstalled', $event);
  }

  /**
   * Create the settings file.
   *
   * @return \Civi\Setup\Event\InstallSettingsEvent
   */
  public function installSettings() {
    $event = new InstallSettingsEvent($this->getModel());
    return $this->getDispatcher()->dispatch('civi.setup.installSettings', $event);
  }

  /**
   * Create the database schema.
   *
   * @return \Civi\Setup\Event\InstallSchemaEvent
   */
  public function installSchema() {
    $event = new InstallSchemaEvent($this->getModel());
    return $this->getDispatcher()->dispatch('civi.setup.installSchema', $event);
  }

  /**
   * Remove the settings file.
   *
   * @return \Civi\Setup\Event\RemoveSettingsEvent
   */
  public function removeSettings() {
    $event = new RemoveSettingsEvent($this->getModel());
    return $this->getDispatcher()->dispatch('civi.setup.removeSettings', $event);
  }

  /**
   * Remove the database schema.
   *
   * @return \Civi\Setup\Event\RemoveSchemaEvent
   */
  public function removeSchema() {
    $event = new RemoveSchemaEvent($this->getModel());
    return $this->getDispatcher()->dispatch('civi.setup.removeSchema', $event);
  }

  /**
   * Create a page-controller for a web-based installation form.
   *
   * @return \Civi\Setup\Event\CreateFormEvent
   */
  public function createForm() {
    $event = new CreateFormEvent($this->getModel());
    return $this->getDispatcher()->dispatch('civi.setup.createForm', $event);
  }

  // ----- Accessors -----

  /**
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  public function getDispatcher() {
    return $this->dispatcher;
  }

  /**
   * @return \Civi\Setup\Model
   */
  public function getModel() {
    return $this->model;
  }

}