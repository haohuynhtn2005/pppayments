<?php
namespace ShieldPpPayment\Module;

use ShieldPpPayment\Library\Logger;
use ShieldPpPayment\Library\CsPluginConfig;
use ShieldPpPayment\Module\ShieldPaypal\ShieldPaypalModule;

class BaseModule implements ModuleInterface
{
  public static function init()
  {
    CsPluginConfig::init();
    Logger::init();
    ShieldPaypalModule::init();
  }
}