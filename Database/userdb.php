<?php
require_once("connectdb.php");

function Insert_User($username, $fname, $lname, $email, $contact, $password, $account_role, $location = null, $placeType = null) {
    $pdo = null; // Initialize PDO variable
    try {
        $pdo = ConnectDB();
        $stmt = $pdo->prepare("INSERT INTO clients (first_name, last_name, email, contact_number, password, location_address, type_of_place) VALUES (:First_name, :Last_name, :Email, :Contact, :Password, :Location, :PlaceType)");

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt->bindParam(":First_name", $fname);
        $stmt->bindParam(":Last_name", $lname);
        $stmt->bindParam(":Email", $email);
        $stmt->bindParam(":Contact", $contact);
        $stmt->bindParam(":Password", $hashed_password);
        $stmt->bindParam(":Location", $location);
        $stmt->bindParam(":PlaceType", $placeType);

        // Execute the statement
        $stmt->execute();
        return $pdo->lastInsertId();

    } catch(PDOException $e) {
        // Log the error message to identify the issue
        error_log("Insert Failed: " . $e->getMessage());
        return false;

    } finally {
        // Close PDO connection
        if ($pdo !== null) {
            $pdo = null;
        }
    }
}

function User_Email_Exists($email) {
    $pdo = null;
    try {
        $pdo = ConnectDB();
        $stmt = $pdo->prepare("SELECT email FROM clients WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        error_log($e->getMessage());
        return false;
    } finally {
        // Close PDO connection
        if ($pdo !== null) {
            $pdo = null;
        }
    }
}
?>
