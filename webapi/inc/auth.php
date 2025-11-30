<?php
// inc/auth.php
function cfg(){
    static $c;
    if (!$c) $c = require __DIR__ . '/config.php';
    return $c;
}

function read_json_file($path){
    if (!file_exists($path)) return [];
    $s = file_get_contents($path);
    if ($s === false || trim($s) === '') return [];
    $data = json_decode($s, true);
    return is_array($data) ? $data : [];
}

function write_json_file($path, $data){
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, $path);
}

function normalize_name($name){
    // Remove spaces and lowercase; also collapse multiple spaces
    $n = preg_replace('/\s+/', '', $name);
    return mb_strtolower($n, 'UTF-8');
}

function find_user_by_coachname($coachName){
    $users = read_json_file(cfg()['coaches_file']);
    $needle = normalize_name($coachName);
    foreach ($users as $u){
        if (isset($u['CoachName']) && normalize_name($u['CoachName']) === $needle){
            return $u;
        }
    }
    return null;
}

function find_user_by_email($email){
    $users = read_json_file(cfg()['coaches_file']);
    foreach ($users as $u){
        if (!empty($u['Email']) && strcasecmp($u['Email'], $email) === 0) return $u;
    }
    return null;
}

function update_user_by_coachname($coachName, $newData){
    $path = cfg()['coaches_file'];
    $users = read_json_file($path);
    $needle = normalize_name($coachName);
    foreach ($users as $i => $u){
        if (isset($u['CoachName']) && normalize_name($u['CoachName']) === $needle){
            $users[$i] = array_merge($u, $newData);
            write_json_file($path, $users);
            return true;
        }
    }
    return false;
}

function start_secure_session(){
    $c = cfg();
    if (session_status() === PHP_SESSION_NONE){
        session_name($c['session_name']);
        session_start();
        if (empty($_SESSION['initiated'])){
            session_regenerate_id(true);
            $_SESSION['initiated'] = time();
        }
    }
}

function require_login(){
    start_secure_session();
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function authenticate_user($coachName, $password){
    $user = find_user_by_coachname($coachName);
    if (!$user) return false;
    if (!isset($user['Password'])) return false;
    if (password_verify($password, $user['Password'])){
        // success
        return $user;
    }
    return false;
}

// --- reset tokens (stored in tokens.json) ---
function create_reset_token($email){
    $path = cfg()['tokens_file'];
    $tokens = read_json_file($path);
    $token = bin2hex(random_bytes(16));
    $tokens[] = [
        'email' => $email,
        'token' => $token,
        'expires' => time() + cfg()['reset_token_ttl']
    ];
    write_json_file($path, $tokens);
    return $token;
}

function consume_reset_token($token){
    $path = cfg()['tokens_file'];
    $tokens = read_json_file($path);
    foreach ($tokens as $i => $t){
        if (isset($t['token']) && hash_equals($t['token'], $token) && ($t['expires'] ?? 0) >= time()){
            $email = $t['email'];
            array_splice($tokens, $i, 1);
            write_json_file($path, $tokens);
            return $email;
        }
    }
    return false;
}

function update_password_by_email($email, $newPassword){
    $users = read_json_file(cfg()['coaches_file']);
    foreach ($users as $i => $u){
        if (!empty($u['Email']) && strcasecmp($u['Email'], $email) === 0){
            $users[$i]['Password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            write_json_file(cfg()['coaches_file'], $users);
            return true;
        }
    }
    return false;
}

function update_password_by_coachname($coachName, $newPassword){
    return update_user_by_coachname($coachName, ['Password' => password_hash($newPassword, PASSWORD_DEFAULT)]);
}
