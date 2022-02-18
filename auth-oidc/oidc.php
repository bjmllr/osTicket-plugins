<?php

require_once('config.php');
require_once(dirname(__file__).'/lib/phpseclib/phpseclib/Crypt/Hash.php');
require_once(dirname(__file__).'/lib/phpseclib/phpseclib/Crypt/RSA.php');
require_once(dirname(__file__).'/lib/phpseclib/phpseclib/Math/BigInteger.php');
require_once(dirname(__file__).'/lib/jumbojett/openid-connect-php/OpenIDConnectClient.php');

use Jumbojett\OpenIDConnectClient;

class OidcAuth {
    var $config;
    var $access_token;

    function __construct($config, $scope) {
        $this->config = $config;
        $this->scope = $scope;

        $this->oidc = new OpenIDConnectClient(
            $this->config->get('oidc-url'),
            $this->config->get('oidc-client-id'),
            $this->config->get('oidc-client-secret'),
        );

        if ($scope === 'staff') {
            $this->oidc->setRedirectURL($this->config->get('oidc-staff-redirect'));
        } else if ($scope === 'user') {
            $this->oidc->setRedirectURL($this->config->get('oidc-user-redirect'));
        }
    }

    function triggerAuth() {
        $auth = $this->oidc->authenticate();
        $depts = $this->getNewDepts();

        if (!$auth) {
            $_SESSION['_staff']['auth']['msg'] = 'Authentication required';
        } else if ($this->scope === 'staff' && empty($depts)) {
            $_SESSION['_staff']['auth']['msg'] = 'Authorization required';
        } else {
            $this->setUser();
            $this->setEmail();
            $this->setName();

            if ($this->scope === 'staff') {
                $staff = $this->getStaff();
                if (!isset($staff)) $this->createStaff();
                $this->updateAccess();
            }
        }
    }

    function setUser() {
        $_SESSION[':oidc']['user'] = $this->oidc->getAccessTokenPayload()->preferred_username;
    }

    function getUser() {
        return $_SESSION[':oidc']['user'];
    }

    function setEmail() {
        $_SESSION[':oidc']['email'] = $this->oidc->getAccessTokenPayload()->email;
    }

    function getEmail() {
        return $_SESSION[':oidc']['email'];
    }

    function setName() {
        $_SESSION[':oidc']['given_name'] = $this->oidc->getAccessTokenPayload()->given_name;
        $_SESSION[':oidc']['family_name'] = $this->oidc->getAccessTokenPayload()->family_name;
        $_SESSION[':oidc']['name'] = $this->oidc->getAccessTokenPayload()->name;
    }

    function getName() {
        return $_SESSION[':oidc']['name'];
    }

    function getGivenName() {
        return $_SESSION[':oidc']['given_name'];
    }

    function getFamilyName() {
        return $_SESSION[':oidc']['family_name'];
    }

    function getProfile() {
        return array(
            'email' => $this->getEmail(),
            'name' => $this->getName(),
        );
    }

    function getLocalDepts() {
        if (!isset($this->local_depts))
            $this->local_depts = Dept::objects()->all();
        return $this->local_depts;
    }

    function getNewDepts() {
        if (isset($this->new_depts)) return $this->new_depts;
        if ($this->scope === 'user') return [];

        $localDepts = $this->getLocalDepts();
        $tokenDepts = $this->getTokenDepts();
        if (!isset($localDepts) || !isset($tokenDepts)) return [];

        $this->new_depts = [];
        foreach($localDepts as $local) {
            foreach ($tokenDepts as $remote) {
                if (strtolower($remote) == strtolower($local->getLocalName())) {
                    $this->new_depts []= $local;
                }
            }
        }

        return $this->new_depts;
    }

    function getNewDeptNames() {
        $names = [];
        $newDepts = $this->getNewDepts();
        foreach ($newDepts as $dept) {
            $names []= $dept->getLocalName();
        }
        return $names;
    }

    function getNewDeptIds() {
        $deptIds = [];
        $depts = $this->getNewDepts();
        foreach ($depts as $dept) {
            $deptIds []= $dept->id;
        }
        return $deptIds;
    }

    function getOldDepts() {
        if (isset($this->old_depts)) return $this->old_depts;

        $staff = $this->getStaff();
        $this->old_depts = [];

        if (isset($staff)) {
            $deptIds = array_keys($staff->getRoles());
            foreach ($deptIds as $id) {
                $this->old_depts []= Dept::lookup($id);
            }
        }
        return $this->old_depts;
    }

    function getOldDeptNames() {
        $deptNames = [];
        $depts = $this->getOldDepts();
        foreach ($depts as $dept) {
            if (isset($dept))
                $deptNames []= $dept->getLocalName();
        }
        return $deptNames;
    }

    function getOldDeptIds() {
        $deptIds = [];
        $depts = $this->getOldDepts();
        foreach ($depts as $dept) {
            $deptIds []= $dept->id;
        }
        return $deptIds;
    }

    function getTokenDepts() {
        $clientId = $this->config->get('oidc-client-id');
        $rolesKey = $this->config->get('oidc-departments-key');
        $token = $this->oidc->getAccessTokenPayload();
        return $token->resource_access->$clientId->$rolesKey ?:
            $token->$clientId->$rolesKey ?:
            $token->resource_access->$rolesKey ?:
            $token->$rolesKey;
    }

