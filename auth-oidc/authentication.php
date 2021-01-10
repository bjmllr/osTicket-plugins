<?php

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');

class OidcAuthPlugin extends Plugin {
    var $config_class = "OidcPluginConfig";

    function bootstrap() {
        $config = $this->getConfig();
        $enabled = $config->get('oidc-enabled');

        if (in_array($enabled, array('all', 'staff'))) {
            require_once('oidc.php');
            StaffAuthenticationBackend::register(
                new OidcStaffAuthBackend($this->getConfig()));
        }
        if (in_array($enabled, array('all', 'client'))) {
            require_once('oidc.php');
            UserAuthenticationBackend::register(
                new OidcUserAuthBackend($this->getConfig()));
        }
    }
}

require_once(INCLUDE_DIR.'UniversalClassLoader.php');
use Symfony\Component\ClassLoader\UniversalClassLoader_osTicket;
$loader = new UniversalClassLoader_osTicket();
$loader->registerNamespaceFallbacks(array(
    dirname(__file__).'/lib'));
$loader->register();
