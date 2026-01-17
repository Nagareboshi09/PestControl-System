<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

    function SendMail($username, $email, $otp){
        require 'PHPMailer/src/Exception.php';
        require 'PHPMailer/src/PHPMailer.php';
        require 'PHPMailer/src/SMTP.php';

        $mail = new PHPMailer(true);
        try {
            // Server settings
            //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                     //Enable verbose debug output
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host       = 'smtp.gmail.com';                       //Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $mail->Username   = 'narteareanfredrick@gmail.com';               //SMTP username
            $mail->Password   = 'esyr zjtg sxoo mgrj';                   //SMTP password

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         //ENCRYPTION_SMTPS - Enable implicit TLS encryption
            $mail->Port       = 587;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $mail->setFrom('narteareanfredrick@gmail.com', 'MacJ Pest Control');
            $mail->addAddress($email, $username);     //Add a recipient


            //Content
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = 'MacJ Pest Control Service';
            $mail->Body = '
                    <h3>MacJ Pest Control - Email Verification</h3>
                    <p>Dear ' . htmlspecialchars($username) . ',</p>
                    <p>Thank you for registering with MacJ Pest Control. To complete your registration, please use the following verification code:</p>
                    <p style="font-size: 24px; font-weight: bold; color: #28a745; text-align: center; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">' . $otp . '</p>
                    <p>Please enter this code on the verification page to confirm your email address and activate your account.</p>
                    <p>If you did not request this verification code, please ignore this email.</p>
                    <p>Thank you for choosing MacJ Pest Control for your pest management needs!</p>
                    <p>Best regards,<br>The MacJ Pest Control Team</p>
                ';
            $mail->send();


        } catch (Exception $e) {
            $_SESSION["status"] = "Message could not be sent. Mailer Error: " . $mail->ErrorInfo;
            header("Location: " . $_SERVER["HTTP_REFERER"]);
            exit(0);
        }


        }













?>
