<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class OidcPluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-oidc');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        $modes = new ChoiceField(array(
            'label' => $__('Authentication'),
            'default' => "disabled",
            'choices' => array(
                'disabled' => $__('Disabled'),
                'staff' => $__('Agents Only'),
                'client' => $__('Clients Only'),
                'all' => $__('Agents and Clients'),
            ),
        ));

        return array(
            'oidc' => new SectionBreakField(array(
                'label' => $__('OpenID Connect Authentication'),
            )),
            'oidc-url' => new TextboxField(array(
                'label' => $__('OIDC Provider URL'),
                'configuration' => array('size'=>60, 'length'=>100),
                'hint' => $__('In Keycloak this will be https://yourdomain/auth/realms/yourrealm'),
            )),
            'oidc-client-id' => new TextboxField(array(
                'label' => $__('OIDC Client ID'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'oidc-client-secret' => new TextboxField(array(
                'label' => $__('OIDC Client Secret'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'oidc-user-redirect' => new TextboxField(array(
                'label' => $__('OIDC User Login Redirect URL'),
                'configuration' => array('size'=>60, 'length'=>100),
                'hint' => $__('Try https://yourdomain/login.php?do=ext&bk=oidc.client'),
            )),
            'oidc-staff-redirect' => new TextboxField(array(
                'label' => $__('OIDC Staff Login Redirect URL'),
                'configuration' => array('size'=>60, 'length'=>100),
                'hint' => $__('Try https://yourdomain/scp/login.php?do=ext&bk=oidc.client'),
            )),
            'oidc-departments-key' => new TextboxField(array(
                'label' => $__('OIDC Departments Key'),
                'configuration' => array('size'=>60, 'length'=>100),
                'hint' => $__('This is the name of the field in the access token that contains the list of department names. This must be present to enable staff login.'),
            )),
            'oidc-default-role-id' => new TextboxField(array(
                'label' => $__('OIDC Default Role ID'),
                'configuration' => array('size'=>60, 'length'=>100),
                'hint' => $__('The role ID to use for a newly-added department, e.g., 1 = All Access on a new install. This must be present to enable staff login.')
            )),
            'oidc-enabled' => clone $modes,
        );
    }
}
?>