    function getStaff() {
        $email = $this->getEmail();
        if (isset($email) && !isset($this->staff))
            $this->staff = StaffSession::lookup(['email' => $email]);
        return $this->staff;
    }

    function getPrimaryDept() {
        $staff = $this->getStaff();
        if (isset($staff))
            return $staff->getDept();
        else
            return null;
    }

    function createStaff() {
        $defaultRoleId = intval($this->config->get('oidc-default-role-id'));

        $errors = [];
        $this->staff = Staff::create(array(
            'isactive' => true,
        ));
        $this->staff->updatePerms(array(
            User::PERM_CREATE,
            User::PERM_EDIT,
            User::PERM_DELETE,
            User::PERM_MANAGE,
            User::PERM_DIRECTORY,
            Organization::PERM_CREATE,
            Organization::PERM_EDIT,
            Organization::PERM_DELETE,
            FAQ::PERM_MANAGE,
        ));
        $this->staff->update(array(
            'username' => $this->getUser(),
            'firstname' => $this->getGivenName(),
            'lastname' => $this->getFamilyName(),
            'email' => $this->getEmail(),
            'isactive' => true,
            'dept_id' => $this->getNewDeptIds()[0],
            'role_id' => $defaultRoleId,
        ), $errors);
        foreach ($errors as $error) $ost->logError("Create staff", $error);
        return $this->staff;
    }

    function updateAccess() {
        $defaultRoleId = $this->config->get('oidc-default-role-id');

        // exit early if there are no changes
        $oldIds = $this->getOldDeptIds();
        $newIds = $this->getNewDeptIds();
        sort($oldIds);
        sort($newIds);
        if ($oldIds === $newIds) return;

        // The primary department must change if it is not in the access token.
        // Do we need to change the primary department?
        $oldPrimaryDept = $this->getPrimaryDept();
        if (!isset($oldPrimaryDept) || !in_array($oldPrimaryDept->id, $newIds))
            $this->getStaff()->setDepartmentId($newIds[0]);

        $unchanged = [];
        $added = [];
        $newDepts = $this->getNewDepts();
        foreach ($newDepts as $dept) {
            if (in_array($dept->id, $oldIds))
                $unchanged[$dept->id] = $dept;
            else
                $added[$dept->id] = $dept;
        }

        $access = [];
        $oldRoles = $this->getStaff()->getRoles();
        foreach ($unchanged as $id => $dept) {
            // omit primary dept
            if ($id === $this->getStaff()->dept_id) continue;

            $role = $oldRoles[$id];
            $access[$id] = [$id, ($role->id ?: $defaultRoleId), true];
        }
        foreach ($added as $id => $dept) {
            // omit primary dept
            if ($id === $this->getStaff()->dept_id) continue;

            $access[$id] = [$id, $defaultRoleId, true];
        }

        $errors = [];
        $this->getStaff()->updateAccess($access, $errors);
        foreach ($errors as $id => $error) {
            var_dump($error);
            $ost->logError("Dept#" . $id, $error);
        }
    }
}

class OidcStaffAuthBackend extends ExternalStaffAuthenticationBackend {
    static $id = "oidc.client";
    static $name = /* trans */ "OIDC";
    static $service_name = "OpenID Connect";

    var $config;

    function __construct($config) {
        $this->config = $config;
        $this->oidc = new OidcAuth($config, 'staff');
    }

    function getName() {
        $config = $this->config;
        list($__, $_N) = $config->translate();
        return $__(static::$name);
    }

    function signOn() {
        if (isset($_SESSION[':oidc']['user'])) {
            $staff = StaffSession::lookup(['email' => $this->oidc->getEmail()]);

            if ($staff && $staff->getId()) {
                return $staff;
            } else {
                $_SESSION['_staff']['auth']['msg'] = 'Authentication required';
            }
        }
    }

    static function signOut($user) {
        parent::signOut($user);
        unset($_SESSION[':oidc']);
    }

    function triggerAuth() {
        parent::triggerAuth();
        $this->oidc->triggerAuth();
        Http::redirect(ROOT_PATH . 'scp/');
    }
}

class OidcUserAuthBackend extends ExternalUserAuthenticationBackend {
    static $id = "oidc.client";
    static $name = /* trans */ "OIDC";
    static $service_name = "OpenID Connect";

    var $config;

    function __construct($config) {
        $this->config = $config;
        $this->oidc = new OidcAuth($config, 'user');
    }

    function getName() {
        $config = $this->config;
        list($__, $_N) = $config->translate();
        return $__(static::$name);
    }

    function signOn() {
        if (isset($_SESSION[':oidc']['user'])) {
            $acct = ClientAccount::lookupByUsername($this->oidc->getEmail());
            $client = null;
            if ($acct && $acct->getId()) {
                $client = new ClientSession(new EndUser($acct->getUser()));
            }

            if ($client) {
                return $client;
            } else {
                return new ClientCreateRequest(
                    $this, $this->oidc->getEmail(), $this->oidc->getProfile());
            }
        }
    }

    static function signOut($user) {
        parent::signOut($user);
        unset($_SESSION[':oidc']);
    }

    function triggerAuth() {
        parent::triggerAuth();
        $this->oidc->triggerAuth();
        Http::redirect(ROOT_PATH . 'login.php');
    }
}
