<?php

session_start();

$id = 'captcha';
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_SANITIZE_STRING);
}

$captchaStr = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz';
$captchaStr = substr(str_shuffle($captchaStr), 0, 6);
$_SESSION[$id] = $captchaStr;

//background.png
$image = imagecreatefrompng(dirname(__FILE__) . '/background.png');
$colour = imagecolorallocate($image, 130, 130, 130);
$font = dirname(__FILE__) . '/oswald.ttf';
$rotate = rand(-10, 10);
imagettftext($image, 36, $rotate, 56, 64, $colour, $font, $captchaStr);
header('Content-type: image/png');
imagepng($image);
