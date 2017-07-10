<?php

use CCR\MailWrapper;

// Operation: user_admin->pass_reset

$params = array('uid' => RESTRICTION_UID);

$isValid = xd_security\secureCheck($params, 'POST');

if (!$isValid) {
    $returnData['success'] = false;
    $returnData['status'] = 'invalid_id_specified';
    xd_controller\returnJSON($returnData);
};

// -----------------------------

$user_to_email = XDUser::getUserByID($_POST['uid']);

if ($user_to_email == NULL) {
    $returnData['success'] = false;
    $returnData['status'] = 'user_does_not_exist';
    xd_controller\returnJSON($returnData);
}

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
    $returnData['status'] = "Password reset e-mail sent to user {$user_to_email->getUsername()}";
    $returnData['message'] = $returnData['status'];
}
catch (Exception $e) {
    $returnData['success'] = false;
    $returnData['message'] = $e->getMessage();
}

xd_controller\returnJSON($returnData);

