<?php

include_once('config.inc.php');
include_once('misc.inc.php');
include_once('wefactauth.inc.php');

global $current_user;

$current_user = false;

// session startup
function _set_current_user($username, $userid, $localauth = true, $is_admin = false, $has_csrf_token = false, $is_api = false) {
    global $current_user;

    $current_user = array(
        'username' => $username,
        'id' => $userid,
        'localauth' => $localauth,
        'is_admin' => $is_admin,
        'has_csrf_token' => $has_csrf_token,
        'is_api' => $is_api,
    );
}

function _check_csrf_token($user) {
    global $secret;

    if (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && $_SERVER['HTTP_X_CSRF_TOKEN']) {
        $found_token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (isset($_POST['csrf-token']) && $_POST['csrf-token']) {
        $found_token = $_POST['csrf-token'];
    } else {
        $found_token = '';
    }

    if (isset($secret) && $secret) {
        # if we have a secret keep csrf-token valid across logins
        $csrf_hmac_secret = hash_pbkdf2('sha256', 'csrf_hmac', $secret, 100, 0, true);
        $userinfo = base64_encode($user['emailaddress']) . ':' . base64_encode($user['password']);
        $csrf_token = base64_encode(hash_hmac('sha256', $userinfo, $csrf_hmac_secret, true));
    } else {
        # without secret create new token for each session
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = base64_encode(openssl_random_pseudo_bytes(32));
        }
        $csrf_token = $_SESSION['csrf_token'];
    }

    if ($found_token === $csrf_token) {
        global $current_user;
        $current_user['has_csrf_token'] = true;
    }

    define('CSRF_TOKEN', $csrf_token);
    header("X-CSRF-Token: {$csrf_token}");
}

function enc_secret($message) {
    global $secret;

    if (isset($secret) && $secret) {
        $enc_secret = hash_pbkdf2('sha256', 'encryption', $secret, 100, 0, true);
        $hmac_secret = hash_pbkdf2('sha256', 'encryption_hmac', $secret, 100, 0, true);

        $mcrypt = mcrypt_module_open(MCRYPT_RIJNDAEL_256, '', MCRYPT_MODE_CBC, '') or die('missing mcrypt');

        # add PKCS#7 padding
        $blocksize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
        $padlength = $blocksize - (strlen($message) % $blocksize);
        $message .= str_repeat(chr($padlength), $padlength);

        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $ciphertext = $iv . mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $enc_secret, $message, MCRYPT_MODE_CBC, $iv);
        mcrypt_module_close($mcrypt);

        $mac = hash_hmac('sha256', $ciphertext, $hmac_secret, true);
        return 'enc:' . base64_encode($ciphertext) . ':' . base64_encode($mac);
    }

    return base64_encode($message);
}

function dec_secret($code) {
    global $secret;
    $is_encrypted = (substr($code, 0, 4) === 'enc:');
    if (isset($secret) && $secret) {
        if (!$is_encrypted) return false;

        $msg = explode(':', $code);
        if (3 != count($msg)) return false;

        $enc_secret = hash_pbkdf2('sha256', 'encryption', $secret, 100, 0, true);
        $hmac_secret = hash_pbkdf2('sha256', 'encryption_hmac', $secret, 100, 0, true);

        $msg[1] = base64_decode($msg[1]);
        $msg[2] = base64_decode($msg[2]);

        $mac = hash_hmac('sha256', $msg[1], $hmac_secret, true);
        # compare hashes first: this should prevent any timing leak
        if (hash('sha256', $mac, true) !== hash('sha256', $msg[2], true)) return false;
        if ($mac !== $msg[2]) return false;

        $mcrypt = mcrypt_module_open(MCRYPT_RIJNDAEL_256, '', MCRYPT_MODE_CBC, '') or die('missing mcrypt');
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
        $iv = substr($msg[1], 0, $iv_size);
        $ciphertext = substr($msg[1], $iv_size);
        $plaintext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $enc_secret, $ciphertext, MCRYPT_MODE_CBC, $iv);
        mcrypt_module_close($mcrypt);

        # remove PKCS#7 padding
        $len = strlen($plaintext);
        $padlength = ord($plaintext[$len-1]);
        $plaintext = substr($plaintext, 0, $len - $padlength);

        return $plaintext;
    }

    if ($is_encrypted) return false;
    return base64_decode($code);
}

function _unset_cookie($name) {
    $is_ssl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
    setcookie($name, "", -1, "", "", $is_ssl);
}

function _store_auto_login($value) {
    $is_ssl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
    // set for 30 days
    setcookie('NSEDIT_AUTOLOGIN', $value, time()+60*60*24*30, "", "", $is_ssl);
}

