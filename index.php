<?php

session_start();
require dirname(__FILE__).'/vendor/autoload.php';

$response = array(
    'status' => 'ok',
    'error'  => array()
);


if(!empty($_SESSION['callback_cnt']) && ($_SESSION['callback_cnt'] > 3)) {
    $response['status'] = 'fail';
    $response['error']['all'] = 'Too many requests';
}

$config = include dirname(__FILE__).'/config.php';

$body = '';
foreach($config['fields'] as $key => $field) {
    $val = isset($_POST[$key]) ? $_POST[$key] : '';
    if(!empty($field['validator']) && ($field['validator'] instanceof Respect\Validation\Validator)) {
        try {
            $field['validator']->assert($val);
        } catch (Respect\Validation\Exceptions\NestedValidationExceptionInterface $exception) {
            $errors = array();
            $response['status'] = 'fail';
            foreach ($exception->findMessages($config['messages']) as $message) {
                if($message) $errors[] = $message;
            }
            $response['error'][$key] = implode(', ',$errors);
        }
    }
    $name = empty($field['name']) ? ucfirst($key) : $field['name'];
    $body .= sprintf("<p><b>%s</b><br>\n%s<br>\n<br>\n</p>", $name, $val);
}

if($response['status'] == 'ok') {
    $transport = Swift_MailTransport::newInstance();
    $mailer = Swift_Mailer::newInstance($config['transport']);

    $message = Swift_Message::newInstance($config['subject'])
        ->setFrom($config['from'])
        ->setTo($config['recipients'])
        ->setBody($body, 'text/html');


    try {
        $result = $mailer->send($message);


        if (!empty($_SESSION['callback_time'])) {
            $_SESSION['callback_cnt'] += 1;
            if ((time() - $_SESSION['callback_time']) > 1800) {
                $_SESSION['callback_cnt'] = 1;
            }
        } else {
            $_SESSION['callback_cnt'] = 1;
        }
        $_SESSION['callback_time'] = time();

    } catch (Swift_SwiftException $e) {
        $response['error']['all'] = $e->getMessage();
    }
}


if(empty($response['error'])) unset($response['error']);
echo json_encode($response);
