<?php
require('../vendor/autoload.php');

use Gregwar\Captcha\CaptchaBuilder;
use Proxy\Http\Request;
use Proxy\Proxy;

class SEC2SessionHandler extends SessionHandler {
    public function close() {
        return parent::close();
    }

    public function create_sid() {
        return sha1(parent::create_sid());
    }

    public function destroy($session_id) {
        return parent::destroy($session_id);
    }

    public function gc($maxlifetime) {
        return parent::gc($maxlifetime);
    }

    public function open($save_path, $session_name) {
        return parent::open($save_path, $session_name);
    }

    public function read($session_id) {
        return parent::read($session_id);
    }

    public function write($session_id, $session_data) {
        return parent::write($session_id, $session_data);
    }
}

function nBetween($varToCheck, $high, $low) {
	if($varToCheck < $low) return false;
	if($varToCheck > $high) return false;
	
	return true;
}

function getrayid() {
	return md5(session_id());
}

function valid_captcha() {
	if(!isset($_SESSION['valid'])) {
		return false;
	} elseif($_SESSION['valid']) {
		return true;
	}

	if(!isset($_SESSION['captcha'])) {
		return false;
	}

	if(!isset($_POST['captcha'])) {
		return false;
	}

	if(!$_SESSION['captcha'] === $_POST['captcha']) {
		return false;
	}

	$_SESSION['valid'] = true;
	return true;
}

function sess_makecaptcha() {
	$captcha = new CaptchaBuilder;
	$captcha->build();

	if(!isset($_SESSION['captcha'])) {
		$_SESSION['valid'] = false;
		$_SESSION['captcha'] = $captcha->getPhrase();
	}

	return $captcha->inline();
}

function sess_start() {
	session_name('antiddos_session');
    session_start();

    if(session_status() == PHP_SESSION_ACTIVE) {
		if(isset($_SESSION['request'])) {
			$_SESSION['request']++;
		} else {
			$_SESSION['request'] = 0;
		}
    }
}

ini_set('session.use_strict_mode', 1);
ini_set('session.sid_length', 40);
ini_set('session.save_handler', 'files');

$handler = new SEC2SessionHandler();
session_set_save_handler($handler, true);

sess_start();
$valid = valid_captcha();

if(isset($_POST['captcha']) && $valid) {
	header('Location: /');
	die('Plz wait...');
}

if ($valid) {
	$request = Request::createFromGlobals();
	$proxy = new Proxy();

	$proxy->getEventDispatcher()->addListener('request.sent', function($event) {
		if(!nBetween($event['response']->getStatusCode(), 499, 100)) {
			die("Bad status code!");
		}
	});

	$proxy->getEventDispatcher()->addListener('request.complete', function($event) {
		$content = $event['response']->getContent();
		$event['response']->setContent($content);
	});

	$response = $proxy->forward($request, "http://localhost:3085". $_SERVER["REQUEST_URI"]);

	// Serve!
	$response->send();
} else {
	$lol = sess_makecaptcha();
	?><h1 style="margin:0px;">SkidZ Anti DDOS</h1>
	<p style="margin:0px;"><small><?= getrayid(); ?></small></p>
	<img src="<?= $lol; ?>" style="border:1px dotted;margin-bottom:15px;margin-top:15px;" />
	<br /><span>Reload the page for a new captcha.</span>
	<form action="/" method="POST">
		<input type="text" name="captcha" placeholder="What do you see in this captcha." style="width:250px;">
		<input type="submit" value="Letse Go!" />
	</form><?php
}