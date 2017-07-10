<?php

use CCR\MailWrapper;

// Operation: user_auth->pass_reset

//require_once dirname(__FILE__).'/../../../classes/MailWrapper.php';

$isValid = isset($_POST['email']) && xd_security\isEmailValid($_POST['email']);

if (!$isValid) {
    $returnData['status'] = 'invalid_email_address';
    xd_controller\returnJSON($returnData);
};

// -----------------------------

$user_to_email = XDUser::userExistsWithEmailAddress($_POST['email'], TRUE);

if ($user_to_email == INVALID) {
    $returnData['status'] = 'no_user_mapping';
    xd_controller\returnJSON($returnData);
}

if ($user_to_email == AMBIGUOUS) {
    $returnData['status'] = 'multiple_accounts_mapped';
    xd_controller\returnJSON($returnData);
}

$user_to_email = XDUser::getUserByID($user_to_email);

// -----------------------------

$page_title = xd_utilities\getConfiguration('general', 'title');

$recipient
    = (xd_utilities\getConfiguration('general', 'debug_mode') == 'on')
    ? xd_utilities\getConfiguration('general', 'debug_recipient')
    : $user_to_email->getEmailAddress();

// -------------------

try {
    $subject = "$page_title: Password Reset";

    $username = $user_to_email->getUsername();
    $rid = md5($username . $user_to_email->getPasswordLastUpdatedTimestamp());

    $site_address
        = \xd_utilities\getConfigurationUrlBase('general', 'site_address');
    $resetUrl = "${site_address}password_reset.php?rid=$rid";

    $props = array(
        'first_name'           => $user_to_email->getFirstName(),
        'username'             => $username,
        'reset_url'            => $resetUrl,
        'maintainer_signature' => MailWrapper::getMaintainerSignature()
    );

    $properties = array(
        'body'=>'',
        'subject'=>$subject,
        'toAddress'=>array(
            array('address'=>$recipient)
        )
    );

    MailWrapper::sendTemplate('password_reset', $props, $properties);
    $returnData['success'] = true;
    $returnData['status']  = 'success';
}
catch (Exception $e) {
    $returnData['success'] = false;
    $returnData['message'] = $e->getMessage();
    $returnData['status']  = 'failure';
}

xd_controller\returnJSON($returnData);

