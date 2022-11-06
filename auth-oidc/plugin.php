<?php
return array(
    'id' =>             'auth:oidc', # notrans
    'version' =>        '0.2',
    'name' =>           /* trans */ 'OpenID Connect Authentication',
    'author' =>         'Ben Miller',
    'description' =>    /* trans */ 'Provides a configurable authentication
        backend for authenticating staff and clients using OpenID Connect.',
    'url' =>            'http://github.com/bjmllr/osTicket-plugins',
    'plugin' =>         'authentication.php:OidcAuthPlugin',
    'requires' => array(
        "phpseclib/phpseclib" => array(
            "version" => "2.0.30",
            "map" => array(
                "phpseclib/phpseclib/phpseclib" => 'lib/phpseclib/phpseclib',
            )
        ),
        "jumbojett/openid-connect-php" => array(
            "version" => "0.9.2",
            "map" => array(
                "jumbojett/openid-connect-php/src" => 'lib/jumbojett/openid-connect-php',
            )
        ),
    ),
);

?>
