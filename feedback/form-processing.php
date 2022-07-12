<?php

header('Content-Type: application/json');

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit();
}

define('LOG_FILE', 'logs/' . date('Y-m-d') . '.log');
define('HAS_WRITE_LOG', true);
define('HAS_CHECK_CAPTCHA', true);
define('HAS_ATTACH_REQUIRED', false);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/gif', 'image/png']);
define('MAX_FILE_SIZE', 512 * 1024);
define('UPLOAD_PATH', dirname(__FILE__) . '/uploads/');

define('HAS_SEND_EMAIL', true);
define('HAS_ATTACH_IN_BODY', true);
define('EMAIL_SETTINGS', [
  'addresses' => ['manager@domain.com'], 
  'from' => ['no-reply@domain.com', 'website_name'], 
  'subject' => 'Message from the feedback form', 
  'host' => 'ssl://smtp.yandex.ru', 
  'username' => 'name@yandex.ru', 
  'password' => '*********', // 
  'port' => '465' 
]);
define('HAS_SEND_NOTIFICATION', false);
define('BASE_URL', 'https://domain.com');
define('SUBJECT_FOR_CLIENT', 'Your message has been delivered');
define('HAS_WRITE_TXT', true);

function itc_log($message)
{
  if (HAS_WRITE_LOG) {
    error_log('Date:  ' . date('d.m.Y h:i:s') . '  |  ' . $message . PHP_EOL, 3, LOG_FILE);
  }
}

$data = [
  'errors' => [],
  'form' => [],
  'logs' => [],
  'result' => 'success'
];

$attachs = [];


if (!empty($_POST['name'])) {
  $data['form']['name'] = htmlspecialchars($_POST['name']);
} else {
  $data['result'] = 'error';
  $data['errors']['name'] = 'Fill in this field.';
  itc_log('The field name is not filled.');
}


if (!empty($_POST['email'])) {
  $data['form']['email'] = $_POST['email'];
  if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $data['result'] = 'error';
    $data['errors']['email'] = 'Email not correct.';
    itc_log('Email not correct.');
  }
} else {
  $data['result'] = 'error';
  $data['errors']['email'] = 'Fill out this field.';
  itc_log('Email field not filled.');
}


if (!empty($_POST['message'])) {
  $data['form']['message'] = htmlspecialchars($_POST['message']);
  if (mb_strlen($data['form']['message'], 'UTF-8') < 20) {
    $data['result'] = 'error';
    $data['errors']['message'] = 'This field must be at least 20 characters long.';
    itc_log('This field must be at least 20 characters long.');
  }
} else {
  $data['result'] = 'error';
  $data['errors']['message'] = 'Fill out this field.';
  itc_log('Message field not filled.');
}


if (HAS_CHECK_CAPTCHA) {
  session_start();
  if ($_POST['captcha'] === $_SESSION['captcha']) {
    $data['form']['captcha'] = $_POST['captcha'];
  } else {
    $data['result'] = 'error';
    $data['errors']['captcha'] = 'The code does not match the image.';
    itc_log('Captcha not passed. Specified code' . $captcha . ' does not match ' . $_SESSION['captcha']);
  }
}

if ($_POST['agree'] == 'true') {
  $data['form']['agree'] = true;
} else {
  $data['result'] = 'error';
  $data['errors']['agree'] = 'This checkbox must be checked.';
  itc_log('The agree box is not checked.');
}

if (empty($_FILES['attach'])) {
  if (HAS_ATTACH_REQUIRED) {
    $data['result'] = 'error';
    $data['errors']['attach'] = 'Fill out this field.';
    itc_log('Files are not attached to the form.');
  }
} else {
  foreach ($_FILES['attach']['error'] as $key => $error) {
    if ($error == UPLOAD_ERR_OK) {
      $name = basename($_FILES['attach']['name'][$key]);
      $size = $_FILES['attach']['size'][$key];
      $mtype = mime_content_type($_FILES['attach']['tmp_name'][$key]);
      if (!in_array($mtype, ALLOWED_MIME_TYPES)) {
        $data['result'] = 'error';
        $data['errors']['attach'][$key] = 'The file has an invalid type.';
        itc_log('Attached file ' . $name . ' has an invalid type.');
      } else if ($size > MAX_FILE_SIZE) {
        $data['result'] = 'error';
        $data['errors']['attach'][$key] = 'The file size is too large.';
        itc_log('file size ' . $name . ' exceeds the allowable.');
      }
    }
  }
  if ($data['result'] === 'success') {

    foreach ($_FILES['attach']['name'] as $key => $attach) {
      $ext = mb_strtolower(pathinfo($_FILES['attach']['name'][$key], PATHINFO_EXTENSION));
      $name = basename($_FILES['attach']['name'][$key], $ext);
      $tmp = $_FILES['attach']['tmp_name'][$key];
      $newName = rtrim($name, '.') . '_' . uniqid() . '.' . $ext;
      if (!move_uploaded_file($tmp, UPLOAD_PATH . $newName)) {
        $data['result'] = 'error';
        $data['errors']['attach'][$key] = 'Error loading file.';
        itc_log('Error while moving file' . $name . '.');
      } else {
        $attachs[] = UPLOAD_PATH . $newName;
      }
    }
  }
}

// place php server storage files here

if ($data['result'] == 'success' && HAS_WRITE_TXT) {
  $output = '=======' . date('d.m.Y H:i') . '=======';
  $output .= 'Имя: ' . $data['form']['name'] . PHP_EOL;
  $output .= 'Email: ' . $data['form']['email'] . PHP_EOL;
  $output .= 'Сообщение: ' . $data['form']['message'] . PHP_EOL;
  if (count($attachs)) {
    $output .= 'Files:' . PHP_EOL;
    foreach ($attachs as $attach) {
      $output .= $attach . PHP_EOL;
    }
  }
  $output = '=====================';
  error_log($output, 3, 'logs/forms.log');
}

echo json_encode($data);
exit();