function try_login() {
    if (isset($_POST['username']) and isset($_POST['password'])) {
        if (_try_login($_POST['username'], $_POST['password'])) {
            global $secret;

            # only store if we have a secret.
            if ($secret && isset($_POST['autologin']) && $_POST['autologin']) {
                _store_auto_login(enc_secret(json_encode(array(
                    'username' => $_POST['username'],
                    'password' => $_POST['password']))));
            }
            return true;
        }
    }
    return false;
}

function _try_login($username, $password) {
    global $wefactapiurl, $wefactapikey;

    if (!valid_user($username)) {
        writelog("Illegal username at login!", $username);
        return false;
    }

    $do_local_auth = true;

    if (isset($wefactapiurl) && isset($wefactapikey)) {
        $wefact = do_wefact_auth($username, $password);
        if (false === $wefact ) {
            writelog("Failed Wefact login!", $username);
            return false;
        }
        if (-1 !== $wefact) {
            $do_local_auth = false;
        }
    }

    if ($do_local_auth && !do_db_auth($username, $password)) {
        writelog("Failed login!", $username);
        return false;
    }

    $user = get_user_info($username);
    if (!$user) {
        writelog("Failed to find user!", $username);
        return false;
    } else {
        _set_current_user($username, $user['id'], (bool) $do_local_auth, (bool) $user['isadmin']);

        if (session_id()) {
            session_unset();
            session_destroy();
        }
        session_start() or die('session failure: could not start session');
        session_regenerate_id(true) or die('session failure: regenerated id failed');
        session_unset();
        $_SESSION['username'] = $username;
        $_SESSION['localauth'] = $do_local_auth;
        $_SESSION['userid'] = $user['id'];

        # requires session:
        _check_csrf_token($user);
        return true;
    }
}

function _check_session() {
    global $adminapikey, $adminapiips;

    $is_ssl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
    session_set_cookie_params(30*60, null, null, $is_ssl, true);
    session_name('NSEDIT_SESSION');

    if (isset($adminapikey) && '' !== $adminapikey && isset($adminapiips) && isset($_POST['adminapikey'])) {
        if (false !== array_search($_SERVER['REMOTE_ADDR'], $adminapiips)
            and $_POST['adminapikey'] === $adminapikey)
        {
            # Allow this request, fake that we're logged in as user.
            return _set_current_user('admin', 1, false, true, true, true);
        }
        else
        {
            header('Status: 403 Forbidden');
            exit(0);
        }
    }

    if (isset($_COOKIE['NSEDIT_SESSION'])) {
        if (session_start() && isset($_SESSION['username'])) {
            $user = get_user_info($_SESSION['username']);
            if (!$user) {
                session_destroy();
                session_unset();
            } else {
                _set_current_user($_SESSION['username'], $_SESSION['userid'], (bool) $_SESSION['localauth'], (bool) $user['isadmin']);
                _check_csrf_token($user);
                return;
            }
        }
        // failed: remove cookie
        _unset_cookie('NSEDIT_SESSION');
    }

    if (isset($_COOKIE['NSEDIT_AUTOLOGIN'])) {
        $login = json_decode(dec_secret($_COOKIE['NSEDIT_AUTOLOGIN']), 1);
        if ($login and isset($login['username']) and isset($login['password'])
            and _try_login($login['username'], $login['password'])) {
            _store_auto_login($_COOKIE['NSEDIT_AUTOLOGIN']); # reset cookie
            return;
        }

        // failed: remove cookie
        _unset_cookie('NSEDIT_AUTOLOGIN');
    }
}

# auto load session if possible
_check_session();

function is_logged_in() {
    global $current_user;
    return (bool) $current_user;
}

# GET/HEAD requests only require a logged in user (they shouldn't trigger any
# "writes"); all other requests require the X-CSRF-Token to be present.
function is_csrf_safe() {
    global $current_user;

    switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
    case 'HEAD':
        return is_logged_in();
    default:
        return (bool) $current_user && (bool) $current_user['has_csrf_token'];
    }
}

function is_apiuser() {
    global $current_user;
    return $current_user && (bool) $current_user['is_api'];
}

function is_adminuser() {
    global $current_user;
    return $current_user && (bool) $current_user['is_admin'];
}

function get_sess_user() {
    global $current_user;
    return $current_user ? $current_user['username'] : null;
}

function get_sess_userid() {
    global $current_user;
    return $current_user ? $current_user['id'] : null;
}

function has_local_auth() {
    global $current_user;
    return $current_user ? $current_user['localauth'] : null;
}

function logout() {
    @session_destroy();
    @session_unset();
    if (isset($_COOKIE['NSEDIT_AUTOLOGIN'])) {
        _unset_cookie('NSEDIT_AUTOLOGIN');
    }
    if (isset($_COOKIE['NSEDIT_SESSION'])) {
        _unset_cookie('NSEDIT_SESSION');
    }
}
