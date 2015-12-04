<?php

function oxid_userlogin(){
	global $oxid;
	

	$oxid['oxid_salt'] = $oxid['db']->get_var("SELECT OXPASSSALT FROM ".$oxid['table_users']." WHERE OXUSERNAME  = '".$oxid['oxid_user']."' ");

	$oxid['oxid_password'] = hash('sha512',$oxid['oxid_password'].$oxid['oxid_salt']);

	$user = $oxid['db']->get_row("SELECT OXID, OXUSERNAME ,OXPASSWORD,OXRIGHTS FROM ".$oxid['table_users']." WHERE OXUSERNAME  = '".$oxid['oxid_user']."' and OXPASSWORD  = '".$oxid['oxid_password']."'");

	if($user && $user->OXRIGHTS == 'malladmin')
		return true;
	else
		return false;
}

function error_handler($level, $message, $file, $line, $context) {

    //Handle user errors, warnings, and notices ourself
    if($level !== E_NOTICE && $level !== E_DEPRECATED && $level !== E_USER_DEPRECATED) {
        Cartridge::set_log('Error '.$level.': '.$message .'(Line: '.$line.' - File: '.$file.')');
        Cartridge::set_log( "\r\n##################################\r\n");
        send_error_mail('Error '.$level.': '.$message .'(Line: '.$line.' - File: '.$file.')');
        Cartridge::remove_lock_file();
        die();
    }
    return true;
    
}

function send_error_mail($message){
	require ABSPATH .'/lib/class.phpmailer.php';
	require ABSPATH .'/lib/class.smtp.php';

	$mail = new PHPMailer;
	$mail->isSMTP();
	
	// 0 = off (for production use)
	// 1 = client messages
	// 2 = client and server messages
	$mail->SMTPDebug = 0;
	$mail->Debugoutput = 'html';
	$mail->Host = "mail.your-server.de";

	$mail->Port = 25;
	$mail->SMTPAuth = true;

	$mail->Username = "error@wortbildton.de";
	$mail->Password = "ErrorWBT2015$";

	$mail->setFrom('error@wortbildton.de', 'Error Server');
	$mail->addAddress('baltruschat@wortbildton.de', 'Felix Baltruschat');
	//Set the subject line
	$mail->Subject = 'Error GOSCH TS Server';
	
	$mail->msgHTML($message);
	$mail->AltBody = strip_tags($message);

	$mail->send();
}