<?php
namespace Dell\WpShieldpp\Module;

use Dell\WpShieldpp\Library\Logger;
use Dell\WpShieldpp\Library\CsPluginConfig;
use Dell\WpShieldpp\Module\ShieldPaypal\ShieldPaypalModule;

class BaseModule implements ModuleInterface
{
  public static function init()
  {
    CsPluginConfig::init();
    Logger::init();
    ShieldPaypalModule::init();
  }
}